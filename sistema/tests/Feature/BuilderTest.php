<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Quote;
use App\Support\BuildValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BuilderTest extends TestCase
{
    use RefreshDatabase;

    private const SPECS = [
        'cpu' => ['series' => 'Ryzen 7000', 'power_w' => 162, 'socket' => 'AM5', 'cores' => 8, 'threads' => 16, 'tdp_w' => 120, 'igpu' => false, 'tier' => 4],
        'motherboard' => ['chipset' => 'B850', 'ram_slots' => 4, 'socket' => 'AM5', 'memory_type' => 'DDR5', 'form_factor' => 'ATX', 'm2_slots' => 2],
        'ram' => ['memory_type' => 'DDR5', 'capacity_gb' => 32, 'modules' => 2, 'speed_mhz' => 6000],
        'gpu' => ['recommended_psu_w' => 650, 'power_connector' => '8-pin', 'tdp_w' => 250, 'vram_gb' => 12, 'length_mm' => 300, 'tier' => 4],
        'storage' => ['type' => 'NVMe', 'capacity_gb' => 1000],
        'psu' => ['pcie_16pin' => true, 'pcie_8pin' => 2, 'watts' => 750, 'certification' => '80+ Gold'],
        'cooler' => ['type' => 'aire', 'sockets' => ['AM5', 'AM4'], 'max_tdp_w' => 200],
        'case' => ['includes_psu' => false, 'form_factors' => ['ATX', 'mATX'], 'max_gpu_length_mm' => 360],
    ];

    /** @return array<string, Product> */
    private function parts(array $overrides = []): array
    {
        $parts = [];
        foreach (self::SPECS as $slot => $specs) {
            $category = Category::firstOrCreate(['slug' => 'slot-'.$slot], ['name' => Str::title($slot), 'builder_slot' => $slot, 'is_visible' => true]);
            $parts[$slot] = Product::factory()->create(['category_id' => $category->id, 'is_published' => true, 'price' => 1000, 'specs' => array_merge($specs, $overrides[$slot] ?? []), 'spec_sources' => array_fill_keys(array_keys(array_merge($specs, $overrides[$slot] ?? [])), 'manual')]);
        }

        return $parts;
    }

    public function test_validator_rules(): void
    {
        $ok = self::SPECS;
        $this->assertSame([], BuildValidator::errors($ok));

        $cases = [
            'socket' => ['cpu' => ['series' => 'Ryzen 7000', 'power_w' => 162, 'socket' => 'LGA1851']],
            'memoria' => ['ram' => ['memory_type' => 'DDR4']],
            'formato' => ['case' => ['includes_psu' => false, 'form_factors' => ['ITX']]],
            'largo GPU' => ['gpu' => ['length_mm' => 400]],
            'socket disipador' => ['cooler' => ['sockets' => ['LGA1700']]],
            'tdp disipador' => ['cooler' => ['max_tdp_w' => 65]],
            'fuente' => ['psu' => ['pcie_16pin' => true, 'pcie_8pin' => 2, 'watts' => 300]],
        ];
        foreach ($cases as $name => $change) {
            $build = $ok;
            foreach ($change as $slot => $specs) {
                $build[$slot] = array_merge($build[$slot], $specs);
            }
            $this->assertNotEmpty(BuildValidator::errors($build), "Debe fallar: {$name}");
        }

        // Sin tarjeta madre, la RAM se valida contra la plataforma del CPU (AM5 = DDR5).
        $platform = ['cpu' => $ok['cpu'], 'ram' => array_merge($ok['ram'], ['memory_type' => 'DDR4'])];
        $this->assertNotEmpty(BuildValidator::errors($platform));
        $platform['ram']['memory_type'] = 'DDR5';
        $this->assertCount(1, BuildValidator::errors($platform));
        $this->assertSame('no_video', BuildValidator::issues($platform)[0]['code']);

        // Faltar piezas no es error: se informa aparte.
        $noGpu = $ok;
        unset($noGpu['gpu'], $noGpu['cooler']);
        $this->assertSame('no_video', BuildValidator::issues($noGpu)[0]['code']);
        $this->assertSame(['tarjeta de video'], BuildValidator::missing($noGpu));
        $noGpu['cpu']['igpu'] = true;
        $this->assertSame([], BuildValidator::missing($noGpu));
    }

    public function test_builder_page_embeds_catalog(): void
    {
        $parts = $this->parts();

        $this->get('/arma-tu-pc')->assertOk()->assertSee('build-catalog', false)->assertSee($parts['gpu']->name);
    }

    public function test_complete_build_creates_builder_quote(): void
    {
        $parts = $this->parts();

        $this->postJson('/arma-tu-pc/cotizar', [
            'name' => 'Gamer',
            'phone' => '5512345678',
            'build_name' => 'La Bestia',
            'parts' => collect($parts)->pluck('id')->all(),
        ])->assertOk()->assertJsonStructure(['folio', 'url', 'pdf', 'whatsapp']);

        $quote = Quote::with('items')->firstOrFail();
        $this->assertSame('builder', $quote->source);
        $this->assertSame('La Bestia', $quote->build_name);
        $this->assertCount(8, $quote->items);
        $this->assertSame('8000.00', $quote->total);
    }

    public function test_incompatible_or_incomplete_build_is_rejected(): void
    {
        $parts = $this->parts(['ram' => ['memory_type' => 'DDR4']]);

        $this->postJson('/arma-tu-pc/cotizar', ['name' => 'Gamer', 'phone' => '5512345678', 'parts' => collect($parts)->pluck('id')->all()])
            ->assertStatus(422)->assertJsonPath('errors.parts.0', 'La memoria DDR4 no entra en una tarjeta DDR5.');

        $this->assertSame(0, Quote::count());
    }

    public function test_partial_build_is_quoted_with_missing_pieces_noted(): void
    {
        $parts = $this->parts();

        $this->postJson('/arma-tu-pc/cotizar', ['name' => 'Gamer', 'phone' => '5512345678', 'parts' => [$parts['cpu']->id, $parts['motherboard']->id, $parts['ram']->id, $parts['gpu']->id]])->assertOk();

        $quote = Quote::with('items')->firstOrFail();
        $this->assertCount(4, $quote->items);
        $this->assertStringContainsString('Sin almacenamiento, fuente de poder, gabinete', $quote->notes);
        $this->postJson('/arma-tu-pc/cotizar', ['name' => 'Gamer', 'phone' => '5512345678', 'parts' => []])->assertStatus(422);
    }

    public function test_honeypot_creates_nothing(): void
    {
        $parts = $this->parts();

        $this->postJson('/arma-tu-pc/cotizar', ['name' => 'Bot', 'phone' => '5512345678', 'website' => 'x', 'parts' => collect($parts)->pluck('id')->all()])->assertStatus(202);
        $this->assertSame(0, Quote::count());
    }
}
