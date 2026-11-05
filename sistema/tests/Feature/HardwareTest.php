<?php

namespace Tests\Feature;

use App\Models\Build;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\BuildSuggester;
use App\Support\Hardware\ChipsetTable;
use App\Support\Hardware\Compatibility;
use App\Support\Hardware\SpecExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HardwareTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_compatibility_cases_and_chipset_mirror(): void
    {
        foreach (json_decode(file_get_contents(base_path('tests/fixtures/compatibility.json')), true) as $case) {
            $issues = Compatibility::check($case['parts']);
            $this->assertSame($case['expected'], array_map(fn ($i) => ['code' => $i['code'], 'level' => $i['level']], $issues), $case['name']);
            $this->assertSame($case['issues'], $issues, $case['name']);
        }
        $this->assertSame(ChipsetTable::DATA, json_decode(file_get_contents(resource_path('js/builder/chipsets.json')), true));
    }

    public function test_real_names_extract_specs_with_origins(): void
    {
        $cases = [
            ['psu', 'Fuente Naceb Evotec P4600W 600W ATX 24PIN 12V', ['watts' => 600]],
            ['ram', 'RAM Corsair Vengeance Lpxr DDR4 16GB4X4GB 2666 Rojo CMK16GX4M4A2666C16R', ['modules' => 4, 'capacity_gb' => 16, 'speed_mhz' => 2666]],
            ['case', 'Gabinete Stylos Mini-Torre C/fuente 500W Metal Negro STPGAC5B', ['includes_psu' => true, 'max_gpu_length_mm' => 280]],
            ['motherboard', 'MB Gigabyte A320M S2H Ryzen Gen 1-3 DDR4 64GB M-ATX GA-A320M-S2H', ['chipset' => 'A320', 'socket' => 'AM4']],
            ['cpu', 'Procesador AMD Ryzen 9 5900XT 4.8 Ghz AM4', ['tdp_w' => 105, 'power_w' => 142, 'cores' => 16, 'threads' => 32]],
            ['motherboard', 'MOTHER AMD B650E-F AM5 4xDDR5 192GB M-ATX', ['chipset' => 'B650E', 'ram_slots' => 4]],
            ['gpu', 'ASUS Prime RTX5080 16GB', ['tdp_w' => 360, 'recommended_psu_w' => 850]],
            ['cooler', 'Royal Talent F120 AM4 AM5', ['max_tdp_w' => 95]],
        ];
        foreach ($cases as [$slot, $name, $expected]) {
            $actual = SpecExtractor::extract($slot, $name);
            foreach ($expected as $key => $value) {
                $this->assertSame($value, $actual['specs'][$key] ?? null, $name.' '.$key);
            }
            $this->assertEquals(array_keys($actual['specs']), array_keys($actual['sources']));
        }
        $this->assertSame('tabla', SpecExtractor::extract('cpu', $cases[4][1])['sources']['power_w']);
        $this->assertSame('supuesto', SpecExtractor::extract('gpu', 'RTX5080')['sources']['power_connector']);
        $this->assertSame('supuesto', SpecExtractor::extract('cooler', 'Royal Talent F120')['sources']['max_tdp_w']);
        $this->assertArrayNotHasKey('power_w', SpecExtractor::extract('cpu', 'Ryzen 9 9950X3D2')['specs']);
    }

    public function test_legacy_values_need_a_confirmed_source_and_merge_marks_unattributed_values(): void
    {
        foreach (['cooler' => ['sockets' => ['AM4'], 'max_tdp_w' => 180], 'ram' => ['memory_type' => 'DDR4', 'capacity_gb' => 16, 'modules' => 1]] as $slot => $specs) {
            $product = Product::factory()->create(['category_id' => Category::factory()->create(['builder_slot' => $slot])->id, 'specs' => $specs, 'spec_sources' => null]);
            $this->assertFalse($product->specsVerified());
            $this->assertSame(array_keys($specs), $product->estimatedSpecs());
            foreach (['manual', 'icecat', 'tabla', 'nombre'] as $source) {
                $product->spec_sources = array_fill_keys(array_keys($specs), $source);
                $this->assertTrue($product->specsVerified());
            }
            $product->spec_sources = ['sockets' => ''];
            $product->fill(SpecExtractor::merge($product, SpecExtractor::extract($slot, 'Modelo desconocido')));
            $this->assertFalse($product->specsVerified());
            $this->assertSame('supuesto', $product->spec_sources[array_key_first($specs)]);
            if ($slot === 'cooler') {
                $this->assertSame(['AM4'], $product->specs['sockets']);
                $this->assertSame(95, $product->specs['max_tdp_w']);
            }
            $again = SpecExtractor::merge($product, SpecExtractor::extract($slot, 'Modelo desconocido'));
            $this->assertSame($product->spec_sources, $again['spec_sources']);
        }
    }

    public function test_case_name_declares_size_and_psu_with_negative_precedence(): void
    {
        $cases = [
            ['Gabinete Acteck Kiruna II Negro MATX 500W Acero AC-935685', ['mATX', 'ITX'], true],
            ['Gabinete Acteck Drako GM450 RGB Negro ATX 500W AC-935715', ['ATX', 'mATX', 'ITX'], true],
            ['Gabinete ADATA XPG Invader X Btfmt Blanco ARGB ATX S/fuente Cristal', ['ATX', 'mATX', 'ITX'], false],
            ['Gabinete MICRO ATX SIN FUENTE 600W', ['mATX', 'ITX'], false],
            ['Gabinete MICRO-ATX S/F 500W', ['mATX', 'ITX'], false],
            ['Gabinete M-ATX con fuente', ['mATX', 'ITX'], true],
            ['Gabinete MINI TORRE 500W', ['mATX', 'ITX'], true],
            ['Gabinete MINI-ITX S/FUENTE', ['ITX'], false],
            ['Gabinete ITX S/F', ['ITX'], false],
            ['Gabinete E-ATX sin fuente', ['E-ATX', 'ATX', 'mATX', 'ITX'], false],
        ];
        foreach ($cases as [$name, $sizes, $psu]) {
            $result = SpecExtractor::extract('case', $name);
            $this->assertSame($sizes, $result['specs']['form_factors'], $name);
            $this->assertSame($psu, $result['specs']['includes_psu'], $name);
            $this->assertSame('nombre', $result['sources']['form_factors']);
            $this->assertSame('nombre', $result['sources']['includes_psu']);
        }
    }

    public function test_cooler_explicit_sockets_and_vendor_fallbacks(): void
    {
        foreach (['AM4', 'AM5', 'LGA1700', 'LGA1851', 'LGA1200', 'LGA1151'] as $socket) {
            $result = SpecExtractor::extract('cooler', 'Disipador genérico '.$socket);
            $this->assertSame([$socket], $result['specs']['sockets']);
            $this->assertSame('nombre', $result['sources']['sockets']);
        }
        foreach (['Socket AMD' => ['AM4', 'AM5'], 'AMD' => ['AM4', 'AM5'], 'Intel' => ['LGA1700', 'LGA1851', 'LGA1200']] as $name => $sockets) {
            $result = SpecExtractor::extract('cooler', 'Disipador '.$name);
            $this->assertSame($sockets, $result['specs']['sockets']);
            $this->assertSame('supuesto', $result['sources']['sockets']);
        }
        foreach (['AK620' => 260, 'AG620' => 260, 'AK400' => 220, 'AG400' => 220, 'AS500' => 220] as $model => $tdp) {
            $result = SpecExtractor::extract('cooler', 'Disipador Deepcool '.$model);
            $this->assertSame($tdp, $result['specs']['max_tdp_w']);
            $this->assertSame('tabla', $result['sources']['max_tdp_w']);
            $this->assertSame('tabla', $result['sources']['sockets']);
        }
        foreach (['Deepcool LE240 V2', 'Corsair NAUTILUS 360', 'MSI CORELIQUID A13 360MM', 'Cooler Master Master Liquid 240 Core II', 'Deepcool AK620 G2'] as $name) {
            $result = SpecExtractor::extract('cooler', $name);
            $this->assertSame(95, $result['specs']['max_tdp_w'], $name);
            $this->assertSame('supuesto', $result['sources']['max_tdp_w']);
            $this->assertSame('tabla', $result['sources']['sockets']);
        }
        $this->assertArrayNotHasKey('sockets', SpecExtractor::extract('cooler', 'Deepcool AK6200')['specs']);
    }

    public function test_psu_connectors_are_critical_and_distinguish_revisions(): void
    {
        foreach ([
            ['ASUS ROG Strix 850W Gold Aura ROG-STRIX-850G-AURA-GAMING ATX 3.0', 3, true],
            ['Corsair RM850X 850W CP-9020200-NA/RF', 4, false],
            ['Corsair RM850X 850W CP-9020270-NA', 3, true],
            ['Corsair CX650 650W CP-9020278-NA', 2, false],
            ['Cooler Master 850W MWE V3 MPE-8506-ACAG-BUS', 4, true],
            ['XPG Core Reactor II 1000W', 8, true],
            ['EVGA 750 G6 750W', 4, false],
            ['Gigabyte GP-UD850GM PG5 V2 850W', 4, true],
            ['MSI MAG A850GL 850W PCIE5', 4, true],
            ['ASUS Prime 850W', 3, true],
        ] as [$name, $eight, $sixteen]) {
            $result = SpecExtractor::extract('psu', $name);
            $this->assertSame($eight, $result['specs']['pcie_8pin'], $name);
            $this->assertSame($sixteen, $result['specs']['pcie_16pin'], $name);
            $this->assertSame('tabla', $result['sources']['pcie_8pin']);
            $this->assertSame('tabla', $result['sources']['pcie_16pin']);
        }
        $unknown = SpecExtractor::extract('psu', 'Fuente desconocida 850W');
        $this->assertSame('supuesto', $unknown['sources']['pcie_8pin']);
        $parts = $this->catalog();
        $psu = $parts['psu'];
        $psu->spec_sources = array_diff_key($psu->spec_sources, ['pcie_8pin' => true]);
        $this->assertFalse($psu->specsVerified());
        $this->assertSame(['pcie_8pin'], $psu->estimatedSpecs());
        $result = SpecExtractor::extract('psu', 'ASUS ROG Strix 850W Gold Aura');
        $parts['psu']->update(['specs' => $result['specs'], 'spec_sources' => $result['sources']]);
        $parts['gpu']->update(['specs' => [...$parts['gpu']->specs, 'power_connector' => '8-pin']]);
        $suggested = BuildSuggester::suggest('gaming1080', 'equilibrada');
        $this->assertFalse($suggested['unavailable']);
        $this->assertNotContains('gpu_power_connector', array_column(Compatibility::check(BuildSuggester::specs($suggested['parts'])), 'code'));
    }

    private function catalog(): array
    {
        $fixture = json_decode(file_get_contents(base_path('tests/fixtures/compatibility.json')), true)[0]['parts'];
        $parts = [];
        foreach ($fixture as $slot => $part) {
            $category = Category::factory()->create(['builder_slot' => $slot, 'is_visible' => true]);
            $parts[$slot] = Product::factory()->create(['category_id' => $category->id, 'specs' => $part['specs'], 'spec_sources' => array_fill_keys(array_keys($part['specs']), 'manual'), 'is_published' => true, 'price_mode' => 'show', 'price' => 1000, 'stock' => 2, 'stock_24h' => 0]);
        }

        return $parts;
    }

    public function test_suggestion_steps_down_and_never_uses_insufficient_or_unverified_psu(): void
    {
        $parts = $this->catalog();
        $parts['gpu']->update(['specs' => [...$parts['gpu']->specs, 'tier' => 1]]);
        $strong = Product::factory()->create(['category_id' => $parts['psu']->category_id, 'specs' => [...$parts['psu']->specs, 'watts' => 1000], 'stock' => 0, 'stock_24h' => 0]);
        $result = BuildSuggester::suggest('gaming1080', 'equilibrada');
        $this->assertFalse($result['unavailable']);
        $this->assertTrue($result['stepped']);
        $this->assertSame([], Compatibility::check(BuildSuggester::specs($result['parts'])));
        foreach ($result['parts'] as $part) {
            $this->assertTrue($part->specsVerified());
        }
        $this->assertNotEquals($strong->id, $result['parts']['psu']->id);
        $parts['psu']->update(['specs' => [...$parts['psu']->specs, 'watts' => 300]]);
        $this->assertTrue(BuildSuggester::suggest('gaming1080', 'equilibrada')['unavailable']);
        $parts['psu']->update(['specs' => [...$parts['psu']->specs, 'watts' => 650], 'spec_sources' => ['watts' => 'supuesto']]);
        $this->assertTrue(BuildSuggester::suggest('gaming1080', 'equilibrada')['unavailable']);
        $this->getJson('/arma-tu-pc/sugerido?uso=gaming1080&presupuesto=equilibrada')->assertOk()->assertJsonPath('unavailable', true)->assertJsonPath('parts', []);
        $this->getJson('/arma-tu-pc/sugerido?uso=no&presupuesto=alta')->assertUnprocessable();
    }

    public function test_search_backtracks_to_another_motherboard_and_prefers_dual_channel(): void
    {
        $parts = $this->catalog();
        $parts['motherboard']->update(['price' => 500, 'specs' => [...$parts['motherboard']->specs, 'memory_type' => 'DDR5']]);
        $goodBoard = $parts['motherboard']->replicate(['sku', 'slug']);
        $goodBoard->fill(['sku' => 'GOOD-BOARD', 'slug' => 'good-board', 'price' => 1100, 'specs' => [...$parts['motherboard']->specs, 'memory_type' => 'DDR4']])->save();
        Product::factory()->create(['category_id' => $parts['ram']->category_id, 'specs' => [...$parts['ram']->specs, 'modules' => 1], 'price' => 100, 'is_published' => true, 'price_mode' => 'show', 'stock' => 5]);
        $result = BuildSuggester::suggest('gaming1080', 'equilibrada');
        $this->assertFalse($result['unavailable']);
        $this->assertSame($goodBoard->id, $result['parts']['motherboard']->id);
        $this->assertSame(2, $result['parts']['ram']->specs['modules']);
        $this->getJson('/arma-tu-pc/sugerido?uso=gaming1080&presupuesto=equilibrada')->assertOk()->assertJsonPath('parts.motherboard', $goodBoard->id)->assertJsonMissingPath('cost');
    }

    public function test_field_merge_protects_manual_and_icecat(): void
    {
        $product = new Product(['specs' => ['watts' => 850, 'certification' => '80+ Gold', 'pcie_16pin' => false], 'spec_sources' => ['watts' => 'manual', 'certification' => 'icecat', 'pcie_16pin' => 'supuesto']]);
        $merged = SpecExtractor::merge($product, SpecExtractor::extract('psu', 'Fuente 600W 80 PLUS BRONZE ATX 3.1'));
        $this->assertSame(850, $merged['specs']['watts']);
        $this->assertSame('80+ Gold', $merged['specs']['certification']);
        $this->assertTrue($merged['specs']['pcie_16pin']);
        $this->assertSame('manual', $merged['spec_sources']['watts']);
    }

    public function test_review_unpublishes_problems_without_deleting_or_changing_valid_builds(): void
    {
        $parts = $this->catalog();
        $build = Build::create(['name' => 'Válido', 'slug' => 'valid', 'use' => 'gaming1080', 'is_published' => true]);
        $build->syncParts(array_map(fn ($p) => $p->id, $parts));
        $this->artisan('equipos:revisar')->assertSuccessful();
        $this->assertTrue($build->fresh()->is_published);
        $parts['cooler']->update(['spec_sources' => ['max_tdp_w' => 'supuesto']]);
        $this->artisan('equipos:revisar')->expectsOutputToContain('Ficha pendiente')->assertSuccessful();
        $this->assertFalse($build->fresh()->is_published);
        $this->assertCount(8, $build->fresh()->products);
        $parts['cooler']->update(['spec_sources' => ['max_tdp_w' => 'manual']]);
        $parts['case']->update(['specs' => [...$parts['case']->specs, 'includes_psu' => true]]);
        $build->refresh()->update(['is_published' => true]);
        $this->artisan('equipos:revisar')->expectsOutputToContain('pagas dos fuentes')->assertSuccessful();
        $this->assertFalse($build->fresh()->is_published);
    }

    public function test_publication_rejects_risks_and_unverified_specs_and_sellers_see_levels(): void
    {
        $parts = $this->catalog();
        $user = User::factory()->create(['role' => 'seller']);
        $payload = ['name' => 'Prueba', 'use' => 'gaming1080', 'is_published' => true, 'parts' => array_map(fn ($p) => $p->id, $parts)];
        $parts['motherboard']->update(['specs' => [...$parts['motherboard']->specs, 'chipset' => 'B450']]);
        $this->actingAs($user)->post('/admin/builds', $payload)->assertSessionHasErrors('parts');
        $this->getJson('/admin/builds/check?'.http_build_query(['parts' => $payload['parts']]))->assertJsonPath('issues.0.level', 'riesgo')->assertJsonPath('issues.0.code', 'chipset_cpu')->assertJsonMissingPath('margin');
        $parts['motherboard']->update(['specs' => [...$parts['motherboard']->specs, 'chipset' => 'B550'], 'spec_sources' => ['ram_slots' => 'supuesto']]);
        $this->post('/admin/builds', $payload)->assertSessionHasErrors('parts');
        $parts['motherboard']->update(['spec_sources' => array_fill_keys(array_keys($parts['motherboard']->specs), 'manual')]);
        $this->post('/admin/builds', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('builds', ['name' => 'Prueba', 'is_published' => true]);
    }

    public function test_admin_only_marks_changed_fields_manual_and_can_confirm(): void
    {
        $parts = $this->catalog();
        $p = $parts['ram'];
        $specs = [...$p->specs, 'speed_mhz' => 3200];
        $p->update(['specs' => $specs, 'spec_sources' => array_fill_keys(array_keys($specs), 'nombre')]);
        $payload = [...$p->only(['sku', 'name', 'slug', 'category_id', 'cost_currency', 'price_mode', 'stock', 'stock_24h']), 'specs' => [...$specs, 'speed_mhz' => 3600]];
        $this->actingAs(User::factory()->create(['role' => 'seller']))->patch('/admin/products/'.$p->id, $payload)->assertSessionHasNoErrors();
        $this->assertSame('nombre', $p->fresh()->spec_sources['modules']);
        $this->assertSame('manual', $p->fresh()->spec_sources['speed_mhz']);
        $this->patch('/admin/products/'.$p->id, [...$payload, 'confirm_specs' => true])->assertSessionHasNoErrors();
        $this->assertSame(['manual'], array_values(array_unique($p->fresh()->spec_sources)));
        $p->update(['spec_sources' => ['modules' => 'supuesto']]);
        $this->get('/admin/products?chip=pending_specs')->assertInertia(fn ($page) => $page->where('counts.pending_specs', 1)->has('products.data', 1));
    }

    public function test_derivation_simulation_does_not_save_or_unpublish_and_actual_run_reviews(): void
    {
        $parts = $this->catalog();
        $p = $parts['psu'];
        $p->update(['name' => 'Fuente 600W 80 PLUS BRONZE ATX 3.1', 'spec_sources' => ['watts' => 'manual', 'certification' => 'nombre']]);
        $this->artisan('catalogo:fichas', ['--slot' => 'psu', '--simular' => true])->assertSuccessful();
        $this->assertSame('80+ Gold', $p->fresh()->specs['certification']);
        $this->artisan('catalogo:fichas', ['--slot' => 'psu'])->assertSuccessful();
        $this->assertSame(650, $p->fresh()->specs['watts']);
        $this->assertSame('80+ Bronze', $p->fresh()->specs['certification']);
        $this->assertStringNotContainsString('--specs', Artisan::all()['productos:importar']->getSynopsis());
    }

    public function test_import_derives_specs_ignores_csv_specs_and_reviews_published_builds(): void
    {
        $parts = $this->catalog();
        $build = Build::create(['name' => 'Sugerido', 'slug' => 'gamer-1080p', 'use' => 'gaming1080', 'is_published' => true]);
        $build->syncParts(array_map(fn ($p) => $p->id, $parts));
        $p = $parts['psu'];
        $p->update(['spec_sources' => ['watts' => 'nombre', 'certification' => 'manual', 'pcie_16pin' => 'manual', 'pcie_8pin' => 'icecat']]);
        $file = tempnam(storage_path('app'), 'r14-test-');
        $handle = fopen($file, 'w');
        $row = ['sku' => $p->sku, 'name' => 'Fuente Naceb Evotec P4600W 600W ATX', 'category' => $p->category->slug, 'brand' => '', 'price' => 1000, 'stock' => 2, 'stock_24h' => 0, 'published' => 1, 'condition' => 'Nuevo', 'warranty_months' => '', 'specs' => '{"watts":4600}'];
        fputcsv($handle, array_keys($row), escape: '\\');
        fputcsv($handle, array_values($row), escape: '\\');
        fclose($handle);
        try {
            $this->artisan('productos:importar', ['archivo' => $file, '--simular' => true])->assertSuccessful();
            $this->assertSame(650, $p->fresh()->specs['watts']);
            $this->artisan('productos:importar', ['archivo' => $file])->assertSuccessful();
            $this->assertSame(600, $p->fresh()->specs['watts']);
            $this->assertSame('80+ Gold', $p->fresh()->specs['certification']);
            $this->assertTrue($p->fresh()->specsVerified());
            $this->assertTrue($build->fresh()->is_published);
            // Sin fuente suficiente se oculta el sugerido, conservando sus piezas.
            $p->update(['stock' => 0, 'stock_24h' => 0]);
            $this->artisan('equipos:sugeridos')->assertSuccessful();
            $this->assertFalse($build->fresh()->is_published);
            $this->assertCount(8, $build->fresh()->products);
        } finally {
            unlink($file);
        }
    }

    public function test_submit_blocks_only_incompatible_and_sends_estimates_in_catalog(): void
    {
        $parts = $this->catalog();
        $parts['cooler']->update(['specs' => [...$parts['cooler']->specs, 'max_tdp_w' => 65], 'spec_sources' => ['max_tdp_w' => 'supuesto']]);
        $this->get('/arma-tu-pc')->assertOk()->assertSee('"estimated"', false)->assertSee('max_tdp_w');
        $payload = ['name' => 'Cliente', 'phone' => '5512345678', 'parts' => array_map(fn ($p) => $p->id, $parts)];
        $this->postJson('/arma-tu-pc/cotizar', $payload)->assertOk();
        $parts['cooler']->update(['spec_sources' => ['max_tdp_w' => 'manual']]);
        $this->postJson('/arma-tu-pc/cotizar', $payload)->assertUnprocessable();
    }
}
