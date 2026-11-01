<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render(): void
    {
        Product::factory()->create(['is_published' => true, 'is_featured' => true]);

        foreach (['/', '/tienda', '/servicio-tecnico', '/asesoria', '/contacto', '/arma-tu-pc', '/carrito', '/buscar?q=sku'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin');
    }

    public function test_category_lists_only_published_products(): void
    {
        $category = Category::factory()->create(['is_visible' => true]);
        $visible = Product::factory()->create(['category_id' => $category->id, 'is_published' => true, 'name' => 'Visible uno']);
        $hidden = Product::factory()->create(['category_id' => $category->id, 'is_published' => false, 'name' => 'Oculto dos']);

        $this->get("/tienda/{$category->slug}")->assertOk()->assertSee('Visible uno')->assertDontSee('Oculto dos');
        $this->get("/producto/{$visible->slug}")->assertOk()->assertSee('application/ld+json', false);
        $this->get("/producto/{$hidden->slug}")->assertNotFound();
    }

    public function test_quote_mode_price_is_never_published(): void
    {
        $product = Product::factory()->create(['is_published' => true, 'price' => 12345.67, 'price_mode' => 'quote']);

        $this->get("/producto/{$product->slug}")->assertOk()->assertSee('Cotizar')->assertDontSee('12,345')->assertDontSee('12345.67');
        $this->getJson("/carrito/productos?ids={$product->id}")->assertJsonPath('0.price', null);
    }

    public function test_cart_submission_creates_web_quote_with_database_prices(): void
    {
        $product = Product::factory()->create(['is_published' => true, 'price' => 2500]);

        $response = $this->post('/cotizar', [
            'name' => 'Luis',
            'phone' => '55 1234 5678',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 1]],
        ]);

        $quote = Quote::with('items')->firstOrFail();
        $response->assertRedirect("/cotizacion/{$quote->public_token}");
        $this->assertSame('web', $quote->source);
        $this->assertSame('5000.00', $quote->total);
        $this->assertSame('5512345678', $quote->customer->phone);
    }

    public function test_cart_submission_validates_and_blocks_bots(): void
    {
        $product = Product::factory()->create(['is_published' => true]);

        $this->post('/cotizar', ['name' => 'Luis', 'phone' => '123', 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertSessionHasErrors('phone');
        $this->post('/cotizar', ['name' => 'Bot', 'phone' => '5512345678', 'website' => 'spam', 'items' => [['product_id' => $product->id, 'quantity' => 1]]])->assertRedirect('/carrito');
        $this->assertSame(0, Quote::count());
    }

    public function test_sitemap_lists_published_products(): void
    {
        $published = Product::factory()->create(['is_published' => true]);
        $hidden = Product::factory()->create(['is_published' => false]);

        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')
            ->assertSee(route('shop.product', $published))->assertDontSee(route('shop.product', $hidden));
    }
}
