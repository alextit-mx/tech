<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontMotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_starts_with_live_banners_before_any_catalog_section(): void
    {
        Banner::create(['image' => 'banners/live.webp', 'image_mobile' => 'banners/mobile.webp', 'alt' => 'Promoción vigente', 'link' => '/tienda#equipos', 'is_active' => true]);
        Banner::create(['image' => 'banners/second.webp', 'alt' => 'Segunda promoción', 'is_active' => true]);
        Banner::create(['image' => 'banners/expired.webp', 'alt' => 'Promoción vencida', 'is_active' => true, 'ends_at' => now()->subDay()]);

        $response = $this->get('/tienda')->assertOk()
            ->assertSee('<body class="has-hero-banners">', false)
            ->assertSeeInOrder(['<main class="page shop shop-home">', '<section class="banners"', '<section class="catalog-head">', 'store-trust'], false)
            ->assertSee('data-banners data-autoplay-interval="4500"', false)
            ->assertSee('data-carousel-progress', false)->assertSee('data-carousel-pause', false)
            ->assertSee('data-banners-prev', false)->assertSee('data-banners-next', false)
            ->assertSee('<source media="(max-width: 640px)" srcset="'.asset('uploads/banners/mobile.webp').'"', false)
            ->assertSee('<h2>Promoción vigente</h2>', false)
            ->assertSee('href="/tienda#equipos"', false)
            ->assertSee('Ver promoción')
            ->assertDontSee('Promoción vencida');

        $this->assertMatchesRegularExpression('/<main class="page shop shop-home">\s*<section class="banners"/', $response->getContent());
    }

    public function test_rails_and_gallery_expose_the_shared_autoplay_controls(): void
    {
        $product = Product::factory()->create(['is_published' => true, 'stock' => 3]);
        $product->images()->createMany([
            ['path' => 'products/front.webp', 'sort' => 0],
            ['path' => 'products/back.webp', 'sort' => 1],
        ]);

        $this->get('/')->assertOk()
            ->assertSee('data-rail data-autoplay-interval="4500"', false)
            ->assertSee('data-carousel-progress', false)->assertSee('data-carousel-pause', false)
            ->assertSee('data-rail-prev', false)->assertSee('data-rail-next', false);
        $this->get(route('shop.product', $product))->assertOk()
            ->assertSee('data-gallery data-autoplay-interval="4500"', false)
            ->assertSee('data-carousel-progress', false)->assertSee('data-carousel-pause', false);
    }

    public function test_store_without_live_banners_starts_with_the_catalog_heading(): void
    {
        Banner::create(['image' => 'banners/hidden.webp', 'alt' => 'Promoción oculta', 'is_active' => false]);
        Banner::create(['image' => 'banners/expired.webp', 'alt' => 'Promoción vencida', 'is_active' => true, 'ends_at' => now()->subDay()]);

        $response = $this->get('/tienda')->assertOk()->assertDontSee('data-banners', false)
            ->assertDontSee('has-hero-banners')->assertDontSee('banner-copy');
        $this->assertMatchesRegularExpression('/<main class="page shop shop-home">\s*<section class="catalog-head">/', $response->getContent());
    }

    public function test_single_banner_has_no_carousel_controls_or_mobile_source_without_an_image(): void
    {
        Banner::create(['image' => 'banners/only.webp', 'alt' => 'Una promoción', 'is_active' => true]);

        $this->get('/tienda')->assertOk()->assertSee('has-hero-banners')
            ->assertSee('<h2>Una promoción</h2>', false)
            ->assertDontSee('<source media="(max-width: 640px)"', false)
            ->assertSee('href="#q-shop"', false)->assertSee('Explorar catálogo')
            ->assertDontSee('Ver promoción')->assertDontSee('data-banners-next', false)
            ->assertDontSee('data-banners-dot', false)->assertDontSee('data-carousel-pause', false);
        $this->get('/')->assertOk()->assertDontSee('has-hero-banners')->assertDontSee('banner-copy');
        $this->get('/buscar')->assertOk()->assertDontSee('has-hero-banners')->assertDontSee('banner-copy');
    }
}
