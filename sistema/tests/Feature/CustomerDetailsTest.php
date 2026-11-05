<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function registered(array $overrides = []): array
    {
        return array_replace([
            'kind' => 'registrado', 'name' => 'Ana Pérez', 'company' => 'Estudio de prueba',
            'rfc' => 'AAA010101AA1', 'legal_name' => 'ESTUDIO DE PRUEBA', 'tax_regime' => '601',
            'tax_zip' => '06000', 'cfdi_use' => 'I04', 'street' => 'Calle de prueba', 'ext_number' => '10',
            'int_number' => '2', 'neighborhood' => 'Centro', 'city' => 'Cuauhtémoc', 'state' => 'Ciudad de México', 'zip' => '06000',
        ], $overrides);
    }

    public function test_seller_registers_and_updates_a_customer_with_fiscal_and_address_details(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'seller']))
            ->postJson('/admin/customers', $this->registered(['rfc' => ' aaa010101aa1 ']))
            ->assertOk()->assertJsonPath('rfc', 'AAA010101AA1')->assertJsonPath('zip', '06000');
        $customer = Customer::firstOrFail();
        $this->patchJson('/admin/customers/'.$customer->id, $this->registered(['company' => 'Estudio actualizado']))->assertRedirect();
        $this->assertSame('Estudio actualizado', $customer->fresh()->company);
        $this->assertSame('Calle de prueba 10 Int. 2, Centro, Cuauhtémoc, Ciudad de México, CP 06000', $customer->addressLine());
    }

    public function test_registered_customer_requires_minimum_fiscal_information(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/admin/customers', ['kind' => 'registrado', 'name' => 'Ana'])
            ->assertUnprocessable()->assertJsonValidationErrors(['rfc', 'legal_name', 'tax_regime', 'tax_zip']);
        $this->postJson('/admin/customers', $this->registered(['company' => null]))->assertOk();
    }

    public function test_rfc_format_person_type_and_generic_rfcs(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['123456789012', 'AAA011332AA1', 'AAA010101AA!', 'AA010101AAA', 'AAA010101AAAAA'] as $rfc) {
            $this->postJson('/admin/customers', $this->registered(['rfc' => $rfc]))->assertUnprocessable()->assertJsonValidationErrors('rfc');
        }
        $this->postJson('/admin/customers', $this->registered(['tax_regime' => '612']))->assertUnprocessable()->assertJsonValidationErrors('tax_regime');
        $this->postJson('/admin/customers', $this->registered(['rfc' => 'AAAA010101AA1', 'tax_regime' => '601']))->assertUnprocessable()->assertJsonValidationErrors('tax_regime');
        foreach (['AAAA010101AA1', 'XAXX010101000', 'XEXX010101000'] as $rfc) {
            $this->postJson('/admin/customers', $this->registered(['rfc' => $rfc, 'tax_regime' => '626']))->assertOk();
        }
    }

    public function test_registered_rfc_is_unique_only_among_registered_customers(): void
    {
        Customer::create(['name' => 'Mostrador', 'rfc' => 'AAA010101AA1']);
        $this->actingAs(User::factory()->create())->postJson('/admin/customers', $this->registered())->assertOk();
        $this->postJson('/admin/customers', $this->registered(['rfc' => 'aaa010101aa1']))->assertUnprocessable()->assertJsonValidationErrors('rfc');
    }

    public function test_zip_state_and_catalogs_are_validated(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/admin/customers', $this->registered([
            'tax_zip' => '1234', 'zip' => 'ABCDE', 'state' => 'Estado inexistente', 'cfdi_use' => 'P01', 'tax_regime' => '999',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['tax_zip', 'zip', 'state', 'cfdi_use', 'tax_regime']);
    }

    public function test_customer_lookup_and_list_search_company_legal_name_and_rfc_and_filter_kind(): void
    {
        $customer = Customer::create($this->registered());
        Customer::create(['name' => 'Cliente casual']);
        $this->actingAs(User::factory()->create(['role' => 'seller']));
        foreach (['AAA010101AA1', 'Estudio de prueba', 'ESTUDIO DE PRUEBA'] as $term) {
            $this->getJson('/admin/customers/lookup?q='.urlencode($term))->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $customer->id)->assertJsonPath('0.street', 'Calle de prueba');
            $this->get('/admin/customers?search='.urlencode($term))->assertInertia(fn (Assert $page) => $page->has('customers.data', 1));
        }
        $this->get('/admin/customers?kind=registrado')->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.kind', 'registrado'));
        $this->get('/admin/customers?kind=mostrador')->assertInertia(fn (Assert $page) => $page->has('customers.data', 1)->where('customers.data.0.name', 'Cliente casual'));
    }

    public function test_customer_created_from_quote_can_be_selected_without_leaving_the_editor(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'seller']));
        $customerId = $this->postJson('/admin/customers', $this->registered())->assertOk()->json('id');
        $this->post('/admin/quotes', [
            'customer_id' => $customerId, 'status' => 'draft', 'valid_until' => now()->addDays(15)->toDateString(),
            'items' => [['description' => 'Servicio de prueba', 'quantity' => 1, 'unit_price' => 116]],
        ])->assertRedirect();
        $quote = Quote::firstOrFail();
        $this->get('/admin/quotes/'.$quote->id.'/edit')->assertInertia(fn (Assert $page) => $page->where('customer.company', 'Estudio de prueba')->where('customer.tax_zip', '06000')->has('sat.states', 32));
    }

    public function test_walk_in_stays_quick_and_customer_endpoints_require_active_authentication(): void
    {
        $this->postJson('/admin/customers', ['name' => 'Ana'])->assertUnauthorized();
        $this->getJson('/admin/customers/lookup?q=Ana')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_active' => false]))->postJson('/admin/customers', ['name' => 'Ana'])->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'seller']))->postJson('/admin/customers', ['name' => 'Ana'])->assertOk()->assertJsonPath('kind', 'mostrador');
    }
}
