<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalAppearancePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_pages_render_in_both_themes_with_optional_isolated_browser_fixtures(): void
    {
        $user = User::factory()->create(['role' => 'owner', 'name' => 'Vendedor de prueba']);
        $brand = Brand::factory()->create(['name' => 'ASUS']);
        $category = Category::factory()->create(['name' => 'Tarjetas de video', 'builder_slot' => 'gpu']);
        $product = Product::factory()->create(['brand_id' => $brand->id, 'category_id' => $category->id,
            'name' => 'Tarjeta de video ASUS Dual RTX 4060 8GB', 'model' => 'DUAL-RTX4060-8G', 'sku' => 'ASUS-4060-8G', 'price' => 6499, 'stock' => 3, 'stock_24h' => 5]);
        Customer::create(['name' => 'Ana Pérez', 'kind' => 'registrado', 'company' => 'Estudio de diseño', 'email' => 'ana@example.test', 'phone' => '5555555555']);
        $pages = ['quotes' => '/admin/quotes', 'quote-editor' => '/admin/quotes/create', 'products' => '/admin/products', 'product-editor' => '/admin/products/'.$product->id.'/edit',
            'customers' => '/admin/customers', 'pending-specs' => '/admin/fichas', 'builds' => '/admin/builds', 'settings' => '/admin/settings',
            'service-orders' => '/admin/service', 'warranties' => '/admin/warranties', 'service-plans' => '/admin/plans', 'banners' => '/admin/banners', 'users' => '/admin/users'];
        $output = getenv('R20_CAPTURE_DIR');
        if ($output && ! is_dir($output)) {
            mkdir($output, 0777, true);
        }
        foreach (['light', 'dark'] as $appearance) {
            $user->forceFill(compact('appearance'))->save();
            foreach ($pages as $name => $url) {
                $response = $this->actingAs($user)->get($url)->assertOk()->assertSee('data-appearance="'.$appearance.'"', false);
                if ($output) {
                    file_put_contents($output.'/'.$name.'-'.$appearance.'.html', $response->getContent());
                }
            }
        }
        if ($output) {
            file_put_contents($output.'/search.json', $this->getJson('/admin/quotes/products?q=4060')->assertOk()->getContent());
        }
    }
}
