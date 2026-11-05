<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\QuoteService;
use App\Support\Hardware\Icecat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuoteDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_details_are_autofilled_editable_and_survive_product_changes_and_duplication(): void
    {
        $service = app(QuoteService::class);
        $seller = User::factory()->create(['role' => 'seller']);
        $product = Product::factory()->create([
            'brand_id' => Brand::factory()->create(['name' => 'MSI'])->id,
            'model' => 'X870E TOMAHAWK', 'warranty_months' => 12,
        ]);
        $this->actingAs($seller)->getJson('/admin/quotes/products?q='.urlencode($product->sku))
            ->assertOk()->assertJsonPath('0.brand', 'MSI')->assertJsonPath('0.model', 'X870E TOMAHAWK')->assertJsonPath('0.warranty', '1 AÑO');
        $line = $service->lineFromProduct($product);
        $quote = $service->saveFromAdmin(null, [
            'customer_id' => Customer::create(['name' => 'Ana'])->id,
            'items' => [$line],
        ], $seller);
        $product->update(['model' => 'Modelo cambiado', 'warranty_months' => 24]);
        $this->assertSame('X870E TOMAHAWK', $quote->fresh()->items[0]->model);
        $this->put('/admin/quotes/'.$quote->id, [
            'customer_id' => $quote->customer_id, 'status' => 'draft', 'valid_until' => now()->addDays(15)->toDateString(),
            'items' => [array_replace($line, ['brand' => 'Marca escrita', 'model' => 'Modelo escrito', 'warranty' => '6 MESES'])],
        ])->assertRedirect();
        $copy = $service->duplicate($quote->fresh()->load('items', 'customer'), $seller);
        $this->assertSame('Marca escrita', $copy->items[0]->brand);
        $this->assertSame('Modelo escrito', $copy->items[0]->model);
        $this->assertSame('6 MESES', $copy->items[0]->warranty);
        $this->assertSame('2 AÑOS', QuoteService::warrantyText(24));
        $this->assertSame('6 MESES', QuoteService::warrantyText(6));
        $this->assertNull(QuoteService::warrantyText(null));
    }

    public function test_pdf_and_its_rendered_content_have_item_details_and_correct_pre_tax_totals(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $quote = app(QuoteService::class)->saveFromAdmin(null, [
            'customer_id' => Customer::create(['name' => 'Ana'])->id,
            'valid_until' => now()->addDays(15)->toDateString(),
            'items' => [['description' => 'Tarjeta madre de prueba', 'brand' => 'MSI', 'model' => 'X870E TOMAHAWK', 'warranty' => '1 AÑO', 'quantity' => 2, 'unit_price' => 7308]],
        ], $seller);
        $this->assertEquals(14616, $quote->total);
        $this->assertEquals(12600, $quote->subtotal);
        $this->assertEquals(2016, $quote->tax);
        $html = view('pdf.quote', ['quote' => $quote->load('user'), 'logo' => public_path('img/ats-computer.png')])->render();
        foreach (['CLIENTE', 'FOLIO', 'MSI', 'X870E TOMAHAWK', '1 AÑO', '$6,300.00', '$12,600.00', '$2,016.00', '$14,616.00', '15 días', 'CONDICIONES COMERCIALES'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->actingAs($seller)->get('/admin/quotes/'.$quote->id.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/cotizacion/'.$quote->public_token.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $quote->update(['discount' => 116, 'total' => 14500]);
        $html = view('pdf.quote', ['quote' => $quote, 'logo' => public_path('img/ats-computer.png')])->render();
        $this->assertEquals($quote->total, $quote->subtotal + $quote->tax);
        $this->assertStringContainsString('DESCUENTO', $html);
        $this->assertStringContainsString('$14,500.00', $html);
    }

    public function test_cache_only_model_command_simulates_without_network_and_preserves_written_models(): void
    {
        Http::preventStrayRequests();
        $cache = Storage::fake('icecat');
        $fixture = json_decode(file_get_contents(base_path('tests/fixtures/icecat/model.json')), true);
        $cache->put('fixture.json', json_encode(['data' => $fixture, 'code' => 200, 'queried_at' => now()->timestamp]));
        $cache->put('corrupt.json', '{');
        $this->app->instance(Icecat::class, new Icecat($cache->path('')));
        $product = Product::factory()->create(['sku' => 'OB4711085948410', 'model' => null]);
        $manual = Product::factory()->create(['sku' => '4711085948410', 'model' => 'Modelo manual']);
        $missing = Product::factory()->create(['model' => null]);
        $this->artisan('catalogo:modelos', ['--simular' => true])->expectsOutputToContain('LANCER BLADE RGB')->assertSuccessful();
        $this->assertNull($product->fresh()->model);
        $this->artisan('catalogo:modelos')->assertSuccessful();
        $this->assertSame('LANCER BLADE RGB', $product->fresh()->model);
        $this->assertSame('Modelo manual', $manual->fresh()->model);
        $this->assertNull($missing->fresh()->model);
        Http::assertNothingSent();
    }

    public function test_new_settings_are_owner_only_and_validated(): void
    {
        $payload = ['business_name' => 'Negocio de prueba', 'exchange_rate' => 20, 'quote_validity_days' => 15, 'location_name' => 'Sucursal de prueba', 'phone' => '5555555555'];
        $this->actingAs(User::factory()->create(['role' => 'seller']))->put('/admin/settings', $payload)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'owner']))->put('/admin/settings', $payload)->assertRedirect();
        $this->assertSame('Sucursal de prueba', Setting::find('location_name')->value);
        $this->put('/admin/settings', array_replace($payload, ['phone' => str_repeat('1', 41)]))->assertSessionHasErrors('phone');
    }
}
