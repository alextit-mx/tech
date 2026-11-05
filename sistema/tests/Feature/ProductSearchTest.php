<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_words_accents_compact_forms_brand_model_category_and_empty_index(): void
    {
        $product = Product::factory()->create(['name' => 'Cámara ASUS RTX-4060', 'model' => 'DUAL.4060', 'sku' => 'CAM-002-A',
            'brand_id' => Brand::factory()->create(['name' => 'ASUS'])->id,
            'category_id' => Category::factory()->create(['name' => 'Vídeo digital'])->id]);
        $this->actingAs(User::factory()->create(['role' => 'seller']));
        foreach ([false, null, ''] as $fallback) {
            if ($fallback !== false) {
                Product::whereKey($product->id)->update(['search_text' => $fallback]);
            }
            foreach (['4060 asus', 'camara', 'rtx4060', 'RTX 4060', 'cam-002', 'dual4060', 'asus dual.4060', 'digital camara'] as $term) {
                $this->getJson('/admin/quotes/products?q='.urlencode($term))->assertOk()->assertJsonPath('0.product_id', $product->id)->assertJsonMissingPath('0.unit_cost');
            }
            $this->getJson('/admin/quotes/products?q=asus%20inexistente')->assertExactJson([]);
            $this->getJson('/admin/quotes/products?q=%25')->assertExactJson([]);
        }
        $this->actingAs(User::factory()->create(['role' => 'owner']))->get('/admin/products?search=4060%20camara')
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.id', $product->id));
    }

    public function test_relevance_precedes_stock_publication_and_name_with_twenty_result_limit(): void
    {
        $make = fn ($attributes) => Product::factory()->create(array_replace(['name' => 'Otra tarjeta', 'stock' => 0, 'stock_24h' => 0, 'is_published' => false], $attributes));
        $other = $make(['model' => 'Otra', 'brand_id' => Brand::factory()->create(['name' => '4060'])->id]);
        $words = $make(['name' => 'Tarjeta gráfica 4060']);
        $name = $make(['name' => '4060 para oficina']);
        $prefix = $make(['sku' => '4060-ABC']);
        $exact = $make(['model' => '4060']);
        $published = $make(['model' => '4060', 'is_published' => true]);
        $stock = $make(['model' => '4060', 'stock_24h' => 2]);
        $this->actingAs(User::factory()->create(['role' => 'seller']));
        $response = $this->getJson('/admin/quotes/products?q=4060')->assertOk();
        $this->assertSame([$stock->id, $published->id, $exact->id, $prefix->id, $name->id, $words->id, $other->id], array_column($response->json(), 'product_id'));
        Product::factory()->count(25)->create(['name' => 'Tarjeta 4060']);
        $this->getJson('/admin/quotes/products?q=4060')->assertJsonCount(20);
    }

    public function test_index_updates_on_save_relationship_changes_import_path_and_command(): void
    {
        $product = Product::factory()->create(['name' => 'Cámara', 'model' => 'RTX-4060']);
        $this->assertStringContainsString('camara', $product->search_text);
        $product->update(['name' => 'Vídeo', 'model' => 'RTX 5070']);
        $this->assertStringContainsString('rtx5070', $product->fresh()->search_text);
        $brand = Brand::factory()->create(['name' => 'Águila']);
        $product->update(['brand_id' => $brand->id]);
        $this->assertStringContainsString('aguila', $product->search_text);
        $brand->update(['name' => 'Cóndor']);
        $this->assertNull($product->fresh()->search_text);
        $this->artisan('catalogo:indexar', ['--simular' => true])->assertSuccessful();
        $this->assertNull($product->fresh()->search_text);
        $this->artisan('catalogo:indexar')->assertSuccessful();
        $this->assertStringContainsString('condor', $product->fresh()->search_text);
        $this->assertSame([$product->id], ProductSearch::apply(Product::query(), 'condor')->pluck('id')->all());
    }

    public function test_product_lookup_requires_portal_access(): void
    {
        $this->getJson('/admin/quotes/products?q=asus')->assertUnauthorized();
    }
}
