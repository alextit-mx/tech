<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Http\Requests\Admin\BulkPricingRequest;
use App\Http\Requests\Admin\CategoryRequest;
use App\Http\Requests\Admin\LogoRequest;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Requests\Admin\SettingsRequest;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceChange;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use App\Support\BuilderSpecs;
use App\Support\PendingSpecs;
use App\Support\ProductSearch;
use App\Support\WebpImage;
use App\Support\Xlsx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function dashboard()
    {
        return Inertia::render('Dashboard');
    }

    /** Filtros rápidos del listado (chips). */
    private const CHIPS = ['pending_specs', 'no_photo', 'no_price', 'no_stock', 'hidden', 'remate', 'featured'];

    public function products(Request $r)
    {
        [$base, $search, $chip, $sort, $dir] = $this->productQuery($r);
        $counts = ['all' => (clone $base)->count()];
        foreach (self::CHIPS as $c) {
            $counts[$c] = $this->chip(clone $base, $c)->count();
        }
        $perPage = in_array((int) $r->per_page, [25, 50, 100], true) ? (int) $r->per_page : 25;
        $q = ($chip ? $this->chip($base, $chip) : $base)
            ->with(['category:id,name', 'brand:id,name', 'images:id,product_id,path,sort'])
            ->orderBy($sort === 'updated' ? 'updated_at' : $sort, $dir)->orderByDesc('id');

        return Inertia::render('admin/Products', [
            'products' => $q->paginate($perPage)->withQueryString()->through(fn (Product $p) => $this->row($p, $r)),
            'counts' => $counts,
            'filters' => ['search' => $search, 'category_id' => $r->category_id ?: '', 'brand_id' => $r->brand_id ?: '', 'chip' => $chip ?? '', 'sort' => $sort, 'dir' => $dir, 'per_page' => $perPage],
            'categories' => Category::orderBy('sort')->get(['id', 'name', 'builder_slot']),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
            'canBulk' => $r->user()->can('bulk-pricing'),
            'canMargins' => $r->user()->can('view-margins'),
        ]);
    }

    /** Búsqueda, categoría, marca, chip y orden del listado; los comparten la tabla y la descarga en Excel. */
    private function productQuery(Request $r): array
    {
        $search = trim((string) $r->search);
        $base = ProductSearch::apply(Product::query(), $search)
            ->when($r->category_id, fn ($x) => $x->where('category_id', $r->category_id))
            ->when($r->brand_id, fn ($x) => $x->where('brand_id', $r->brand_id));
        $chip = in_array($r->chip, self::CHIPS, true) ? $r->chip : null;
        $sort = in_array($r->sort, ['name', 'price', 'stock', 'updated'], true) ? $r->sort : 'updated';

        return [$base, $search, $chip, $sort, $r->dir === 'asc' ? 'asc' : 'desc'];
    }

    /** El listado con los filtros que tenga puestos, todas las páginas, en Excel. El margen solo para quien puede verlo. */
    public function exportProducts(Request $r)
    {
        [$base, , $chip, $sort, $dir] = $this->productQuery($r);
        $margins = $r->user()->can('view-margins');
        $columns = ['SKU' => ['text', 16], 'Producto' => ['text', 64], 'Categoría' => ['text', 26], 'Marca' => ['text', 16],
            'Costo' => ['money', 13], 'Moneda' => ['text', 9], 'Precio con IVA' => ['money', 15], 'Precio en tienda' => ['text', 16],
            'Existencia' => ['int', 11], 'Llega en 24 h' => ['int', 13], 'Publicado' => ['text', 11], 'Destacado' => ['text', 11],
            'Garantía (meses)' => ['int', 16], 'Fotos' => ['int', 8]]
            + ($margins ? ['Margen' => ['percent', 10]] : [])
            + ['Nota' => ['text', 30], 'Enlace en tienda' => ['text', 60], 'Actualizado' => ['text', 17]];
        $rows = ($chip ? $this->chip($base, $chip) : $base)
            ->with(['category:id,name', 'brand:id,name'])->withCount('images')
            ->orderBy($sort === 'updated' ? 'updated_at' : $sort, $dir)->orderByDesc('id')
            ->lazy(500)->map(fn (Product $p) => [
                'SKU' => $p->sku, 'Producto' => $p->name, 'Categoría' => $p->category?->name, 'Marca' => $p->brand?->name,
                'Costo' => $p->cost, 'Moneda' => $p->cost === null ? null : $p->cost_currency, 'Precio con IVA' => $p->price,
                'Precio en tienda' => $p->price_mode === 'quote' ? 'Cotizar' : 'Visible',
                'Existencia' => $p->stock, 'Llega en 24 h' => $p->stock_24h,
                'Publicado' => $p->is_published ? 'Sí' : 'No', 'Destacado' => $p->is_featured ? 'Sí' : 'No',
                'Garantía (meses)' => $p->warranty_months, 'Fotos' => $p->images_count,
                'Margen' => $margins ? $p->margin() : null, 'Nota' => $p->short_description,
                'Enlace en tienda' => url('/producto/'.$p->slug), 'Actualizado' => $p->updated_at?->format('Y-m-d H:i'),
            ]);

        $file = tempnam(sys_get_temp_dir(), 'productos');
        Xlsx::write($file, 'Productos', $columns, $rows);

        return response()->download($file, 'productos-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private function chip($q, string $chip)
    {
        return match ($chip) {
            'no_photo' => $q->whereDoesntHave('images'),
            'no_price' => $q->where(fn ($w) => $w->whereNull('price')->orWhere('price', '<=', 0)->orWhere('price_mode', 'quote')),
            'no_stock' => $q->where('stock', 0)->where('stock_24h', 0),
            'hidden' => $q->where('is_published', false),
            'remate' => $q->where(fn ($w) => $w->where('short_description', 'like', '%remate%')->orWhere('short_description', 'like', '%caja abierta%')->orWhere('short_description', 'like', '%descontinuad%')),
            'featured' => $q->where('is_featured', true),
            'pending_specs' => $q->whereIn('id', Product::with('category')->whereHas('category', fn ($q) => $q->whereNotNull('builder_slot'))->get()->reject(fn (Product $p) => $p->specsVerified())->modelKeys()),
        };
    }

    /** Fila del listado: lo que necesita la tabla y el panel de edición rápida (margen solo para quien puede verlo). */
    private function row(Product $p, Request $r): array
    {
        $cost = $p->costInMxn();
        $data = $p->only(['id', 'sku', 'name', 'slug', 'category_id', 'brand_id', 'short_description', 'cost', 'cost_currency', 'price', 'price_mode', 'stock', 'stock_24h', 'is_published', 'is_featured', 'warranty_months', 'updated_at']);
        $data['category'] = $p->category?->only(['id', 'name']);
        $data['brand'] = $p->brand?->only(['id', 'name']);
        $data['images'] = $p->images->map->only(['id', 'path', 'sort'])->values();
        $data['price_below_cost'] = $cost !== null && $p->price !== null && ((float) $p->price / 1.16) < $cost;
        if ($r->user()->can('view-margins')) {
            $data['margin'] = $p->margin();
        }

        return $data;
    }

    public function productForm(?Product $product = null)
    {
        return Inertia::render('admin/ProductForm', [
            'specSchema' => BuilderSpecs::editorSchema(),
            'product' => $product?->load(['images', 'priceChanges.user:id,name']),
            'categories' => Category::orderBy('sort')->get(['id', 'name', 'builder_slot']),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function saveProduct(ProductRequest $r, ?Product $product = null)
    {
        $data = $r->validated();
        $category = Category::findOrFail($data['category_id']);
        if (! BuilderSpecs::valid($category->builder_slot, $data['specs'] ?? [])) {
            return back()->withErrors(['specs' => 'Las especificaciones no corresponden a esta categoría.']);
        }
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);
        $product ??= new Product;
        $sources = $product->spec_sources ?? [];
        foreach ($data['specs'] ?? [] as $key => $value) {
            if ($r->boolean('confirm_specs') || ! array_key_exists($key, $product->specs ?? []) || ($product->specs[$key] ?? null) !== $value) {
                $sources[$key] = 'manual';
            }
        }
        $data['spec_sources'] = array_intersect_key($sources, $data['specs'] ?? []);
        if ($r->boolean('confirm_specs')) {
            $data['spec_conflicts'] = [];
        }
        unset($data['confirm_specs']);
        DB::transaction(function () use ($product, $data, $r) {
            $oldPrice = $product->price;
            $oldCost = $product->cost;
            $product->fill($data);
            $product->save();
            if ($oldPrice != $product->price || $oldCost != $product->cost) {
                PriceChange::create(['product_id' => $product->id, 'user_id' => $r->user()->id, 'old_price' => $oldPrice, 'new_price' => $product->price, 'old_cost' => $oldCost, 'new_cost' => $product->cost]);
            }
        });

        PendingSpecs::forgetCounts();

        if ($r->input('after') === 'shop' && $product->is_published) {
            return Inertia::location(url('/producto/'.$product->slug));
        }

        return $product->wasRecentlyCreated
            ? redirect()->route('admin.products.edit', ['product' => $product, 'fotos' => 1])
            : back();
    }

    public function bulkUpdate(Request $r)
    {
        $d = $r->validate([
            'product_ids' => 'required|array|min:1', 'product_ids.*' => 'integer|exists:products,id',
            'action' => 'required|in:publish,hide,feature,unfeature,category',
            'category_id' => 'required_if:action,category|nullable|exists:categories,id',
        ]);
        $values = match ($d['action']) {
            'publish' => ['is_published' => true],
            'hide' => ['is_published' => false],
            'feature' => ['is_featured' => true],
            'unfeature' => ['is_featured' => false],
            'category' => ['category_id' => $d['category_id']],
        };
        Product::whereIn('id', $d['product_ids'])->get()->each->update($values);

        return back();
    }

    public function visibility(Request $r)
    {
        $data = $r->validate(['product_ids' => 'required|array', 'product_ids.*' => 'exists:products,id', 'is_published' => 'required|boolean']);
        Product::whereIn('id', $data['product_ids'])->update(['is_published' => $data['is_published']]);

        return back()->with('success', 'Visibilidad actualizada.');
    }

    public function quick(Request $r, Product $product)
    {
        $d = $r->validate([
            'name' => 'sometimes|required|string|max:255', 'category_id' => 'sometimes|required|exists:categories,id',
            'price' => 'sometimes|nullable|numeric|min:0', 'price_mode' => 'sometimes|in:show,quote',
            'cost' => 'sometimes|nullable|numeric|min:0', 'cost_currency' => 'sometimes|in:MXN,USD',
            'stock' => 'sometimes|integer|min:0', 'stock_24h' => 'sometimes|integer|min:0',
            'is_published' => 'sometimes|boolean', 'is_featured' => 'sometimes|boolean',
        ]);
        DB::transaction(function () use ($product, $d, $r) {
            $oldPrice = $product->price;
            $oldCost = $product->cost;
            $product->fill($d)->save();
            if ($oldPrice != $product->price || $oldCost != $product->cost) {
                PriceChange::create(['product_id' => $product->id, 'user_id' => $r->user()->id, 'old_price' => $oldPrice, 'new_price' => $product->price, 'old_cost' => $oldCost, 'new_cost' => $product->cost]);
            }
        });
        if ($r->expectsJson()) {
            return response()->json(['product' => $this->row($product->load(['category:id,name', 'brand:id,name', 'images:id,product_id,path,sort']), $r)]);
        }

        return back()->with('success', 'Cambios guardados.');
    }

    public function bulk(BulkPricingRequest $r)
    {
        Gate::authorize('bulk-pricing');
        $d = $r->validated();
        $categoryIds = [];
        if (! empty($d['category_id'])) {
            $collect = function ($categoryId) use (&$collect, &$categoryIds) {
                $categoryIds[] = $categoryId;
                foreach (Category::where('parent_id', $categoryId)->pluck('id') as $childId) {
                    $collect($childId);
                }
            };
            $collect($d['category_id']);
        }
        $q = Product::query()->when($categoryIds !== [], fn ($x) => $x->whereIn('category_id', $categoryIds), fn ($x) => $x->whereIn('id', $d['product_ids'] ?? []))->whereNotNull('price');
        DB::transaction(function () use ($q, $d, $r) {
            foreach ($q->get() as $p) {
                $old = $p->price;
                $new = ceil(((float) $old * (1 + $d['percent'] / 100)) / 10) * 10;
                $p->update(['price' => $new]);
                PriceChange::create(['product_id' => $p->id, 'user_id' => $r->user()->id, 'old_price' => $old, 'new_price' => $new, 'old_cost' => $p->cost, 'new_cost' => $p->cost]);
            }
        });

        return back()->with('success', 'Precios actualizados.');
    }

    public function categories(Request $r)
    {
        Gate::authorize('manage-categories');

        return Inertia::render('admin/Categories', ['categories' => Category::with('parent:id,name')->orderBy('sort')->get()]);
    }

    public function saveCategory(CategoryRequest $r, ?Category $category = null)
    {
        Gate::authorize('manage-categories');
        $d = $r->validated();
        $d['slug'] = ($d['slug'] ?? null) ?: Str::slug($d['name']);
        ($category ?? new Category)->fill($d)->save();

        return back()->with('success', 'Categoría guardada.');
    }

    public function brands()
    {
        return Inertia::render('admin/Brands', ['brands' => Brand::withCount('products')->orderBy('name')->get()]);
    }

    public function saveBrand(BrandRequest $r, ?Brand $brand = null)
    {
        $d = $r->validated();
        $d['slug'] = ($d['slug'] ?? null) ?: Str::slug($d['name']);
        ($brand ?? new Brand)->fill($d)->save();

        return back()->with('success', 'Marca guardada.');
    }

    public function users()
    {
        Gate::authorize('manage-users');

        return Inertia::render('admin/Users', ['users' => User::query()->select('id', 'name', 'email', 'role', 'is_active')->orderBy('name')->get()]);
    }

    public function saveUser(UserRequest $r, ?User $user = null)
    {
        Gate::authorize('manage-users');
        $d = $r->validated();
        if ($user && $user->id === $r->user()->id && (! $r->boolean('is_active') || $d['role'] !== 'owner')) {
            return back()->withErrors(['role' => 'No puedes desactivarte ni quitarte el rol de dueño.']);
        }if (! empty($d['password'])) {
            $d['password'] = Hash::make($d['password']);
        } else {
            unset($d['password']);
        }($user ?? new User)->fill($d)->save();

        return back()->with('success', 'Usuario guardado.');
    }

    public function settings()
    {
        Gate::authorize('manage-settings');

        return Inertia::render('admin/Settings', ['settings' => Setting::pluck('value', 'key'), 'logos' => Branding::previews()]);
    }

    public function saveSettings(SettingsRequest $r)
    {
        Gate::authorize('manage-settings');
        $data = $r->validated();
        DB::transaction(function () use ($data) {
            foreach ($data as $k => $v) {
                Setting::updateOrCreate(['key' => $k], ['value' => $v]);
            }
        });
        Cache::flush();

        return back()->with('success', 'Configuración guardada.');
    }

    public function uploadLogo(LogoRequest $request, string $type)
    {
        Branding::replace($type, $request->file('image'));

        return back()->with('success', 'Imagen actualizada.');
    }

    public function deleteLogo(Request $request, string $type)
    {
        Gate::authorize('manage-settings');
        Branding::replace($type, null);

        return back()->with('success', 'Se restauró la imagen predeterminada.');
    }

    public function upload(Request $r, Product $product)
    {
        $r->validate(['images' => 'required|array|max:12', 'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:20480'], [
            'images.*.image' => 'Cada archivo debe ser una imagen.',
            'images.*.mimes' => 'Sube fotos JPG, PNG o WebP.',
            'images.*.max' => 'Cada foto puede pesar hasta 20 MB.',
            'images.max' => 'Sube hasta 12 fotos a la vez.',
        ]);
        foreach ($r->file('images') as $file) {
            $path = WebpImage::store($file, 'products');
            $product->images()->create(['path' => $path, 'sort' => (int) $product->images()->max('sort') + 1]);
        }
        if ($r->expectsJson()) {
            return response()->json(['images' => $product->images()->get(['id', 'path', 'sort'])]);
        }

        return back()->with('success', count($r->file('images')) === 1 ? 'Foto cargada.' : 'Fotos cargadas.');
    }

    public function reorderImage(Request $r, ProductImage $image)
    {
        $r->validate(['sort' => 'required|integer|min:0']);
        DB::transaction(function () use ($image, $r) {
            $images = $image->product->images()->orderBy('sort')->orderBy('id')->get()->values();
            $current = $images->search(fn ($candidate) => $candidate->id === $image->id);
            $images->splice($current, 1);
            $images->splice(min($r->integer('sort'), $images->count()), 0, [$image]);
            foreach ($images as $index => $item) {
                $item->update(['sort' => $index]);
            }
        });

        return $r->expectsJson() ? response()->json(['images' => $image->product->images()->get(['id', 'path', 'sort'])]) : back();
    }

    public function deleteImage(Request $r, ProductImage $image)
    {
        $product = $image->product;
        Storage::disk('uploads')->delete($image->path);
        $image->delete();

        return $r->expectsJson() ? response()->json(['images' => $product->images()->get(['id', 'path', 'sort'])]) : back();
    }
}
