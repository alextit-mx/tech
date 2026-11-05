<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ServiceOrder;
use App\Models\ServicePlan;
use App\Models\User;
use App\Models\Warranty;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seller(): User
    {
        return User::factory()->create(['role' => 'seller']);
    }

    private function order(User $user): ServiceOrder
    {
        $this->actingAs($user)->post('/admin/service', [
            'customer_name' => 'Pedro',
            'customer_phone' => '55 8765 4321',
            'device_type' => 'laptop',
            'brand' => 'Lenovo',
            'reported_issue' => 'No enciende',
        ])->assertRedirect();

        return ServiceOrder::firstOrFail();
    }

    public function test_reception_creates_folio_customer_and_first_event(): void
    {
        $order = $this->order($this->seller());

        $this->assertSame('OS-'.now()->year.'-0001', $order->folio);
        $this->assertSame('5587654321', $order->customer->phone);
        $this->assertSame('received', $order->status);
        $this->assertCount(1, $order->events);
        $this->actingAs($this->seller())->get("/admin/service/{$order->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_status_flow_records_events_and_delivery_date(): void
    {
        $seller = $this->seller();
        $order = $this->order($seller);

        $this->actingAs($seller)->post("/admin/service/{$order->id}/events", ['status' => 'repairing', 'note' => 'Cambio de SSD', 'is_public' => true]);
        $this->actingAs($seller)->post("/admin/service/{$order->id}/events", ['note' => 'Cliente pidió descuento', 'is_public' => false]);
        $this->actingAs($seller)->post("/admin/service/{$order->id}/events", ['status' => 'delivered', 'is_public' => true]);

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertNotNull($order->delivered_at);
        $this->assertCount(4, $order->events);
    }

    public function test_public_status_requires_last_digits_and_hides_internal_notes(): void
    {
        $seller = $this->seller();
        $order = $this->order($seller);
        $order->log('diagnosing', 'Revisando la tarjeta madre', true, $seller);
        $order->log(null, 'NOTA INTERNA secreta', false, $seller);

        $this->post('/estado', ['folio' => strtolower($order->folio), 'digits' => '0000'])->assertOk()->assertDontSee('Revisando la tarjeta madre')->assertSee('No encontramos');
        $this->post('/estado', ['folio' => $order->folio, 'digits' => '4321'])->assertOk()
            ->assertSee('Revisando la tarjeta madre')->assertDontSee('NOTA INTERNA')->assertDontSee('5587654321');
    }

    public function test_warranty_from_accepted_quote_copies_items_and_tracks_validity_per_item(): void
    {
        $seller = $this->seller();
        $product = Product::factory()->create(['is_published' => true, 'price' => 1000, 'warranty_months' => 36]);
        $quote = app(QuoteService::class)->createFromCart(['name' => 'Ana', 'phone' => '5512345678'], [['product_id' => $product->id]], 'web');
        $quote->update(['status' => 'accepted']);

        $this->actingAs($seller)->get("/admin/warranties/create?quote={$quote->id}")
            ->assertInertia(fn (Assert $page) => $page->component('admin/WarrantyForm')->where('items.0.months', 36)->where('source.quote_id', $quote->id));

        $this->actingAs($seller)->post('/admin/warranties', [
            'customer_id' => $quote->customer_id,
            'quote_id' => $quote->id,
            'starts_at' => now()->subMonths(6)->toDateString(),
            'items' => [['description' => 'GPU', 'serial' => 'SN-123', 'months' => 36], ['description' => 'Armado', 'months' => 3]],
        ])->assertRedirect('/admin/warranties');

        $warranty = Warranty::with('items')->firstOrFail();
        $this->assertSame('GAR-'.now()->year.'-0001', $warranty->folio);
        $this->assertTrue($warranty->items[0]->isActive());
        $this->assertFalse($warranty->items[1]->isActive());
        $this->assertSame('active', $warranty->status);

        $this->post('/garantia', ['folio' => $warranty->folio, 'proof' => 'sn-123'])->assertOk()->assertSee('Garantía vigente');
        $this->post('/garantia', ['folio' => $warranty->folio, 'proof' => '9999'])->assertOk()->assertSee('No encontramos');

        $this->actingAs($seller)->post("/admin/warranties/{$warranty->id}/void", ['void_reason' => 'Equipo abierto por terceros']);
        $this->assertSame('void', $warranty->fresh()->load('items')->status);
    }

    public function test_plan_counts_remaining_visits(): void
    {
        $seller = $this->seller();
        $customer = Customer::create(['name' => 'Escuela', 'phone' => '5511112222']);
        $this->actingAs($seller)->post('/admin/plans', [
            'customer_id' => $customer->id, 'name' => 'Mantenimiento', 'visits_included' => 2,
            'starts_at' => today()->toDateString(), 'ends_at' => today()->addYear()->toDateString(),
        ]);
        $plan = ServicePlan::firstOrFail();

        foreach ([1, 2, 3] as $n) {
            $this->actingAs($seller)->post("/admin/plans/{$plan->id}/visits", ['visited_at' => today()->toDateString(), 'work' => "Visita {$n}"]);
        }

        $this->assertSame(2, $plan->visits()->count());
        $this->assertSame(0, $plan->fresh()->remainingVisits());
    }

    public function test_dashboard_shows_service_and_expiring(): void
    {
        $seller = $this->seller();
        $this->order($seller);

        $this->actingAs($seller)->get('/admin')->assertInertia(fn (Assert $page) => $page->where('service.open.received', 1)->has('expiring.warranties'));
    }
}
