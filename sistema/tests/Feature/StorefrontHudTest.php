<?php

namespace Tests\Feature;

use App\Models\Build;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontHudTest extends TestCase
{
    use RefreshDatabase;

    private function piece(array $attributes = [], bool $photo = true): Product
    {
        $product = Product::factory()->create(array_merge([
            'is_published' => true, 'stock' => 2, 'stock_24h' => 0, 'is_featured' => true,
        ], $attributes));
        if ($photo) {
            $product->images()->create(['path' => 'products/'.$product->id.'.jpg', 'sort' => 0]);
        }

        return $product;
    }

    public function test_home_only_promotes_published_pieces_with_photo_and_real_stock(): void
    {
        $local = $this->piece(['name' => 'Pieza local con foto']);
        $nextDay = $this->piece(['name' => 'Pieza disponible en 24 horas', 'stock' => 0, 'stock_24h' => 3]);
        $noPhoto = $this->piece(['name' => 'No tiene fotografía'], false);
        $soldOut = $this->piece(['name' => 'Agotada con foto', 'stock' => 0, 'stock_24h' => 0]);
        $hidden = $this->piece(['name' => 'Pieza privada con foto', 'is_published' => false]);
        $hiddenCategory = $this->piece(['name' => 'Categoría no visible', 'category_id' => Category::factory()->create(['is_visible' => false])->id]);

        $this->get('/')->assertOk()->assertSee($local->name)->assertSee($nextDay->name)
            ->assertDontSee($noPhoto->name)->assertDontSee($soldOut->name)->assertDontSee($hidden->name)->assertDontSee($hiddenCategory->name)
            ->assertViewHas('immediate', fn ($items) => $items->modelKeys() === [$local->id])
            ->assertViewHas('featured', fn ($items) => $items->modelKeys() === [$nextDay->id] && $items->every(fn ($p) => $p->imageUrl() && ($p->stock > 0 || $p->stock_24h > 0)))
            ->assertDontSee('Foto pendiente');
    }

    public function test_fallback_selection_also_requires_photo_and_stock(): void
    {
        $available = $this->piece(['name' => 'Alternativa fotografiada', 'is_featured' => false, 'stock' => 0, 'stock_24h' => 2]);
        $this->piece(['name' => 'Alternativa sin foto', 'is_featured' => false], false);
        $this->piece(['name' => 'Alternativa agotada', 'is_featured' => false, 'stock' => 0]);

        $this->get('/')->assertOk()->assertSee($available->name)->assertDontSee('Alternativa sin foto')->assertDontSee('Alternativa agotada')
            ->assertViewHas('featured', fn ($items) => $items->modelKeys() === [$available->id]);
    }

    public function test_department_cover_uses_available_child_and_counts_published_products_only(): void
    {
        $root = Category::factory()->create(['name' => 'Componentes del equipo']);
        $child = Category::factory()->create(['parent_id' => $root->id]);
        $empty = Category::factory()->create(['name' => 'Sin fotos disponibles']);
        $cover = $this->piece(['category_id' => $child->id]);
        $this->piece(['category_id' => $child->id, 'is_published' => false]);
        $this->piece(['category_id' => $child->id], false);
        $this->piece(['category_id' => $empty->id, 'stock' => 0]);

        $this->get('/')->assertOk()->assertViewHas('departmentTiles', function ($tiles) use ($child, $cover) {
            $tile = $tiles->first();

            return $tiles->count() === 1 && $tile['name'] === $child->name && $tile['count'] === 2 && $tile['image'] === $cover->imageUrl();
        });
    }

    public function test_home_builds_require_a_photo_and_available_published_parts(): void
    {
        $available = $this->piece();
        $noPhoto = $this->piece([], false);
        $unavailable = $this->piece(['stock' => 0, 'stock_24h' => 0]);
        $deleted = $this->piece();
        $deleted->delete();
        foreach (['Equipo disponible' => $available, 'Equipo sin fotos' => $noPhoto, 'Equipo agotado' => $unavailable, 'Equipo con pieza eliminada' => $deleted] as $name => $part) {
            $build = Build::create(['name' => $name, 'slug' => Str::slug($name), 'use' => 'office', 'is_published' => true]);
            $build->products()->attach($part->id, ['slot' => 'cpu', 'quantity' => 1]);
        }

        $this->get('/')->assertOk()->assertSee('Equipo disponible')->assertDontSee('Equipo sin fotos')
            ->assertDontSee('Equipo agotado')->assertDontSee('Equipo con pieza eliminada')->assertDontSee('Foto pendiente')
            ->assertViewHas('builds', fn ($builds) => $builds->count() === 1);
    }

    public function test_product_shows_separate_availability_and_hides_quote_price_and_cost(): void
    {
        $product = $this->piece(['name' => 'Producto por cotizar', 'stock' => 2, 'stock_24h' => 7, 'price_mode' => 'quote', 'price' => 98765.43, 'cost' => 54321.12, 'specs' => ['socket' => 'AM5']]);

        $this->get(route('shop.product', $product))->assertOk()->assertSee('En el local')->assertSee('En 24 h')
            ->assertSee('2 piezas')->assertSee('7 piezas')->assertSee('AM5')->assertSee('scope="row"', false)
            ->assertDontSee('98765.43')->assertDontSee('98,765')->assertDontSee('54321.12');
        $this->get('/')->assertOk()->assertSee('Cotizar')->assertDontSee('98,765')->assertDontSee('54321.12');
    }

    public function test_search_filters_preserve_term_and_pagination_and_do_not_promise_unverified_stock(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(25)->create(['category_id' => $category->id, 'name' => 'Ryzen disponible', 'is_published' => true, 'stock' => 2]);
        $this->piece(['name' => 'Ryzen en 24 horas', 'stock' => 0, 'stock_24h' => 3]);

        $this->get('/buscar?q=Ryzen&inmediata=1&orden=nombre')->assertOk()->assertSee('Filtrar resultados')->assertSee('Limpiar filtros')
            ->assertDontSee('Ryzen en 24 horas')->assertViewHas('products', fn ($p) => $p->total() === 25 && str_contains($p->nextPageUrl(), 'inmediata=1'));
        $this->get('/buscar?q=&inmediata=1')->assertOk()->assertViewHas('products', fn ($p) => $p->total() === 25);
        $this->get('/buscar?q=pieza-inexistente')->assertOk()->assertSee('Sin coincidencias')->assertDontSee('seguro te lo conseguimos');
    }

    public function test_location_uses_configured_address_and_whatsapp(): void
    {
        Setting::updateOrCreate(['key' => 'address'], ['value' => 'República de Uruguay 9, local 60']);
        Setting::updateOrCreate(['key' => 'whatsapp'], ['value' => '525512345678']);

        $this->get('/')->assertOk()->assertSee('República de Uruguay 9, local 60')->assertSee(urlencode('República de Uruguay 9, local 60'), false)
            ->assertSee('https://wa.me/525512345678', false);
    }
}
