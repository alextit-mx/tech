<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Http\Requests\Admin\BulkPricingRequest;
use App\Http\Requests\Admin\CategoryRequest;
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
use App\Support\BuilderSpecs;
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

    public function products(Request $r)
    {
        $q = Product::with(['category:id,name', 'brand:id,name', 'images:id,product_id,path,sort'])->when($r->search, fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', '%'.$r->search.'%')->orWhere('sku', 'like', '%'.$r->search.'%')))->when($r->category_id, fn ($x) => $x->where('category_id', $r->category_id))->when($r->brand_id, fn ($x) => $x->where('brand_id', $r->brand_id))->when($r->filled('price_mode'), fn ($x) => $x->where('price_mode', $r->price_mode))->when($r->filled('published'), fn ($x) => $x->where('is_published', $r->boolean('published')))->when($r->boolean('out_of_stock'), fn ($x) => $x->where('stock', 0))->latest();
        $products = $q->paginate(25)->through(function (Product $p) use ($r) {
            $data = $p->toArray();
            if ($r->user()->can('view-margins')) {
                $data['margin'] = $p->margin();
            }
            $cost = $p->costInMxn();
            $data['price_below_cost'] = $cost !== null && $p->price !== null && ((float) $p->price / 1.16) < $cost;

            return $data;
        });

        return Inertia::render('admin/Products', ['products' => $products, 'categories' => Category::orderBy('sort')->get(['id', 'name', 'builder_slot']), 'brands' => Brand::orderBy('name')->get(['id', 'name']), 'canBulk' => $r->user()->can('bulk-pricing')]);
    }

    public function productForm(?Product $product = null)
    {
        return Inertia::render('admin/ProductForm', [
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
        DB::transaction(function () use ($product, $data, $r) {
            $oldPrice = $product->price;
            $oldCost = $product->cost;
            $product->fill($data);
            $product->save();
            if ($oldPrice != $product->price || $oldCost != $product->cost) {
                PriceChange::create(['product_id' => $product->id, 'user_id' => $r->user()->id, 'old_price' => $oldPrice, 'new_price' => $product->price, 'old_cost' => $oldCost, 'new_cost' => $product->cost]);
            }
        });

        return redirect()->route('admin.products')->with('success', 'Producto guardado.');
    }

    public function visibility(Request $r)
    {
        $data = $r->validate(['product_ids' => 'required|array', 'product_ids.*' => 'exists:products,id', 'is_published' => 'required|boolean']);
        Product::whereIn('id', $data['product_ids'])->update(['is_published' => $data['is_published']]);

        return back()->with('success', 'Visibilidad actualizada.');
    }

    public function quick(Request $r, Product $product)
    {
        $d = $r->validate(['price' => 'nullable|numeric|min:0', 'stock' => 'sometimes|integer|min:0']);
        DB::transaction(function () use ($product, $d, $r) {
            $old = $product->price;
            $product->fill($d)->save();
            if (array_key_exists('price', $d) && $old != $product->price) {
                PriceChange::create(['product_id' => $product->id, 'user_id' => $r->user()->id, 'old_price' => $old, 'new_price' => $product->price, 'old_cost' => $product->cost, 'new_cost' => $product->cost]);
            }
        });

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

        return Inertia::render('admin/Settings', ['settings' => Setting::pluck('value', 'key')]);
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

    public function upload(Request $r, Product $product)
    {
        $r->validate(['images' => 'required|array', 'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:8192']);
        foreach ($r->file('images') as $file) {
            $image = imagecreatefromstring(file_get_contents($file->getRealPath()));
            $w = imagesx($image);
            $h = imagesy($image);
            $scale = min(1, 1600 / max($w, $h));
            $dest = imagecreatetruecolor((int) ($w * $scale), (int) ($h * $scale));
            imagecopyresampled($dest, $image, 0, 0, 0, 0, imagesx($dest), imagesy($dest), $w, $h);
            $name = Str::uuid().'.webp';
            ob_start();
            imagewebp($dest, null, 85);
            $encoded = ob_get_clean();
            Storage::disk('uploads')->put('products/'.$name, $encoded);
            imagedestroy($image);
            imagedestroy($dest);
            $product->images()->create(['path' => 'products/'.$name, 'sort' => $product->images()->count()]);
        }

        return back()->with('success', 'Fotos cargadas.');
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

        return back();
    }

    public function deleteImage(ProductImage $image)
    {
        Storage::disk('uploads')->delete($image->path);
        $image->delete();

        return back();
    }
}
