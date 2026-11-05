<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontPolishTest extends TestCase
{
    use RefreshDatabase;

    private function product(Category $category, array $attributes = [], bool $photo = true): Product
    {
        $product = Product::factory()->create(array_merge([
            'category_id' => $category->id, 'is_published' => true, 'stock' => 2,
            'stock_24h' => 0, 'is_featured' => false, 'name' => 'Pieza de prueba',
        ], $attributes));
        if ($photo) {
            $product->images()->create(['path' => 'products/'.$product->id.'.jpg', 'sort' => 0]);
        }

        return $product;
    }

    public function test_home_rails_diversify_before_limiting_and_do_not_repeat_products(): void
    {
        $storage = Category::factory()->create(['builder_slot' => 'storage']);
        for ($i = 0; $i < 125; $i++) {
            $this->product($storage, ['is_featured' => true, 'stock' => 100]);
        }
        foreach (['gpu', 'cpu', 'motherboard', 'ram', 'case', 'psu', 'cooler'] as $slot) {
            $category = Category::factory()->create(['builder_slot' => $slot]);
            $this->product($category);
            $this->product($category, ['stock' => 0, 'stock_24h' => 2]);
        }
        $response = $this->get('/')->assertOk();
        $immediate = $response->viewData('immediate');
        $featured = $response->viewData('featured');
        $this->assertCount(8, $immediate);
        $this->assertCount(8, $featured);
        foreach ([$immediate, $featured] as $rail) {
            $this->assertCount($rail->count(), $rail->unique('category_id'));
            $this->assertTrue($rail->every(fn ($p) => $p->imageUrl() && ($p->stock > 0 || $p->stock_24h > 0)));
        }
        $this->assertEmpty(array_intersect($immediate->modelKeys(), $featured->modelKeys()));
        $this->assertTrue($immediate->every(fn ($p) => $p->stock > 0));
    }

    public function test_departments_show_eight_specific_categories_with_real_cover_and_counts(): void
    {
        $root = Category::factory()->create(['name' => 'Componentes']);
        $covers = [];
        foreach (['gpu', 'cpu', 'motherboard', 'ram', 'storage', 'case', 'psu', 'cooler'] as $slot) {
            $category = Category::factory()->create(['builder_slot' => $slot, 'parent_id' => $root->id]);
            $covers[$category->name] = $this->product($category)->imageUrl();
            $this->product($category, [], false);
            $this->product($category, ['is_published' => false]);
        }
        $this->product(Category::factory()->create(['is_visible' => false]));
        $this->get('/')->assertOk()->assertViewHas('departmentTiles', fn ($tiles) => $tiles->count() === 8
            && $tiles->every(fn ($tile) => $tile['count'] === 2 && $tile['image'] === $covers[$tile['name']]));
    }

    public function test_recommended_category_and_search_sort_photo_before_stock_across_pages(): void
    {
        $category = Category::factory()->create();
        $noPhoto = $this->product($category, ['stock' => 999, 'is_featured' => true, 'price' => 1], false);
        $noPhoto->images()->create(['path' => '', 'sort' => 0]);
        $nextDay = $this->product($category, ['stock' => 0, 'stock_24h' => 5, 'price' => 20]);
        $local = $this->product($category, ['stock' => 5, 'price' => 30]);
        for ($i = 0; $i < 23; $i++) {
            $this->product($category, ['stock' => 1, 'price' => 40]);
        }
        foreach ([route('shop.category', $category).'?', '/buscar?q=Pieza&'] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertEquals($local->id, $response->viewData('products')->first()->id);
            $this->assertNotContains($noPhoto->id, $response->viewData('products')->modelKeys());
            $this->get($url.'page=2')->assertOk()->assertViewHas('products', fn ($products) => $products->modelKeys() === [$nextDay->id, $noPhoto->id]);
            $this->get($url.'orden=precio')->assertOk()->assertViewHas('products', fn ($products) => $products->first()->id === $noPhoto->id);
            $this->get($url.'orden=precio-desc&page=2')->assertOk()->assertViewHas('products', fn ($products) => $products->modelKeys() === [$nextDay->id, $noPhoto->id]);
        }
    }

    public function test_product_schema_matches_price_currency_and_actual_availability(): void
    {
        $category = Category::factory()->create(['name' => 'Memoria RAM', 'description' => 'Memoria para tu equipo gamer.']);
        foreach ([[2, 0, 'InStock'], [0, 3, 'InStock'], [0, 0, 'OutOfStock']] as [$stock, $nextDay, $availability]) {
            $product = $this->product($category, ['price_mode' => 'show', 'price' => 1234.56, 'stock' => $stock, 'stock_24h' => $nextDay], false);
            $this->get(route('shop.product', $product))->assertOk()->assertSee('Foto pendiente')
                ->assertSee('"@type":"Product"', false)->assertSee('"priceCurrency":"MXN"', false)
                ->assertSee('"price":"1234.56"', false)->assertSee('"availability":"https://schema.org/'.$availability.'"', false);
        }
        $cover = $this->product($category, ['stock' => 10]);
        $this->get(route('shop.category', $category))->assertOk()
            ->assertSee('<meta property="og:image" content="'.$cover->imageUrl().'"', false)
            ->assertSee('<title>Memoria RAM | Tienda Tech Computer</title>', false)
            ->assertSee('content="Memoria para tu equipo gamer."', false)
            ->assertSee('<link rel="canonical" href="'.route('shop.category', $category).'"', false);
    }
}
