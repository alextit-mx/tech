<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Services\QuoteService;
use App\Support\Folio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    private function service(): QuoteService
    {
        return app(QuoteService::class);
    }

    private function customer(): Customer
    {
        return Customer::create(['name' => 'Ana', 'phone' => '5512345678']);
    }

    public function test_folio_is_consecutive_and_restarts_each_year(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 31));
        $this->assertSame('COT-2026-0001', DB::transaction(fn () => Folio::next('COT')));
        $this->assertSame('COT-2026-0002', DB::transaction(fn () => Folio::next('COT')));
        $this->assertSame('OS-2026-0001', DB::transaction(fn () => Folio::next('OS')));

        $this->travelTo(now()->setDate(2027, 1, 1));
        $this->assertSame('COT-2027-0001', DB::transaction(fn () => Folio::next('COT')));
    }

    public function test_totals_include_tax_and_pending_lines(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $quote = $this->service()->saveFromAdmin(null, [
            'customer_id' => $this->customer()->id,
            'status' => 'draft',
            'valid_until' => now()->addDays(3)->toDateString(),
            'discount' => 160,
            'items' => [
                ['description' => 'Armado y pruebas', 'quantity' => 1, 'unit_price' => 1160],
                ['description' => 'RTX por confirmar', 'quantity' => 2, 'unit_price' => null],
                ['description' => 'Pasta térmica', 'quantity' => 2, 'unit_price' => 580],
            ],
        ], $seller);

        $this->assertSame('2160.00', $quote->total);
        $this->assertEquals(1862.07, $quote->subtotal);
        $this->assertEquals(297.93, $quote->tax);
        $this->assertTrue($quote->has_pending_prices);
        $this->assertNull($quote->items[1]->line_total);
        $this->assertSame($seller->id, $quote->user_id);
    }

    public function test_cart_quote_uses_database_prices_and_reuses_customer(): void
    {
        $customer = $this->customer();
        $shown = Product::factory()->create(['price' => 4990, 'is_published' => true]);
        $quoted = Product::factory()->create(['price' => 9999, 'price_mode' => 'quote', 'is_published' => true]);
        $hidden = Product::factory()->create(['price' => 100, 'is_published' => false]);

        $quote = $this->service()->createFromCart(
            ['name' => 'Ana López', 'phone' => '+52 55 1234 5678'],
            [['product_id' => $shown->id, 'quantity' => 2, 'unit_price' => 1], ['product_id' => $quoted->id], ['product_id' => $hidden->id]],
            'web',
        );

        $this->assertSame($customer->id, $quote->customer_id);
        $this->assertSame('Ana López', $customer->fresh()->name);
        $this->assertCount(2, $quote->items);
        $this->assertSame('9980.00', $quote->total);
        $this->assertNull($quote->items->firstWhere('product_id', $quoted->id)->unit_price);
        $this->assertNull($quote->user_id);
        $this->assertSame('sent', $quote->status);
    }

    public function test_changing_the_product_does_not_change_existing_quotes(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'is_published' => true]);
        $quote = $this->service()->createFromCart(['name' => 'Ana', 'phone' => '5512345678'], [['product_id' => $product->id]], 'web');

        $product->update(['price' => 2000, 'name' => 'Otro nombre']);

        $item = $quote->fresh()->items->first();
        $this->assertSame('1000.00', $item->unit_price);
        $this->assertStringNotContainsString('Otro nombre', $item->description);
    }

    public function test_sent_quote_past_validity_shows_as_expired(): void
    {
        $quote = $this->service()->createFromCart(['name' => 'Ana', 'phone' => '5512345678'], [], 'web');
        $quote->update(['valid_until' => now()->subDay()]);

        $this->assertSame('expired', $quote->fresh()->display_status);
        $quote->update(['status' => 'accepted']);
        $this->assertSame('accepted', $quote->fresh()->display_status);
    }

    public function test_seller_creates_quote_and_never_receives_margin(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $product = Product::factory()->create(['price' => 1160, 'cost' => 500]);

        $this->actingAs($seller)->post('/admin/quotes', [
            'customer_id' => $this->customer()->id,
            'status' => 'draft',
            'valid_until' => now()->addDays(3)->toDateString(),
            'items' => [['product_id' => $product->id, 'description' => 'Producto', 'quantity' => 1, 'unit_price' => 1160]],
        ])->assertRedirect();

        $quote = Quote::firstOrFail();
        $this->assertSame('500.00', $quote->items->first()->unit_cost);
        $this->actingAs($seller)->get("/admin/quotes/{$quote->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->component('admin/QuoteEditor')->where('margin', null));
        $this->actingAs($seller)->get('/admin')
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('margin', null));

        $owner = User::factory()->create(['role' => 'owner']);
        $this->actingAs($owner)->get("/admin/quotes/{$quote->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('margin', fn ($m) => abs($m - 0.5) < 0.001));
    }

    public function test_web_quote_can_be_taken_by_a_seller(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $quote = $this->service()->createFromCart(['name' => 'Ana', 'phone' => '5512345678'], [], 'web');

        $this->actingAs($seller)->post("/admin/quotes/{$quote->id}/take")->assertRedirect("/admin/quotes/{$quote->id}/edit");
        $this->assertSame($seller->id, $quote->fresh()->user_id);
    }

    public function test_pdf_and_public_page(): void
    {
        $product = Product::factory()->create(['price' => 1000, 'is_published' => true]);
        $quote = $this->service()->createFromCart(['name' => 'Ana', 'phone' => '5512345678'], [['product_id' => $product->id]], 'web');

        $this->get("/cotizacion/{$quote->public_token}")->assertOk()->assertSee($quote->folio);
        $this->get("/cotizacion/{$quote->public_token}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/cotizacion/token-falso')->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => 'seller']))->get("/admin/quotes/{$quote->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_deactivated_user_is_logged_out(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $this->actingAs($seller)->get('/admin/quotes')->assertOk();

        $seller->update(['is_active' => false]);
        $this->actingAs($seller->fresh())->get('/admin/quotes')->assertRedirect('/login');
        $this->assertGuest();
    }
}
