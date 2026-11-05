<?php

namespace Tests\Feature;

use App\Models\Build;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\Hardware\SpecExtractor;
use App\Support\PendingSpecs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PendingSpecsTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $slot = 'case', array $attributes = []): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->create(['builder_slot' => $slot])->id,
            'specs' => [], 'spec_sources' => [], 'stock' => 0, 'stock_24h' => 0,
            ...$attributes,
        ]);
    }

    public function test_lists_only_pending_for_slot_in_publication_stock_and_name_order(): void
    {
        $this->actingAs(User::factory()->create());
        $hidden = $this->product(attributes: ['name' => 'Oculto', 'is_published' => false, 'stock' => 100]);
        $last = $this->product(attributes: ['name' => 'Zeta', 'is_published' => true, 'stock' => 2]);
        $alpha = $this->product(attributes: ['name' => 'Alfa', 'is_published' => true, 'stock' => 1, 'stock_24h' => 1]);
        $first = $this->product(attributes: ['name' => 'Primero', 'is_published' => true, 'stock_24h' => 3]);
        $this->product('cooler');
        $confirmed = ['form_factors' => ['ATX'], 'max_gpu_length_mm' => 360, 'includes_psu' => false];
        $this->product(attributes: ['specs' => $confirmed, 'spec_sources' => array_fill_keys(array_keys($confirmed), 'manual')]);
        $this->get(route('admin.specs.pending', ['slot' => 'case']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/PendingSpecs')->where('counts.case', 4)->where('counts.cooler', 1)
            ->where('products.data.0.id', $first->id)->where('products.data.1.id', $alpha->id)
            ->where('products.data.2.id', $last->id)->where('products.data.3.id', $hidden->id)
            ->missing('products.data.0.cost')->missing('products.data.0.margin')->has('products.data', 4));
        $this->get(route('admin.specs.pending'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('filters.slot', 'case'));
    }

    public function test_search_stock_filter_and_pagination(): void
    {
        $this->actingAs(User::factory()->create());
        $this->product(attributes: ['name' => 'Sin existencias', 'sku' => 'MATCH-0']);
        $found = $this->product(attributes: ['name' => 'Disponible', 'sku' => 'MATCH-1', 'stock_24h' => 2]);
        $this->get(route('admin.specs.pending', ['slot' => 'case', 'search' => 'MATCH', 'in_stock' => 1]))
            ->assertOk()->assertInertia(fn (Assert $p) => $p->has('products.data', 1)->where('products.data.0.id', $found->id));
        $this->get(route('admin.specs.pending', ['search' => $found->brand->name]))->assertOk()->assertInertia(fn (Assert $p) => $p->has('products.data', 1));
        for ($i = 0; $i < 25; $i++) {
            $this->product();
        }
        $this->get(route('admin.specs.pending', ['slot' => 'case']))->assertOk()->assertInertia(fn (Assert $p) => $p->has('products.data', 25)->where('products.total', 27)->where('products.per_page', 25));
    }

    public function test_save_confirms_only_submitted_fields_clears_their_conflicts_and_removes_completed_row(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'seller']));
        $product = $this->product(attributes: [
            'specs' => ['form_factors' => ['ATX'], 'max_gpu_length_mm' => 320, 'includes_psu' => false, 'color' => 'Negro'],
            'spec_sources' => ['form_factors' => 'nombre', 'max_gpu_length_mm' => 'supuesto', 'includes_psu' => 'supuesto', 'color' => 'manual'],
            'spec_conflicts' => [['field' => 'max_gpu_length_mm', 'actual' => 320, 'icecat' => 360], ['field' => 'includes_psu', 'actual' => false, 'icecat' => true]],
        ]);
        $this->assertSame(1, array_sum(PendingSpecs::counts()));
        $this->patchJson(route('admin.specs.update', $product), ['specs' => ['max_gpu_length_mm' => 360]])
            ->assertOk()->assertJsonPath('complete', false)->assertJsonPath('product.pending', ['includes_psu']);
        $product->refresh();
        $this->assertSame('manual', $product->spec_sources['max_gpu_length_mm']);
        $this->assertSame('nombre', $product->spec_sources['form_factors']);
        $this->assertSame('Negro', $product->specs['color']);
        $this->assertCount(1, $product->spec_conflicts);
        $this->assertSame(1, array_sum(PendingSpecs::counts()));
        $this->patchJson(route('admin.specs.update', $product), ['specs' => ['includes_psu' => false]])->assertOk()->assertJsonPath('complete', true);
        $this->assertSame([], $product->fresh()->spec_conflicts);
        $this->get(route('admin.specs.pending', ['slot' => 'case']))->assertOk()->assertInertia(fn (Assert $p) => $p->has('products.data', 0)->where('pendingSpecsCount', 0));
    }

    public function test_rejects_invalid_types_ranges_options_and_other_slot_fields(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->product();
        foreach ([['max_gpu_length_mm' => -1], ['max_gpu_length_mm' => 1000001], ['max_gpu_length_mm' => 2.5], ['includes_psu' => 'quizá'], ['form_factors' => ['XXL']], ['form_factors' => []], ['watts' => 650], ['color' => 'Rojo']] as $specs) {
            $this->patchJson(route('admin.specs.update', $product), ['specs' => $specs])->assertUnprocessable();
        }
        $other = Product::factory()->create(['specs' => []]);
        $this->patchJson(route('admin.specs.update', $other), ['specs' => ['watts' => 650]])->assertUnprocessable();
        $storage = $this->product('storage');
        $this->patchJson(route('admin.specs.update', $storage), ['specs' => ['type' => 'M.2']])->assertUnprocessable()->assertJsonValidationErrors('specs.type');
        $this->assertSame([], $product->fresh()->specs);
    }

    public function test_permission_is_checked_for_listing_saving_and_review(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create());
        Gate::define('edit-products', fn () => false);
        $this->get(route('admin.specs.pending'))->assertForbidden();
        $this->patchJson(route('admin.specs.update', $product), ['specs' => ['includes_psu' => false]])->assertForbidden();
        $this->postJson(route('admin.specs.review'))->assertForbidden();
    }

    public function test_valid_published_build_remains_published(): void
    {
        $this->actingAs(User::factory()->create());
        $parts = [
            'cpu' => ['series' => 'Ryzen 7000', 'power_w' => 162, 'socket' => 'AM5', 'tdp_w' => 120, 'igpu' => true, 'tier' => 3],
            'motherboard' => ['chipset' => 'B850', 'ram_slots' => 4, 'socket' => 'AM5', 'memory_type' => 'DDR5', 'form_factor' => 'ATX', 'm2_slots' => 2],
            'ram' => ['memory_type' => 'DDR5', 'capacity_gb' => 32, 'modules' => 2],
            'storage' => ['type' => 'NVMe', 'capacity_gb' => 1000],
            'psu' => ['pcie_16pin' => true, 'pcie_8pin' => 2, 'watts' => 750, 'certification' => '80+ Gold'],
            'cooler' => ['sockets' => ['AM5'], 'max_tdp_w' => 200],
            'case' => ['includes_psu' => false, 'form_factors' => ['ATX'], 'max_gpu_length_mm' => 360],
        ];
        $ids = [];
        foreach ($parts as $slot => $specs) {
            $ids[$slot] = $this->product($slot, ['specs' => $specs, 'spec_sources' => array_fill_keys(array_keys($specs), 'manual')])->id;
        }
        $build = Build::create(['name' => 'Completo', 'slug' => 'completo', 'use' => 'office', 'is_published' => true]);
        $build->syncParts($ids);
        $draft = Build::create(['name' => 'Listo', 'slug' => 'listo', 'use' => 'office', 'is_published' => false]);
        $draft->syncParts($ids);
        $this->postJson(route('admin.specs.review'))->assertOk()->assertJsonPath('published', 1)->assertJsonPath('unpublished', 0)->assertJsonPath('ready', 1)->assertJsonPath('results.0.status', 'publicado')->assertJsonPath('results.0.problems', [])
            ->assertJsonPath('results.1.id', $draft->id)->assertJsonPath('results.1.status', 'listo')->assertJsonPath('results.1.problems', []);
        $this->assertTrue($build->fresh()->is_published);
        $this->assertFalse($draft->fresh()->is_published);
        $this->artisan('equipos:revisar')->expectsOutput('Listo (listo)')->expectsOutput('Listos para publicar: 1')->assertSuccessful();
        $this->assertFalse($draft->fresh()->is_published);
    }

    public function test_review_uses_the_same_service_as_the_command_and_reports_reasons(): void
    {
        $this->actingAs(User::factory()->create());
        $build = Build::create(['name' => 'Incompleto', 'slug' => 'incompleto', 'use' => 'office', 'is_published' => true]);
        $draft = Build::create(['name' => 'Borrador', 'slug' => 'borrador', 'use' => 'office', 'is_published' => false]);
        $this->postJson(route('admin.specs.review'))->assertOk()->assertJsonPath('published', 0)->assertJsonPath('unpublished', 1)
            ->assertJsonPath('ready', 0)->assertJsonPath('results.0.id', $build->id)->assertJsonPath('results.0.name', 'Incompleto')
            ->assertJsonPath('results.0.status', 'despublicado')->assertJsonPath('results.1.status', 'con_problemas')->assertJsonCount(2, 'results')
            ->assertJsonPath('results.0.problems', fn ($problems) => count($problems) > 0)
            ->assertJsonPath('results.1.problems', fn ($problems) => count($problems) > 0);
        $this->assertFalse($build->fresh()->is_published);
        $this->assertFalse($draft->fresh()->is_published);
        $this->postJson(route('admin.specs.review'))->assertOk()->assertJsonPath('unpublished', 0)->assertJsonPath('results.0.status', 'con_problemas')->assertJsonCount(2, 'results');
        $build->update(['is_published' => true]);
        $this->artisan('equipos:revisar')->assertSuccessful();
        $this->assertFalse($build->fresh()->is_published);
    }

    public function test_counts_are_cached_for_ten_minutes(): void
    {
        $this->product();
        $this->assertSame(1, array_sum(PendingSpecs::counts()));
        $this->product('cooler');
        $this->assertSame(1, array_sum(PendingSpecs::counts()));
        $this->travel(11)->minutes();
        $this->assertSame(2, array_sum(PendingSpecs::counts()));
    }

    public function test_product_editor_invalidates_counts_when_saving_or_confirming_specs(): void
    {
        $this->actingAs(User::factory()->create());
        $specs = ['form_factors' => ['ATX'], 'max_gpu_length_mm' => 360, 'includes_psu' => false];
        foreach ([false, true] as $confirm) {
            $product = $this->product(attributes: $confirm ? ['specs' => $specs, 'spec_sources' => array_fill_keys(array_keys($specs), 'supuesto')] : []);
            $this->assertSame(1, array_sum(PendingSpecs::counts()));
            $this->patch(route('admin.products.update', $product), [
                ...$product->only(['sku', 'name', 'category_id', 'cost_currency', 'price_mode', 'stock']),
                'specs' => $specs, 'confirm_specs' => $confirm,
            ])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertTrue($product->fresh()->specsVerified());
            $this->get(route('admin.specs.pending'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('pendingSpecsCount', 0)->where('counts.case', 0));
            PendingSpecs::forgetCounts();
        }
    }

    public function test_storage_types_from_names(): void
    {
        foreach (['SSD NVME 1TB' => 'NVMe', 'M.2 NVMe 500GB' => 'NVMe', 'SSD PCIE 1TB' => 'NVMe', 'SSD SATA 500GB' => 'SATA SSD', 'SSD 2.5" 500GB' => 'SATA SSD', 'HDD SATA 1TB' => 'HDD', 'DISCO DURO 2TB' => 'HDD', 'Disco 7200RPM 1TB' => 'HDD', 'Disco 3.5" 1TB' => 'HDD'] as $name => $type) {
            $result = SpecExtractor::extract('storage', $name);
            $this->assertSame($type, $result['specs']['type'], $name);
            $this->assertSame('nombre', $result['sources']['type']);
        }
    }
}
