<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\Hardware\Icecat;
use App\Support\Hardware\IcecatSpecs;
use App\Support\Hardware\SpecExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class IcecatTest extends TestCase
{
    use RefreshDatabase;

    private Icecat $icecat;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.icecat.username' => 'cuenta-de-pruebas']);
        $this->icecat = new Icecat(Storage::fake('icecat')->path(''));
        $this->app->instance(Icecat::class, $this->icecat);
        Storage::fake('uploads');
        Sleep::fake();
        Http::preventStrayRequests();
    }

    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(base_path('tests/fixtures/icecat/'.$name.'.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    private function product(string $slot = 'ram', array $attributes = []): Product
    {
        return Product::factory()->create([
            'sku' => 'OB4711085948410', 'name' => 'RAM XPG DDR5 16GB AX5U6000C4816G-SLABRWH',
            'category_id' => Category::factory()->create(['builder_slot' => $slot])->id,
            'brand_id' => Brand::firstOrCreate(['name' => 'XPG'], ['slug' => 'xpg'])->id,
            'specs' => [], 'spec_sources' => [], ...$attributes,
        ]);
    }

    private function fakeFixture(string $fixture = 'ram-2576'): void
    {
        Http::fake(['live.icecat.biz/api/*' => Http::response(['data' => $this->fixture($fixture)])]);
    }

    public function test_enrichment_fills_only_empty_models_from_icecat_product_name(): void
    {
        $fixture = $this->fixture('ram-2576');
        $fixture['GeneralInfo']['ProductName'] = 'LANCER BLADE RGB';
        Http::fake(['live.icecat.biz/api/*' => Http::response(['data' => $fixture])]);
        $product = $this->product();
        $this->artisan('catalogo:icecat', ['--slot' => ['ram']])->assertSuccessful();
        $this->assertSame('LANCER BLADE RGB', $product->fresh()->model);
        $product->update(['model' => 'Modelo escrito por el personal']);
        $this->artisan('catalogo:icecat', ['--slot' => ['ram']])->assertSuccessful();
        $this->assertSame('Modelo escrito por el personal', $product->fresh()->model);
    }

    public function test_motherboard_extractor_recognizes_ddr_after_x(): void
    {
        foreach (['Mother AMD A520M-K ASUS Prime AM4 2XDDR4 64GB M-ATX 90MB1500-M0EAY0' => ['DDR4', 2], 'Mother AM5 4XDDR5' => ['DDR5', 4]] as $name => [$type, $slots]) {
            $derived = SpecExtractor::extract('motherboard', $name);
            $this->assertSame($type, $derived['specs']['memory_type']);
            $this->assertSame($slots, $derived['specs']['ram_slots']);
            $this->assertSame('nombre', $derived['sources']['memory_type']);
        }
    }

    public function test_real_fixtures_map_each_available_slot(): void
    {
        $cases = [
            ['storage', 'storage-nvme', ['type' => 'NVMe', 'capacity_gb' => 1000]],
            ['storage', 'storage-sata', ['type' => 'SATA SSD', 'capacity_gb' => 500]],
            ['storage', 'storage-hdd', ['type' => 'HDD', 'capacity_gb' => 2000]],
            ['motherboard', 'motherboard-1947', ['socket' => 'AM4', 'chipset' => 'A520', 'ram_slots' => 4, 'max_ram_gb' => 128, 'memory_type' => 'DDR4', 'form_factor' => 'mATX', 'm2_slots' => 1]],
            ['ram', 'ram-2576', ['capacity_gb' => 16, 'modules' => 1, 'memory_type' => 'DDR5', 'speed_mhz' => 6000]],
            ['ram', 'ram-228', ['capacity_gb' => 16, 'modules' => 2, 'memory_type' => 'DDR4', 'speed_mhz' => 2666]],
            ['gpu', 'gpu-3363', ['vram_gb' => 8, 'length_mm' => 304, 'tdp_w' => 180, 'recommended_psu_w' => 600, 'power_connector' => '16-pin']],
            ['gpu', 'gpu-3382', ['vram_gb' => 8, 'length_mm' => 199, 'tdp_w' => 109, 'recommended_psu_w' => 450, 'power_connector' => '8-pin']],
            ['psu', 'psu-277', ['watts' => 1000, 'certification' => '80+ Gold', 'pcie_8pin' => 6, 'pcie_16pin' => true]],
        ];
        foreach ($cases as [$slot, $fixture, $expected]) {
            $this->assertEquals($expected, IcecatSpecs::map($slot, $this->fixture($fixture)), $fixture);
        }
    }

    private function features(array $features): array
    {
        return ['FeaturesGroups' => [['Features' => collect($features)->map(fn ($value, $name) => ['Feature' => ['Name' => ['Value' => $name]], 'PresentationValue' => $value])->values()->all()]]];
    }

    public function test_storage_interface_normalization(): void
    {
        foreach (['NVMe' => 'NVMe', 'PCIe 4.0' => 'NVMe', 'M.2' => 'NVMe', 'SATA' => 'SATA SSD', 'HDD' => 'HDD'] as $value => $expected) {
            $this->assertSame(['type' => $expected], IcecatSpecs::map('storage', $this->features(['Interfaz' => $value])));
        }
        $this->assertSame([], IcecatSpecs::map('storage', $this->features(['Interfaz' => 'Desconocida'])));
    }

    public function test_case_cooler_and_normalizers_with_synthetic_features(): void
    {
        // No hubo muestras de gabinetes ni disipadores en el piloto.
        $case = $this->features(['Máxima longitud de la tarjeta de gráficos' => '360 mm', 'Fuente de alimentación incluida' => 'No', 'Formas de factor de tarjeta madre soportadas' => 'ATX, micro ATX, Mini-ITX']);
        $this->assertSame(['max_gpu_length_mm' => 360, 'includes_psu' => false, 'form_factors' => ['ATX', 'mATX', 'ITX']], IcecatSpecs::map('case', $case));
        $cooler = $this->features(['Potencia de diseño térmico (TDP)' => '250 W', 'Sockets de procesador soportados' => 'Zócalo AM4, AM5, LGA 1700, LGA1851']);
        $this->assertSame(['max_tdp_w' => 250, 'sockets' => ['AM4', 'AM5', 'LGA1700', 'LGA1851']], IcecatSpecs::map('cooler', $cooler));
        foreach (['2x 8-pin' => '2x8-pin', '3x 8-pin' => '3x8-pin', '1x 16-pin' => '16-pin', 'ninguno' => 'ninguno'] as $input => $expected) {
            $this->assertSame(['power_connector' => $expected], IcecatSpecs::map('gpu', $this->features(['Conectores de energia suplementario' => $input])));
        }
        $this->assertSame(['length_mm' => 268], IcecatSpecs::map('gpu', $this->features(['Longitud' => '268,3 mm'])));
        $this->assertSame(['pcie_8pin' => 3, 'pcie_16pin' => false], IcecatSpecs::map('psu', $this->features(['Conectores de poder PCI Express (8 pin)' => '3', 'Conectores de alimentación PCI Express (16 pines)' => '0'])));
        $this->assertSame([], IcecatSpecs::map('psu', []));
        $this->assertSame([], IcecatSpecs::map('motherboard', $this->features(['tipos de memoria compatibles' => 'DDR4-SDRAM, DDR5-SDRAM'])));
    }

    public function test_prefixed_gtin_request_and_cached_second_run_make_no_http(): void
    {
        foreach (['OB00123456789012' => '00123456789012', 'REM12345678' => '12345678', '1234567890123' => '1234567890123', 'REM1234567' => null, 'AB12345678CD' => null, '123456789012345' => null] as $sku => $expected) {
            $this->assertSame($expected, Icecat::gtin((string) $sku));
        }
        $p = $this->product();
        $this->fakeFixture();
        $this->artisan('catalogo:icecat')->assertSuccessful();
        Http::assertSent(fn (Request $r) => $r['GTIN'] === '4711085948410' && $r['Language'] === 'es' && $r['UserName'] === 'cuenta-de-pruebas');
        $this->assertTrue($p->fresh()->specsVerified());
        $this->assertSame(['icecat'], array_unique(array_values($p->fresh()->spec_sources)));
        $this->app->instance(Icecat::class, new Icecat(Storage::disk('icecat')->path('')));
        $this->artisan('catalogo:icecat')->expectsOutputToContain('Pendientes: 0')->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertCount(1, explode("\n", trim(Storage::disk('icecat')->get('pendientes.csv'))));
        $this->artisan('catalogo:icecat', ['--refrescar' => true])->assertSuccessful();
        Http::assertSentCount(2);
        $this->travel(60)->days();
        $this->icecat->find($p);
        Http::assertSentCount(2);
    }

    public function test_fallback_by_brand_and_last_name_token_and_cached_fallback(): void
    {
        $p = $this->product();
        Http::fake(fn (Request $request) => isset($request['GTIN']) ? Http::response([], 404) : Http::response(['data' => $this->fixture('ram-2576')]));
        $result = $this->icecat->find($p);
        $this->assertSame('part', $result['method']);
        Http::assertSent(fn (Request $r) => isset($r['Brand']) && $r['Brand'] === 'XPG' && $r['ProductCode'] === 'AX5U6000C4816G-SLABRWH' && ! isset($r['GTIN']));
        $this->assertSame($result, $this->icecat->find($p));
        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    }

    public function test_no_valid_gtin_uses_part_directly(): void
    {
        $p = $this->product(attributes: ['sku' => 'LOCAL-PART-01']);
        $this->fakeFixture();
        $this->assertSame('part', $this->icecat->find($p)['method']);
        Http::assertSent(fn (Request $r) => isset($r['Brand'], $r['ProductCode']) && ! isset($r['GTIN']));
        Http::assertSentCount(1);
    }

    public function test_negative_403_and_404_are_cached_and_expire_after_thirty_days(): void
    {
        $products = [$this->product(attributes: ['sku' => 'REM1234567890123']), $this->product(attributes: ['sku' => 'OB9876543210123', 'name' => 'RAM XPG DDR4 8GB OTRO-CODIGO'])];
        Http::fake(fn (Request $r) => Http::response(['msg' => 'Sin acceso o sin ficha'], (($r['GTIN'] ?? null) === '1234567890123' || ($r['ProductCode'] ?? null) === 'AX5U6000C4816G-SLABRWH') ? 403 : 404));
        foreach ($products as $i => $p) {
            $result = $this->icecat->find($p);
            $this->assertNull($result['data']);
            $this->assertSame($i === 0 ? 403 : 404, $result['code']);
            $this->assertSame($result, $this->icecat->find($p));
        }
        Http::assertSentCount(4);
        $this->travel(29)->days();
        foreach ($products as $p) {
            $this->icecat->find($p);
        }
        Http::assertSentCount(4);
        $this->travel(2)->days();
        foreach ($products as $p) {
            $this->icecat->find($p);
        }
        Http::assertSentCount(8);
        $this->icecat->find($products[0], true);
        Http::assertSentCount(10);
        $cached = collect(Storage::disk('icecat')->allFiles())->map(fn ($path) => json_decode(Storage::disk('icecat')->get($path), true));
        $this->assertEqualsCanonicalizing([403, 403, 404, 404], $cached->pluck('code')->all());
    }

    public function test_empty_success_and_connection_failure_are_negative_and_use_fallback(): void
    {
        $p = $this->product();
        Http::fake(['*' => Http::sequence()->push(['data' => []])->push(['statusCode' => 403])->pushFailedConnection()->pushFailedConnection()]);
        $result = $this->icecat->find($p);
        $this->assertSame([['method' => 'gtin', 'code' => 404], ['method' => 'part', 'code' => 403]], $result['attempts']);
        $result = $this->icecat->find($p, true);
        $this->assertNull($result['data']);
        $this->assertSame(0, $result['code']);
    }

    public function test_merge_replaces_name_table_assumed_and_unattributed_but_never_manual(): void
    {
        $p = $this->product('gpu', ['specs' => ['length_mm' => 340, 'tdp_w' => 100, 'power_connector' => '2x8-pin', 'recommended_psu_w' => 400, 'vram_gb' => 8, 'tier' => 2], 'spec_sources' => ['length_mm' => 'manual', 'tdp_w' => 'tabla', 'power_connector' => 'supuesto', 'recommended_psu_w' => 'nombre']]);
        $this->fakeFixture('gpu-3363');
        $this->artisan('catalogo:icecat', ['--slot' => ['gpu']])->assertSuccessful();
        $p->refresh();
        $this->assertSame(340, $p->specs['length_mm']);
        $this->assertSame('manual', $p->spec_sources['length_mm']);
        $this->assertSame(180, $p->specs['tdp_w']);
        $this->assertSame('16-pin', $p->specs['power_connector']);
        $this->assertSame(600, $p->specs['recommended_psu_w']);
        $this->assertSame(8, $p->specs['vram_gb']);
        foreach (['tdp_w', 'power_connector', 'recommended_psu_w', 'vram_gb'] as $field) {
            $this->assertSame('icecat', $p->spec_sources[$field]);
        }
        $this->assertSame(2, $p->specs['tier']);
        $this->assertFalse($p->specsVerified());
    }

    public function test_ram_variant_conflict_is_persisted_keeps_name_capacity_and_is_cleared_only_on_confirmation(): void
    {
        $name = 'RAM ADATA D41 DDR4 8GB 2666 AX4U266638G16-ST41';
        $derived = SpecExtractor::extract('ram', $name);
        $p = $this->product(attributes: ['name' => $name, 'specs' => $derived['specs'], 'spec_sources' => $derived['sources']]);
        $this->fakeFixture('ram-228');
        $this->artisan('catalogo:icecat')->expectsOutputToContain('Pendientes: 1')->assertSuccessful();
        $p->refresh();
        $conflicts = [['field' => 'capacity_gb', 'actual' => 8, 'icecat' => 16]];
        $this->assertSame($conflicts, $p->spec_conflicts);
        $this->assertSame(8, $p->specs['capacity_gb']);
        $this->assertSame(2, $p->specs['modules']);
        $this->assertSame('nombre', $p->spec_sources['capacity_gb']);
        $this->assertFalse($p->specsVerified());
        $this->assertContains('capacity_gb', $p->estimatedSpecs());
        $this->assertStringContainsString('capacity_gb', Storage::disk('icecat')->get('pendientes.csv'));
        $this->artisan('catalogo:icecat')->assertSuccessful();
        $this->assertSame($conflicts, $p->fresh()->spec_conflicts);
        Http::assertSentCount(1);
        $seller = User::factory()->create(['role' => 'seller']);
        $this->actingAs($seller)->get('/admin/products/'.$p->id.'/edit')->assertInertia(fn ($page) => $page->where('product.spec_conflicts', $conflicts));
        $payload = [...$p->only(['sku', 'name', 'slug', 'category_id', 'cost_currency', 'price_mode', 'stock', 'stock_24h', 'specs']), 'spec_conflicts' => []];
        $this->patch('/admin/products/'.$p->id, $payload)->assertSessionHasNoErrors();
        $this->assertSame($conflicts, $p->fresh()->spec_conflicts);
        $this->patch('/admin/products/'.$p->id, [...$payload, 'confirm_specs' => true])->assertSessionHasNoErrors();
        $this->assertSame([], $p->fresh()->spec_conflicts);
        $this->assertTrue($p->fresh()->specsVerified());
        $this->artisan('catalogo:icecat', ['--refrescar' => true])->assertSuccessful();
        $this->assertSame(8, $p->fresh()->specs['capacity_gb']);
        $this->assertSame([], $p->fresh()->spec_conflicts);
    }

    public function test_all_identity_fields_keep_the_name_value_on_conflict(): void
    {
        foreach (['ram' => ['modules' => [1, 2]], 'psu' => ['watts' => [650, 850]], 'gpu' => ['vram_gb' => [8, 16]], 'motherboard' => ['chipset' => ['A520', 'B550'], 'socket' => ['AM4', 'AM5']]] as $slot => $fields) {
            $actual = $official = [];
            foreach ($fields as $field => [$old, $new]) {
                $actual[$field] = $old;
                $official[$field] = $new;
            }
            $p = $this->product($slot, ['sku' => 'TEST-'.$slot, 'specs' => $actual, 'spec_sources' => array_fill_keys(array_keys($actual), 'nombre')]);
            $p->fill(IcecatSpecs::merge($p, $official));
            $this->assertSame($actual, $p->specs);
            $this->assertCount(count($fields), $p->spec_conflicts);
            $this->assertFalse($p->specsVerified());
        }
    }

    public function test_images_use_gallery_originals_in_order_only_for_products_without_photos(): void
    {
        $p = $this->product();
        $existing = $this->product(attributes: ['sku' => '9876543210123']);
        $existing->images()->create(['path' => 'products/manual.jpg', 'sort' => 1]);
        $fixture = $this->fixture('ram-2576');
        $file = UploadedFile::fake()->image('foto.png', 1800, 900);
        $bytes = file_get_contents($file->getRealPath());
        $urls = Icecat::gallery($fixture);
        Http::fake(['live.icecat.biz/api/*' => Http::response(['data' => $fixture]), $urls[0] => Http::response($bytes), $urls[1] => Http::response($bytes)]);
        $this->artisan('catalogo:icecat', ['--imagenes' => true])->assertSuccessful();
        $images = $p->images()->get();
        $this->assertCount(2, $images);
        $this->assertSame([1, 2], $images->pluck('sort')->all());
        foreach ($images as $image) {
            Storage::disk('uploads')->assertExists($image->path);
            $this->assertSame([1600, 800], array_slice(getimagesizefromstring(Storage::disk('uploads')->get($image->path)), 0, 2));
        }
        $this->assertSame(['products/manual.jpg'], $existing->images()->pluck('path')->all());
        Http::assertSentCount(4);
        $this->artisan('catalogo:icecat', ['--imagenes' => true])->assertSuccessful();
        Http::assertSentCount(4);
        $this->assertCount(2, $p->images()->get());
    }

    public function test_images_skip_timeouts_invalid_content_and_http_errors_and_stop_after_six(): void
    {
        $p = $this->product();
        $fixture = $this->fixture('ram-2576');
        $fixture['Gallery'] = array_map(fn ($i) => ['Pic' => 'https://images.icecat.biz/'.$i.'.png'], range(0, 10));
        $file = UploadedFile::fake()->image('foto.png');
        $bytes = file_get_contents($file->getRealPath());
        Http::fake([
            'live.icecat.biz/api/*' => Http::response(['data' => $fixture]),
            'https://images.icecat.biz/0.png' => Http::failedConnection(),
            'https://images.icecat.biz/1.png' => Http::response('no es imagen'),
            'https://images.icecat.biz/2.png' => Http::response('', 404),
            'https://images.icecat.biz/*' => Http::response($bytes),
        ]);
        $this->artisan('catalogo:icecat', ['--imagenes' => true])->assertSuccessful();
        $this->assertCount(6, $p->images()->get());
        $this->assertSame([1, 2, 3, 4, 5, 6], $p->images()->pluck('sort')->all());
        Http::assertSentCount(10);
        Http::assertNotSent(fn (Request $r) => in_array($r->url(), ['https://images.icecat.biz/9.png', 'https://images.icecat.biz/10.png'], true));
    }

    public function test_simulation_does_not_save_or_download_images_or_review_and_csv_previews_missing_fields(): void
    {
        $p = $this->product('gpu');
        $this->fakeFixture('gpu-3363');
        Artisan::command('equipos:revisar', function () {
            throw new \RuntimeException('No debe revisar equipos durante la simulación.');
        });
        $this->artisan('catalogo:icecat', ['--simular' => true, '--imagenes' => true])->assertSuccessful();
        $this->assertSame([], $p->fresh()->specs);
        $this->assertDatabaseCount('product_images', 0);
        Http::assertSentCount(1);
        $this->assertStringContainsString('tier', Storage::disk('icecat')->get('pendientes.csv'));
        $this->assertStringNotContainsString('length_mm', Storage::disk('icecat')->get('pendientes.csv'));
    }

    public function test_command_filters_slots_limit_reports_missing_codes_and_calls_review(): void
    {
        $this->product();
        $this->product(attributes: ['sku' => '9876543210123']);
        $this->product('psu', ['sku' => '1111111111111']);
        Http::fake(['*' => Http::response([], 403)]);
        $reviewed = false;
        Artisan::command('equipos:revisar', function () use (&$reviewed) {
            $reviewed = true;
        });
        $this->artisan('catalogo:icecat', ['--slot' => ['ram'], '--limit' => 1])->expectsOutputToContain('403')->expectsOutputToContain('XPG')->assertSuccessful();
        Http::assertSentCount(2);
        $this->assertTrue($reviewed);
        $rows = array_map('str_getcsv', explode("\n", trim(Storage::disk('icecat')->get('pendientes.csv'))));
        $this->assertCount(3, $rows);
        $this->assertSame(['ram', 'ram'], [$rows[1][3], $rows[2][3]]);
    }

    public function test_missing_username_invalid_options_and_missing_identifiers_do_not_call_http(): void
    {
        $p = $this->product(attributes: ['sku' => 'LOCAL', 'brand_id' => null]);
        $this->assertNull($this->icecat->find($p)['data']);
        $this->artisan('catalogo:icecat', ['--slot' => ['invalid']])->assertFailed();
        $this->artisan('catalogo:icecat', ['--limit' => 0])->assertFailed();
        config(['services.icecat.username' => '']);
        $this->artisan('catalogo:icecat')->expectsOutputToContain('ICECAT_USERNAME')->assertSuccessful();
        Http::assertNothingSent();
        $this->assertSame([], Storage::disk('icecat')->allFiles());
    }
}
