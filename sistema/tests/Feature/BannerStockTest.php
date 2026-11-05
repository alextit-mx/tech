<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BannerStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_manage_banners_and_store_shows_only_live_ones(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD sin WebP.');
        }
        $seller = User::factory()->create(['role' => 'seller']);

        $this->actingAs($seller)->post('/admin/banners', ['image' => UploadedFile::fake()->image('promo.jpg', 1200, 400), 'alt' => 'Promo RTX', 'link' => '/tienda'])->assertRedirect();
        $banner = Banner::firstOrFail();
        $this->assertStringEndsWith('.webp', $banner->image);

        Banner::create(['image' => 'banners/x.webp', 'alt' => 'Vencido', 'ends_at' => now()->subDay(), 'is_active' => true]);
        Banner::create(['image' => 'banners/y.webp', 'alt' => 'Apagado', 'is_active' => false]);

        $this->get('/tienda')->assertOk()->assertSee('Promo RTX')->assertDontSee('Vencido')->assertDontSee('Apagado');

        $this->actingAs($seller)->delete("/admin/banners/{$banner->id}")->assertRedirect();
        $this->assertSame(2, Banner::count());
    }

    public function test_stock_labels_follow_center_and_24h(): void
    {
        $this->assertSame('Entrega inmediata', Product::factory()->make(['stock' => 2, 'stock_24h' => 0])->stockLabel());
        $this->assertSame('Llega mañana', Product::factory()->make(['stock' => 0, 'stock_24h' => 5])->stockLabel());
        $this->assertSame('Sobre pedido', Product::factory()->make(['stock' => 0, 'stock_24h' => 0])->stockLabel());
    }

    public function test_store_filters_immediate_and_tomorrow(): void
    {
        $now = Product::factory()->create(['is_published' => true, 'stock' => 3, 'stock_24h' => 0, 'name' => 'Ahora mismo']);
        $tomorrow = Product::factory()->create(['is_published' => true, 'category_id' => $now->category_id, 'stock' => 0, 'stock_24h' => 4, 'name' => 'Para mañana']);
        $slug = $now->category->slug;

        $this->get("/tienda/{$slug}?inmediata=1")->assertSee('Ahora mismo')->assertDontSee('Para mañana');
        $this->get("/tienda/{$slug}?manana=1")->assertSee('Ahora mismo')->assertSee('Para mañana');
        $this->get("/producto/{$tomorrow->slug}")->assertSee('Llega mañana')->assertSee('Foto pendiente');
    }
}
