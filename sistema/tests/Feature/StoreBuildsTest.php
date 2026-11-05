<?php

namespace Tests\Feature;

use App\Models\Build;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\BuildSuggester;
use App\Support\Hardware\Compatibility;
use App\Support\WebpImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Fotos y listado de productos en admin, equipos armados y la portada de la tienda. */
class StoreBuildsTest extends TestCase
{
    use RefreshDatabase;

    private const SPECS = [
        'cpu' => ['series' => 'Ryzen 7000', 'power_w' => 162, 'socket' => 'AM5', 'cores' => 8, 'threads' => 16, 'tdp_w' => 120, 'igpu' => false, 'tier' => 3],
        'motherboard' => ['chipset' => 'B850', 'ram_slots' => 4, 'socket' => 'AM5', 'memory_type' => 'DDR5', 'form_factor' => 'ATX', 'm2_slots' => 2],
        'ram' => ['memory_type' => 'DDR5', 'capacity_gb' => 32, 'modules' => 2, 'speed_mhz' => 6000],
        'gpu' => ['recommended_psu_w' => 650, 'power_connector' => '8-pin', 'tdp_w' => 250, 'vram_gb' => 12, 'length_mm' => 300, 'tier' => 3],
        'storage' => ['type' => 'NVMe', 'capacity_gb' => 1000],
        'psu' => ['pcie_16pin' => true, 'pcie_8pin' => 2, 'watts' => 750, 'certification' => '80+ Gold'],
        'cooler' => ['type' => 'aire', 'sockets' => ['AM5', 'AM4'], 'max_tdp_w' => 200],
        'case' => ['includes_psu' => false, 'form_factors' => ['ATX', 'mATX'], 'max_gpu_length_mm' => 360],
    ];

    protected function tearDown(): void
    {
        WebpImage::$webp = null;
        parent::tearDown();
    }

    /** @return array<string, Product> */
    private function parts(array $overrides = []): array
    {
        $parts = [];
        foreach (self::SPECS as $slot => $specs) {
            $category = Category::firstOrCreate(['slug' => 'slot-'.$slot], ['name' => Str::title($slot), 'builder_slot' => $slot, 'is_visible' => true]);
            $parts[$slot] = Product::factory()->create(['category_id' => $category->id, 'is_published' => true, 'price_mode' => 'show', 'price' => 1000, 'stock' => 2, 'stock_24h' => 0, 'specs' => array_merge($specs, $overrides[$slot] ?? []), 'spec_sources' => array_fill_keys(array_keys(array_merge($specs, $overrides[$slot] ?? [])), 'manual')]);
        }

        return $parts;
    }

    private function build(array $parts, bool $published = true): Build
    {
        $build = Build::create(['name' => 'Gamer prueba', 'slug' => 'gamer-prueba', 'use' => 'gaming1440', 'is_published' => $published]);
        $build->syncParts(array_map(fn (Product $p) => $p->id, $parts));

        return $build->load('products');
    }

    public function test_photo_upload_works_without_webp_and_rejects_non_images(): void
    {
        Storage::fake('uploads');
        WebpImage::$webp = false;
        $seller = User::factory()->create(['role' => 'seller']);
        $product = Product::factory()->create();

        $this->actingAs($seller)->postJson("/admin/products/{$product->id}/images", ['images' => [UploadedFile::fake()->image('foto.jpg', 2400, 1200)]])
            ->assertOk()->assertJsonCount(1, 'images');
        $path = $product->images()->first()->path;
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertLessThanOrEqual(1600, getimagesizefromstring(Storage::disk('uploads')->get($path))[0]);

        $this->actingAs($seller)->postJson("/admin/products/{$product->id}/images", ['images' => [UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')]])
            ->assertUnprocessable();
    }

    public function test_product_list_chips_quick_edit_and_bulk_update(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        $withPhoto = Product::factory()->create(['is_published' => true, 'cost' => 100, 'price' => 200]);
        $withPhoto->images()->create(['path' => 'products/x.webp', 'sort' => 0]);
        $noPhoto = Product::factory()->create(['is_published' => false]);

        $this->actingAs($seller)->get('/admin/products?chip=no_photo')->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/Products')->has('products.data', 1)->where('products.data.0.id', $noPhoto->id)->where('counts.no_photo', 1)->missing('products.data.0.margin'));

        $this->actingAs($seller)->patchJson("/admin/products/{$withPhoto->id}/quick", ['price' => 250, 'stock_24h' => 3])
            ->assertOk()->assertJsonPath('product.stock_24h', 3)->assertJsonMissingPath('product.margin');
        $this->assertSame(1, $withPhoto->priceChanges()->count());

        $this->actingAs($seller)->post('/admin/products/bulk-update', ['product_ids' => [$noPhoto->id], 'action' => 'publish'])->assertRedirect();
        $this->assertTrue($noPhoto->fresh()->is_published);
    }

    public function test_product_list_downloads_as_excel_with_the_same_filters(): void
    {
        $seller = User::factory()->create(['role' => 'seller']);
        Product::factory()->create(['name' => 'Mouse gamer <RGB> & más', 'sku' => 'MS-1', 'price' => 499, 'is_published' => true]);
        Product::factory()->create(['name' => 'Teclado oculto', 'is_published' => false]);

        $sheet = function (string $url, User $user) {
            $file = $this->actingAs($user)->get($url)->assertOk()->assertDownload()->baseResponse->getFile()->getPathname();
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($file));

            return $zip->getFromName('xl/worksheets/sheet1.xml');
        };

        $hidden = $sheet('/admin/products/export?chip=hidden', $seller);
        $this->assertStringContainsString('Teclado oculto', $hidden);
        $this->assertStringNotContainsString('Mouse gamer', $hidden);
        $this->assertStringNotContainsString('Margen', $hidden);

        $all = $sheet('/admin/products/export?search=mouse', User::factory()->create(['role' => 'owner']));
        $this->assertStringContainsString('Mouse gamer &lt;RGB&gt; &amp; más', $all);
        $this->assertStringContainsString('Margen', $all);
        $this->assertStringContainsString('<v>499</v>', $all);
    }

    public function test_header_shows_login_or_panel_at_the_end_without_mode_switch(): void
    {
        $this->get('/tienda')->assertOk()->assertSee('Entrar')->assertSee('href="/login"', false)->assertDontSee('data-mode-toggle', false);
        $this->actingAs(User::factory()->create(['role' => 'seller']))->get('/tienda')->assertOk()->assertSee('Mi panel')->assertSee('account-link is-in', false);
    }

    public function test_build_total_availability_and_publish_rules(): void
    {
        $parts = $this->parts();
        $parts['psu']->update(['stock' => 0, 'stock_24h' => 4]);
        $build = $this->build($parts);

        $this->assertSame(8000.0, $build->total());
        $this->assertSame(['low', 'Llega mañana'], $build->availability());

        $parts['ram']->update(['price_mode' => 'quote']);
        $this->assertNull($build->fresh()->load('products')->total());

        $owner = User::factory()->create(['role' => 'owner']);
        $bad = $this->parts(['ram' => ['memory_type' => 'DDR4']]);
        $this->actingAs($owner)->post('/admin/builds', ['name' => 'Mala', 'use' => 'gaming1080', 'is_published' => true, 'parts' => array_map(fn ($p) => $p->id, $bad)])
            ->assertSessionHasErrors('parts');
        $this->actingAs($owner)->post('/admin/builds', ['name' => 'Borrador', 'use' => 'gaming1080', 'is_published' => false, 'parts' => array_map(fn ($p) => $p->id, $bad)])
            ->assertRedirect();
        $this->assertDatabaseHas('builds', ['name' => 'Borrador', 'is_published' => false]);

        $this->actingAs($owner)->getJson('/admin/builds/check?'.http_build_query(['parts' => array_map(fn ($p) => $p->id, $bad)]))
            ->assertOk()->assertJsonCount(1, 'errors');
        $this->assertEquals(8000, $this->actingAs($owner)->getJson('/admin/builds/check?'.http_build_query(['parts' => array_map(fn ($p) => $p->id, $bad)]))->json('total'));
    }

    public function test_admin_build_pages_render_and_import_from_builder_link(): void
    {
        $parts = $this->parts();
        $seller = User::factory()->create(['role' => 'seller']);
        $ids = implode('.', array_map(fn ($p) => $p->id, $parts));

        $this->actingAs($seller)->get("/admin/builds/create?b={$ids}&n=Mi%20PC")->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/BuildForm')->where('prefill.name', 'Mi PC')->has('parts.cpu')->has('parts.case')->where('canMargins', false));
        $build = $this->build($parts);
        $this->actingAs($seller)->get('/admin/builds')->assertOk()->assertInertia(fn ($page) => $page->component('admin/Builds')->has('builds', 1));
        $this->actingAs($seller)->get("/admin/builds/{$build->id}/edit")->assertOk()->assertInertia(fn ($page) => $page->component('admin/BuildForm')->has('parts', 8));
        $this->actingAs($seller)->getJson("/admin/builds/parts?slot=motherboard&current[cpu]={$parts['cpu']->id}")->assertOk()->assertJsonPath('items.0.issue', null);
        $this->actingAs($seller)->get("/admin/products/{$parts['cpu']->id}/edit")->assertOk()->assertInertia(fn ($page) => $page->component('admin/ProductForm'));
    }

    public function test_store_shows_builds_rails_and_build_page(): void
    {
        $build = $this->build($this->parts());
        $hidden = Build::create(['name' => 'Oculto', 'slug' => 'oculto', 'use' => 'office']);

        $this->get('/tienda')->assertOk()->assertSee('Equipos armados')->assertSee('Gamer prueba')->assertSee('data-add-build', false);
        $this->get('/tienda')->assertOk()->assertSee('Gamer prueba'); // Segunda vez, desde la caché.
        $this->get("/equipos/{$build->slug}")->assertOk()->assertSee('Personalízalo en Arma tu PC')->assertSee('arma-tu-pc?b=', false)->assertSee('"@type":"Product"', false);
        $this->get("/equipos/{$hidden->slug}")->assertNotFound();
        $this->get('/sitemap.xml')->assertSee('/equipos/gamer-prueba');
    }

    public function test_product_page_suggests_compatible_parts_and_builds(): void
    {
        $parts = $this->parts();
        $this->build($parts);
        $otherBoard = Product::factory()->create(['category_id' => $parts['motherboard']->category_id, 'is_published' => true, 'stock' => 1, 'name' => 'Tarjeta Intel ajena', 'specs' => ['socket' => 'LGA1700', 'memory_type' => 'DDR5', 'form_factor' => 'ATX']]);

        $this->get("/producto/{$parts['cpu']->slug}")->assertOk()
            ->assertSee('Completa tu equipo')->assertSee($parts['motherboard']->name)->assertDontSee($otherBoard->name)
            ->assertSee('Equipos que la incluyen');
    }

    public function test_product_gallery_has_slider_zoom_and_store_shows_more_categories(): void
    {
        $product = Product::factory()->create(['is_published' => true, 'stock' => 3, 'price' => 500]);
        foreach ([0, 1, 2] as $i) {
            $product->images()->create(['path' => "products/f{$i}.webp", 'sort' => $i]);
        }
        Product::factory()->count(3)->create(['is_published' => true, 'stock' => 2, 'category_id' => $product->category_id]);

        $html = $this->get("/producto/{$product->slug}")->assertOk()->assertSee('data-gallery-stage', false)->assertSee('data-lightbox', false)->getContent();
        $this->assertSame(3, substr_count($html, 'class="g-slide'));
        $this->assertSame(3, substr_count($html, 'data-gallery-go='));

        $this->get('/tienda')->assertOk()->assertSee('id="cat-'.$product->category->slug.'"', false);
    }

    public function test_search_suggestions_return_json(): void
    {
        Product::factory()->create(['name' => 'Tarjeta de video RTX 5070 Ti', 'is_published' => true]);
        Product::factory()->create(['name' => 'RTX oculta', 'is_published' => false]);

        $this->getJson('/buscar/sugerencias?q=rtx')->assertOk()->assertJsonCount(1, 'products')->assertJsonPath('products.0.name', 'Tarjeta de video RTX 5070 Ti');
        $this->getJson('/buscar/sugerencias?q=r')->assertOk()->assertJsonCount(0, 'products');
    }

    public function test_suggest_command_creates_compatible_published_builds(): void
    {
        $this->parts();

        $this->artisan('equipos:sugeridos')->assertSuccessful();

        $build = Build::where('slug', 'gamer-1440p')->firstOrFail()->load('products');
        $this->assertTrue($build->is_published);
        $this->assertCount(8, $build->products);
        $this->assertSame([], $build->errors());
    }

    public function test_simulation_with_hundreds_of_unverified_products_per_slot_is_read_only_and_fast(): void
    {
        $parts = $this->parts();
        $existing = $this->build($parts);
        $existing->update(['slug' => 'gamer-1440p']);
        $before = $existing->fresh()->getAttributes();
        foreach ($parts as $slot => $part) {
            $part->update(['specs' => [], 'spec_sources' => []]);
            $rows = [];
            for ($i = 0; $i < 200; $i++) {
                $row = $part->getAttributes();
                unset($row['id']);
                $row['sku'] = $row['slug'] = "pending-{$slot}-{$i}";
                $rows[] = $row;
            }
            foreach (array_chunk($rows, 25) as $chunk) {
                DB::table('products')->insert($chunk);
            }
        }
        DB::enableQueryLog();
        $started = hrtime(true);
        $this->artisan('equipos:sugeridos --simular')
            ->expectsOutputToContain('Gamer 1080p: calculando')
            ->expectsOutputToContain('Oficina y escuela: Este armado no está disponible')
            ->assertSuccessful();
        $this->assertLessThan(10, (hrtime(true) - $started) / 1e9);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(1, array_filter($queries, fn ($q) => str_contains($q['query'], 'from "products"')));
        $this->assertCount(0, array_filter($queries, fn ($q) => preg_match('/^(insert|update|delete)/i', $q['query'])));
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertDatabaseCount('builds', 1);
        $this->assertDatabaseCount('build_product', 8);
    }

    public function test_simulation_of_valid_builds_does_not_create_or_change_existing_builds(): void
    {
        $parts = $this->parts();
        $existing = $this->build($parts);
        $existing->update(['slug' => 'gamer-1440p']);
        $before = $existing->fresh()->getAttributes();
        $this->artisan('equipos:sugeridos --simular')
            ->expectsOutputToContain('✓ Gamer 1440p: $8,000 (')
            ->assertSuccessful();
        $this->assertSame($before, $existing->fresh()->getAttributes());
        $this->assertDatabaseCount('builds', 1);
        $this->assertDatabaseCount('build_product', 8);
    }

    public function test_search_limits_bound_rejected_candidates_and_are_shared_with_the_endpoint(): void
    {
        $parts = $this->parts(['case' => ['includes_psu' => true], 'storage' => ['capacity_gb' => 4000]]);
        foreach ($parts as $slot => $part) {
            for ($i = 0; $i < 7; $i++) {
                $copy = $part->replicate(['sku', 'slug']);
                $copy->fill(['sku' => "limit-{$slot}-{$i}", 'slug' => "limit-{$slot}-{$i}"])->save();
            }
        }
        $started = hrtime(true);
        $result = BuildSuggester::suggest('gaming1440', 'equilibrada');
        $this->assertTrue($result['unavailable']);
        $this->assertSame([], $result['parts']);
        $this->assertStringContainsString('límite de combinaciones evaluadas (20000)', $result['message']);
        $this->assertLessThan(6, (hrtime(true) - $started) / 1e9);
        $this->getJson('/arma-tu-pc/sugerido?uso=gaming1440&presupuesto=equilibrada')
            ->assertOk()->assertJsonPath('unavailable', true)
            ->assertJsonPath('message', $result['message']);

        $timed = (new BuildSuggester(maxSeconds: 0))->forUse('office', 'equilibrada');
        $this->assertTrue($timed['unavailable']);
        $this->assertStringContainsString('límite de tiempo', $timed['message']);
    }

    public function test_evaluation_budget_is_not_reset_when_stepping_down(): void
    {
        $this->parts(['case' => ['includes_psu' => true]]);
        // Cada escalón agota siete evaluaciones; el tercero debe detenerse en la evaluación 15.
        $result = (new BuildSuggester(maxEvaluations: 15))->forUse('gaming1440', 'equilibrada');
        $this->assertTrue($result['unavailable']);
        $this->assertStringContainsString('combinaciones evaluadas (15)', $result['message']);
    }

    public function test_all_uses_and_budgets_can_build_with_a_small_valid_catalog(): void
    {
        $this->parts(['cpu' => ['tier' => 5, 'igpu' => true], 'gpu' => ['tier' => 6], 'ram' => ['capacity_gb' => 64], 'storage' => ['capacity_gb' => 4000]]);
        $suggester = new BuildSuggester;
        foreach (['gaming1080', 'gaming1440', 'gaming4k', 'stream', 'create', 'office'] as $use) {
            foreach (['esencial', 'equilibrada', 'alta', 'extrema'] as $budget) {
                $result = $suggester->forUse($use, $budget);
                $this->assertFalse($result['unavailable'], "{$use}/{$budget}");
                $this->assertSame([], Compatibility::check(BuildSuggester::specs($result['parts'])));
            }
        }
    }
}
