<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Build;
use App\Models\Category;
use App\Models\Product;
use App\Support\BuildValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function home(): View
    {
        $immediate = $this->variedProducts($this->showcase()->where('stock', '>', 0));

        return view('site.pages.home', [
            'featured' => $this->featuredProducts($immediate->modelKeys()),
            'immediate' => $immediate,
            'builds' => Build::live()->with('products.images', 'products.brand:id,name')
                ->get()->filter(fn (Build $build) => ($build->imageUrl() || $build->products->contains(fn (Product $p) => $p->imageUrl()))
                    && $build->products->isNotEmpty() && $build->products->every(fn (Product $p) => ! $p->trashed() && $p->is_published && ($p->stock > 0 || $p->stock_24h > 0)))->take(3),
            ...$this->departmentData(),
        ]);
    }

    public function index(): View
    {
        // Las secciones se guardan 5 min ya renderizadas (Laravel no permite guardar modelos en caché);
        // cualquier cambio de producto, foto o equipo las renueva (ver booted() de los modelos).
        $sections = Cache::remember('shop.home', 300, fn () => view('site.shop.home-sections', $this->homeData())->render());

        return view('site.shop.index', ['banners' => Banner::live()->get(), 'sections' => $sections]);
    }

    private function homeData(): array
    {
        $inSlot = fn (string $slot) => Category::where('builder_slot', $slot)->pluck('id');
        $available = fn (Builder $q) => $q->where(fn ($w) => $w->where('stock', '>', 0)->orWhere('stock_24h', '>', 0));
        $rail = fn (Builder $q, int $n = 12) => $q->limit($n)->get();
        $immediate = $this->variedProducts($this->showcase()->where('stock', '>', 0));
        $featured = $this->featuredProducts($immediate->modelKeys());
        $gpu = $inSlot('gpu');
        $cpu = $inSlot('cpu');
        // Más abajo, un carrusel por cada categoría con más productos disponibles (además de video y procesadores).
        $more = Category::where('is_visible', true)->whereNotIn('id', $gpu->merge($cpu))
            ->withCount(['products' => fn ($q) => $available($q->where('is_published', true))])
            ->orderByDesc('products_count')->limit(8)->get()->where('products_count', '>=', 4)
            ->map(fn (Category $c) => ['id' => 'cat-'.$c->slug, 'eyebrow' => 'Categoría', 'title' => $c->name, 'link' => route('shop.category', $c),
                'items' => $rail($this->showcase()->where('category_id', $c->id)->orderByDesc('is_featured')->orderByRaw("price_mode = 'quote'")->orderByDesc('stock')->latest('updated_at'))])->filter(fn ($rail) => $rail['items']->isNotEmpty());

        return [
            'builds' => Build::live()->with('products.images', 'products.brand:id,name')->get(),
            'rails' => [...array_filter([
                ['id' => 'destacados', 'eyebrow' => 'Selección de la tienda', 'title' => 'Destacados', 'link' => null, 'items' => $featured],
                ['id' => 'video', 'eyebrow' => 'Más FPS', 'title' => 'Tarjetas de video', 'link' => $this->categoryUrl($gpu), 'items' => $rail($this->showcase()->whereIn('category_id', $gpu)->orderByDesc('is_featured')->orderByRaw("price_mode = 'quote'")->orderByDesc('price'))],
                ['id' => 'procesadores', 'eyebrow' => 'El cerebro de tu PC', 'title' => 'Procesadores', 'link' => $this->categoryUrl($cpu), 'items' => $rail($this->showcase()->whereIn('category_id', $cpu)->orderByDesc('is_featured')->orderByRaw("price_mode = 'quote'")->orderByDesc('price'))],
                ['id' => 'inmediata', 'eyebrow' => 'En Plaza de la Tecnología', 'title' => 'Entrega inmediata', 'link' => null, 'items' => $immediate],
                ['id' => 'remates', 'eyebrow' => 'Precio especial', 'title' => 'Remates y caja abierta', 'link' => '/buscar?q=remate', 'items' => $rail($this->showcase()->where(fn ($w) => $w->where('short_description', 'like', '%remate%')->orWhere('short_description', 'like', '%caja abierta%'))->orderByDesc('stock'))],
                ['id' => 'novedades', 'eyebrow' => 'Recién llegados', 'title' => 'Novedades', 'link' => null, 'items' => $rail($this->showcase()->latest())],
            ], fn ($r) => $r['items']->isNotEmpty()), ...$more->all()],
            'brands' => Brand::withCount(['products' => fn ($q) => $q->where('is_published', true)->whereHas('category', fn ($c) => $c->where('is_visible', true))])->orderByDesc('products_count')->limit(18)->get()->where('products_count', '>', 0)->sortBy('name')->values(),
            ...$this->departmentData(),
        ];
    }

    /** Selección de portada: fotografía y existencia comprobadas. */
    private function showcase(): Builder
    {
        return $this->published()->whereHas('images', fn ($q) => $q->whereNotNull('path')->where('path', '!=', ''))
            ->where(fn ($q) => $q->where('stock', '>', 0)->orWhere('stock_24h', '>', 0));
    }

    /** Un representante por categoría; el límite se aplica después de diversificar. */
    private function variedProducts(Builder $query): Collection
    {
        $ids = (clone $query)->withoutEagerLoads()->orderByDesc('is_featured')->orderByDesc('stock')
            ->orderByDesc('stock_24h')->latest('updated_at')->orderBy('products.id')
            ->get(['products.id', 'category_id'])->unique('category_id')->take(8)->modelKeys();

        return $query->whereKey($ids)->get()->sortBy(fn (Product $p) => array_search($p->id, $ids))->values();
    }

    private function featuredProducts(array $exclude = []): Collection
    {
        return $this->variedProducts($this->showcase()->whereKeyNot($exclude));
    }

    private function departmentData(): array
    {
        $categories = Category::where('is_visible', true)->orderBy('sort')->orderBy('id')->get();
        $counts = $this->published()->reorder()->selectRaw('category_id, count(*) as total')->groupBy('category_id')->pluck('total', 'category_id');
        $photoIds = $this->showcase()->reorder()->selectRaw('MIN(products.id)')->groupBy('category_id');
        $photos = Product::with('images')->whereIn('id', $photoIds)->get()->keyBy('category_id');
        $priority = ['gpu', 'cpu', 'motherboard', 'ram', 'storage', 'case'];
        $tiles = $categories->filter(fn (Category $c) => isset($photos[$c->id]))
            ->sortBy(function (Category $c) use ($priority) {
                $rank = array_search($c->builder_slot, $priority, true);

                return $rank !== false ? $rank : (Str::contains(Str::lower($c->name), ['monitor', 'periférico', 'mouse', 'teclado', 'pc armada']) ? 6 : 7);
            })->take(8)->map(function (Category $c) use ($categories, $counts, $photos) {
                $ids = $categories->where('parent_id', $c->id)->pluck('id')->push($c->id);

                return [
                    'name' => $c->name, 'url' => route('shop.category', $c), 'image' => $photos[$c->id]->imageUrl(), 'image_alt' => $photos[$c->id]->name,
                    'count' => $ids->sum(fn ($id) => $counts[$id] ?? 0),
                ];
            })->values();

        return ['departments' => $this->departments(), 'departmentTiles' => $tiles];
    }

    /** Equipo armado: piezas, precio total y enlace para personalizarlo en el configurador. */
    public function build(Request $request, Build $build): View
    {
        abort_unless($build->is_published || $request->user(), 404);
        $build->load('products.images', 'products.brand:id,name', 'products.category:id,name,slug');

        return view('site.shop.build', ['build' => $build, 'others' => Build::live()->whereKeyNot($build->id)->with('products.images')->limit(3)->get()]);
    }

    /** Sugerencias del buscador mientras se escribe (JSON). */
    public function suggest(Request $request): JsonResponse
    {
        $term = trim(Str::limit((string) $request->query('q'), 60, ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['products' => [], 'categories' => []]);
        }
        $words = array_slice(preg_split('/\s+/', $term), 0, 5);
        $products = $this->published()
            ->where(fn ($q) => collect($words)->each(fn ($w) => $q->where(fn ($x) => $x->where('name', 'like', "%{$w}%")->orWhere('sku', 'like', "%{$w}%")->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$w}%")))))
            ->orderByRaw('(stock > 0) desc')->orderByDesc('is_featured')->limit(6)->get()
            ->map(fn (Product $p) => [
                'name' => $p->name, 'brand' => $p->brand?->name, 'url' => route('shop.product', $p), 'image' => $p->imageOrPlaceholder(),
                'price' => $p->publicPrice() !== null ? money($p->publicPrice()) : 'Cotizar', 'stock' => $p->stockLevel(), 'stock_label' => $p->stockLabel(),
            ]);
        $categories = Category::where('is_visible', true)->where('name', 'like', "%{$term}%")->limit(3)->get()
            ->map(fn (Category $c) => ['name' => $c->name, 'url' => route('shop.category', $c)]);

        return response()->json(['products' => $products, 'categories' => $categories]);
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
        $price = $product->publicPrice();

        return view('site.shop.product', [
            'product' => $product,
            'complete' => $this->complements($product),
            'builds' => $product->builds()->where('is_published', true)->with('products.images')->orderBy('sort')->limit(3)->get(),
            'related' => $this->published()->where('category_id', $product->category_id)->whereKeyNot($product->id)
                ->when($price, fn ($q) => $q->where('price_mode', 'show')->whereBetween('price', [$price * 0.7, $price * 1.3])->orderByRaw('abs(price - ?)', [$price]), fn ($q) => $q->inRandomOrder())
                ->limit(8)->get(),
        ]);
    }

    /**
     * "Completa tu equipo": piezas compatibles por especificaciones.
     * CPU → tarjetas madre del socket y RAM de la plataforma; tarjeta madre → CPU del socket y RAM del tipo;
     * RAM → tarjetas madre del tipo; GPU → fuentes con watts suficientes.
     */
    private function complements(Product $product): Collection
    {
        $slot = $product->category?->builder_slot;
        $specs = $product->specs ?? [];
        $of = fn (string $s) => $this->published()->whereIn('category_id', Category::where('builder_slot', $s)->pluck('id'))
            ->where(fn ($w) => $w->where('stock', '>', 0)->orWhere('stock_24h', '>', 0))->orderByRaw("price_mode = 'quote'")->orderBy('price')->get();
        $socket = $specs['socket'] ?? null;
        $memory = $slot === 'cpu' ? (BuildValidator::PLATFORM_MEMORY[$socket] ?? []) : array_filter([$specs['memory_type'] ?? null]);

        $picks = match ($slot) {
            'cpu' => [$of('motherboard')->filter(fn ($p) => ($p->specs['socket'] ?? null) === $socket)->take(4), $of('ram')->filter(fn ($p) => in_array($p->specs['memory_type'] ?? null, $memory, true))->take(4)],
            'motherboard' => [$of('cpu')->filter(fn ($p) => ($p->specs['socket'] ?? null) === $socket)->take(4), $of('ram')->filter(fn ($p) => in_array($p->specs['memory_type'] ?? null, $memory, true))->take(4)],
            'ram' => [$of('motherboard')->filter(fn ($p) => in_array($p->specs['memory_type'] ?? null, $memory, true))->take(8)],
            'gpu' => [$of('psu')->filter(fn ($p) => (int) ($p->specs['watts'] ?? 0) >= (int) (ceil(BuildValidator::consumption(['cpu' => ['tdp_w' => 125], 'gpu' => $specs]) * 1.4 / 50) * 50))->take(8)],
            default => [],
        };

        return collect($picks)->flatten(1)->values();
    }

    private function categoryUrl(Collection $ids): ?string
    {
        $category = Category::whereIn('id', $ids)->where('is_visible', true)->orderBy('parent_id')->first();

        return $category ? route('shop.category', $category->parent_id && $category->parent?->is_visible ? $category->parent : $category) : null;
    }

    public function search(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $products = $term === '' && ! $request->hasAny(['inmediata', 'manana', 'orden', 'min', 'max']) ? null : $this->filter($this->published()->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
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
            // Entrega inmediata = en el local; "para mañana" incluye también lo que llega en 24 h.
            ->when($request->boolean('inmediata'), fn ($q) => $q->where('stock', '>', 0))
            ->when($request->boolean('manana'), fn ($q) => $q->where(fn ($w) => $w->where('stock', '>', 0)->orWhere('stock_24h', '>', 0)))
            ->when(is_numeric($request->query('min')), fn ($q) => $q->where('price_mode', 'show')->where('price', '>=', (float) $request->query('min')))
            ->when(is_numeric($request->query('max')), fn ($q) => $q->where('price_mode', 'show')->where('price', '<=', (float) $request->query('max')));

        return match ($request->query('orden')) {
            'precio' => $query->orderByRaw("price_mode = 'quote'")->orderBy('price'),
            'precio-desc' => $query->orderByRaw("price_mode = 'quote'")->orderByDesc('price'),
            'nombre' => $query->orderBy('name'),
            default => $query->withExists(['images as has_photo' => fn ($q) => $q->whereNotNull('path')->where('path', '!=', '')])
                ->orderByDesc('has_photo')->orderByDesc('stock')->orderByDesc('stock_24h')->orderByDesc('is_featured')->latest()->orderBy('products.id'),
        };
    }

    private function departments()
    {
        return Category::whereNull('parent_id')->where('is_visible', true)->orderBy('sort')
            ->with(['children' => fn ($q) => $q->where('is_visible', true)->orderBy('sort')])
            ->get();
    }
}
