<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\Hardware\Icecat;
use App\Support\Hardware\SpecExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogCorrectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_disk_rules_prioritize_nvme_then_explicit_ssd_then_hdd_then_sata(): void
    {
        foreach ([
            'DD Interno Toshiba 12TB 3.5 MG09ACA12TE SATA Iii' => 'HDD',
            'SSD Stylos STMSSD1B 120GB 2.5 SATA 3' => 'SATA SSD',
            'SSD Kingston 1TB NVMe PCIe 4.0' => 'NVMe',
            'Disco de estado sólido SATA 3.5"' => 'SATA SSD',
            'Disco 5400 RPM SATA' => 'HDD',
            'Disco SATA 120GB' => 'SATA SSD',
        ] as $name => $expected) {
            $this->assertSame($expected, SpecExtractor::extract('storage', $name)['specs']['type'], $name);
        }
    }

    public function test_icecat_long_title_is_replaced_by_part_and_redo_preserves_manual_models(): void
    {
        Http::preventStrayRequests();
        $cache = Storage::fake('icecat');
        $fixture = json_decode(file_get_contents(base_path('tests/fixtures/icecat/model-long.json')), true);
        $cache->put('fixture.json', json_encode(['data' => $fixture]));
        $this->app->instance(Icecat::class, new Icecat($cache->path('')));
        $old = $fixture['GeneralInfo']['ProductName'];
        $part = $fixture['GeneralInfo']['BrandPartCode'];
        $product = Product::factory()->create(['sku' => '4711085948411', 'model' => $old, 'model_source' => 'icecat']);
        $legacy = Product::factory()->create(['sku' => 'OB4711085948411', 'model' => $old]);
        Product::whereKey($legacy->id)->update(['model_source' => null]);
        $manual = Product::factory()->create(['sku' => 'RE4711085948411', 'model' => $old, 'model_source' => 'manual']);
        $this->artisan('catalogo:modelos', ['--rehacer' => true, '--simular' => true])->assertSuccessful();
        $this->assertSame($old, $product->fresh()->model);
        $this->artisan('catalogo:modelos', ['--rehacer' => true])->assertSuccessful();
        $this->assertSame($part, $product->fresh()->model);
        $this->assertSame($part, $legacy->fresh()->model);
        $this->assertSame('icecat', $product->fresh()->model_source);
        $this->assertStringContainsString('gv-r9060xtgaming', $product->fresh()->search_text);
        $this->assertSame($old, $manual->fresh()->model);
        $product->update(['model' => 'Modelo capturado']);
        $this->artisan('catalogo:modelos', ['--rehacer' => true])->assertSuccessful();
        $this->assertSame('Modelo capturado', $product->fresh()->model);
        Http::assertNothingSent();
    }
}
