<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner']);
    }

    private function seller(): User
    {
        return User::factory()->create(['role' => 'seller']);
    }

    private function product(array $attrs = []): Product
    {
        return Product::factory()->create($attrs);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['is_active' => false, 'password' => Hash::make('password')]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_seller_never_receives_margin_and_owner_only_routes_are_forbidden(): void
    {
        $seller = $this->seller();
        $product = $this->product(['cost' => 100, 'price' => 232]);
        $this->actingAs($seller)->get(route('admin.products'))->assertOk()->assertInertia(fn ($page) => $page->where('products.data.0.cost', '100.00')->missing('products.data.0.margin'));
        $this->get(route('admin.users'))->assertForbidden();
        $this->get(route('admin.settings'))->assertForbidden();
        $this->get(route('admin.categories'))->assertForbidden();
        $this->post(route('admin.products.bulk'), ['product_ids' => [$product->id], 'percent' => 5])->assertForbidden();
        $owner = $this->actingAs($this->owner());
        $owner->get(route('admin.users'))->assertOk();
        $owner->get(route('admin.categories'))->assertOk();
        $owner->get(route('admin.settings'))->assertOk();
        $owner->get(route('admin.products'))->assertInertia(fn ($page) => $page->has('products.data.0.margin'));
    }

    public function test_quick_and_bulk_price_updates_are_logged_and_rounded_up_by_ten(): void
    {
        $seller = $this->seller();
        $owner = $this->owner();
        $product = $this->product(['price' => 101.25]);
        $this->actingAs($seller)->patch(route('admin.products.quick', $product), ['price' => 110, 'stock' => 4])->assertRedirect();
        $this->assertDatabaseHas('price_changes', ['product_id' => $product->id, 'old_price' => '101.25', 'new_price' => '110.00']);
        $this->actingAs($owner)->post(route('admin.products.bulk'), ['product_ids' => [$product->id], 'percent' => 5])->assertRedirect();
        $this->assertEquals('120.00', $product->fresh()->price);
        $this->assertDatabaseHas('price_changes', ['product_id' => $product->id, 'old_price' => '110.00', 'new_price' => '120.00']);
    }

    public function test_usd_cost_and_builder_specs_are_validated(): void
    {
        $this->actingAs($this->owner());
        Cache::flush();
        Setting::updateOrCreate(['key' => 'exchange_rate'], ['value' => '18']);
        $category = Category::factory()->create(['builder_slot' => 'cpu']);
        $brand = Brand::factory()->create();
        $response = $this->post(route('admin.products.store'), ['sku' => 'USD-1', 'name' => 'CPU de prueba', 'category_id' => $category->id, 'brand_id' => $brand->id, 'cost' => 10, 'cost_currency' => 'USD', 'price' => 500, 'price_mode' => 'show', 'stock' => 2, 'specs' => ['socket' => 'AM5', 'cores' => 8, 'threads' => 16, 'tdp_w' => 100, 'igpu' => false, 'tier' => 2]]);
        $response->assertRedirect();
        $product = Product::where('sku', 'USD-1')->firstOrFail();
        $this->assertEquals(180, $product->costInMxn());
        $this->assertDatabaseHas('price_changes', ['product_id' => $product->id, 'new_price' => '500.00']);
        $this->post(route('admin.products.store'), ['sku' => 'CPU-X', 'name' => 'Inválido', 'category_id' => $category->id, 'cost_currency' => 'MXN', 'price_mode' => 'show', 'stock' => 0, 'specs' => ['tier' => 9]])->assertSessionHasErrors('specs');
    }

    public function test_owner_cannot_deactivate_themselves(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->patch(route('admin.users.update', $owner), ['name' => $owner->name, 'email' => $owner->email, 'role' => 'owner', 'is_active' => false])->assertSessionHasErrors('role');
    }

    public function test_product_image_upload_creates_webp(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP no está disponible.');
        }
        $product = $this->product();
        $this->actingAs($this->owner())->post(route('admin.products.images', $product), ['images' => [UploadedFile::fake()->image('foto.jpg', 20, 20)]])->assertRedirect();
        $image = $product->images()->firstOrFail();
        $this->assertStringEndsWith('.webp', public_path('uploads/'.$image->path));
        $this->assertFileExists(public_path('uploads/'.$image->path));
        @unlink(public_path('uploads/'.$image->path));
    }
}
