<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function home(): View
    {
        return view('site.pages.home', [
            'featured' => $this->published()->where('is_featured', true)->latest()->limit(8)->get(),
            'departments' => $this->departments(),
        ]);
    }

    public function index(): View
    {
        return view('site.shop.index', [
            'departments' => $this->departments(),
            'featured' => $this->published()->where('is_featured', true)->latest()->limit(8)->get(),
        ]);
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_visible, 404);
        $category->load('parent', 'children');
        $ids = $category->children->pluck('id')->push($category->id);

        $base = $this->published()->whereIn('category_id', $ids);
        $products = $this->filter(clone $base, $request)->paginate(24)->withQueryString();

        return view('site.shop.category', [
            'category' => $category,
            'products' => $products,
            'brands' => Brand::whereIn('id', (clone $base)->select('brand_id'))->orderBy('name')->get(),
            'counts' => (clone $base)->selectRaw('category_id, count(*) as total')->groupBy('category_id')->pluck('total', 'category_id'),
        ]);
    }

    public function product(Product $product): View
    {
        abort_unless($product->is_published, 404);
        $product->load('images', 'brand', 'category.parent');

        return view('site.shop.product', [
            'product' => $product,
            'related' => $this->published()->where('category_id', $product->category_id)->whereKeyNot($product->id)->inRandomOrder()->limit(4)->get(),
        ]);
    }

    public function search(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $products = $term === '' ? null : $this->filter($this->published()->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
            ->orWhere('sku', 'like', "%{$term}%")
            ->orWhere('short_description', 'like', "%{$term}%")
            ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$term}%"))), $request)->paginate(24)->withQueryString();

        return view('site.shop.search', ['term' => $term, 'products' => $products]);
    }

    /** @return Builder<Product> */
    private function published(): Builder
    {
        return Product::with('images', 'brand:id,name,slug', 'category:id,name,slug')
            ->where('is_published', true)
            ->whereHas('category', fn ($q) => $q->where('is_visible', true));
    }

    private function filter(Builder $query, Request $request): Builder
    {
        $sub = $request->query('sub');
        $query->when($sub, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $sub)))
            ->when($request->query('marca'), fn ($q, $slug) => $q->whereHas('brand', fn ($b) => $b->where('slug', $slug)))
            ->when($request->boolean('existencia'), fn ($q) => $q->where('stock', '>', 0))
            ->when(is_numeric($request->query('min')), fn ($q) => $q->where('price_mode', 'show')->where('price', '>=', (float) $request->query('min')))
            ->when(is_numeric($request->query('max')), fn ($q) => $q->where('price_mode', 'show')->where('price', '<=', (float) $request->query('max')));

        return match ($request->query('orden')) {
            'precio' => $query->orderByRaw("price_mode = 'quote'")->orderBy('price'),
            'precio-desc' => $query->orderByRaw("price_mode = 'quote'")->orderByDesc('price'),
            'nombre' => $query->orderBy('name'),
            default => $query->orderByDesc('is_featured')->orderByDesc('stock')->latest(),
        };
    }

    private function departments()
    {
        return Category::whereNull('parent_id')->where('is_visible', true)->orderBy('sort')
            ->with(['children' => fn ($q) => $q->where('is_visible', true)->orderBy('sort')])
            ->get();
    }
}
