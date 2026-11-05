<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Build;
use App\Models\Category;
use App\Models\Product;
use App\Support\BuildValidator;
use App\Support\WebpImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Equipos armados que sugiere la tienda: dueño y vendedores los arman, revisan compatibilidad y publican. */
class BuildController extends Controller
{
    public function index(Request $request): Response
    {
        $builds = Build::with('products.images')->orderBy('sort')->orderBy('id')->get()
            ->map(fn (Build $b) => [...$this->summary($b), 'id' => $b->id, 'name' => $b->name, 'slug' => $b->slug, 'use' => Build::USES[$b->use] ?? $b->use, 'is_published' => $b->is_published, 'image_url' => $b->imageUrl(), 'parts_count' => $b->products->count(), 'thumbs' => $b->parts()->take(4)->map(fn (Product $p) => $p->imageOrPlaceholder())->values()]);

        return Inertia::render('admin/Builds', ['builds' => $builds]);
    }

    public function form(Request $request, ?Build $build = null): Response
    {
        $build?->load('products.images', 'products.brand:id,name');
        $parts = $build ? $build->parts()->map(fn (Product $p) => $this->part($p)) : collect();
        // Desde el configurador: /admin/builds/create?b=1.2.3&n=Nombre
        if (! $build && $request->filled('b')) {
            $parts = $this->lookup(explode('.', (string) $request->query('b')))->map(fn (Product $p) => $this->part($p));
        }

        return Inertia::render('admin/BuildForm', [
            'build' => $build ? [...$build->only(['id', 'name', 'slug', 'tagline', 'use', 'is_published']), 'image_url' => $build->imageUrl()] : null,
            'prefill' => ['name' => $build ? null : Str::limit((string) $request->query('n', ''), 80, '')],
            'parts' => (object) $parts->all(),
            'slots' => Build::SLOT_LABELS,
            'uses' => Build::USES,
            'canMargins' => $request->user()->can('view-margins'),
        ]);
    }

    /** Candidatos para un slot, con la razón si no son compatibles con lo ya elegido. */
    public function parts(Request $request): JsonResponse
    {
        $data = $request->validate(['slot' => ['required', Rule::in(Build::SLOTS)], 'q' => 'nullable|string|max:80', 'current' => 'nullable|array']);
        $current = $this->lookup(array_values($data['current'] ?? []))->except($data['slot']);
        $base = BuildValidator::errors($this->specs($current));
        $categories = Category::where('builder_slot', $data['slot'])->pluck('id');
        $q = trim((string) ($data['q'] ?? ''));

        $items = Product::with('images', 'brand:id,name', 'category:id,builder_slot')->whereIn('category_id', $categories)
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")))
            ->orderByDesc('is_published')->orderByRaw('(stock > 0 or stock_24h > 0) desc')->orderBy('price')->limit(60)->get()
            ->map(function (Product $p) use ($current, $base, $data) {
                $issues = array_values(array_diff(BuildValidator::errors($this->specs($current->merge([$data['slot'] => $p]))), $base));

                return [...$this->part($p), 'issue' => $issues[0] ?? null];
            })
            ->sortBy(fn ($p) => $p['issue'] ? 1 : 0)->values();

        return response()->json(['items' => $items]);
    }

    /** Compatibilidad, faltantes y totales del equipo en edición. */
    public function check(Request $request): JsonResponse
    {
        $parts = $this->lookup(array_values((array) $request->input('parts', [])));
        $specs = $this->specs($parts);
        $price = $parts->every(fn (Product $p) => $p->is_published && $p->publicPrice() !== null) && $parts->isNotEmpty() ? $parts->sum(fn (Product $p) => $p->publicPrice()) : null;
        $cost = $parts->every(fn (Product $p) => $p->costInMxn() !== null) && $parts->isNotEmpty() ? $parts->sum(fn (Product $p) => $p->costInMxn()) : null;

        return response()->json([
            'parts' => (object) $parts->map(fn (Product $p) => $this->part($p))->all(),
            'errors' => BuildValidator::errors($specs),
            'issues' => BuildValidator::issues($specs),
            'unverified' => $parts->reject(fn (Product $p) => $p->specsVerified())->map(fn (Product $p) => $p->name)->values(),
            'missing' => BuildValidator::missing($specs),
            'hidden' => $parts->reject(fn (Product $p) => $p->is_published && $p->publicPrice() !== null)->map(fn (Product $p) => $p->name)->values(),
            'total' => $price,
            'cost' => $cost,
            ...($request->user()->can('view-margins') ? ['margin' => $price && $cost ? round(1 - $cost / ($price / 1.16), 4) : null] : []),
            'watts' => BuildValidator::consumption($specs),
        ]);
    }

    public function save(Request $request, ?Build $build = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'tagline' => 'nullable|string|max:140',
            'use' => ['required', Rule::in(array_keys(Build::USES))],
            'is_published' => 'boolean',
            'parts' => 'nullable|array',
            'parts.*' => 'nullable|integer|exists:products,id',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:20480',
            'remove_image' => 'boolean',
        ], [], ['name' => 'nombre', 'tagline' => 'frase', 'image' => 'foto']);

        $parts = $this->lookup(array_values(array_filter($data['parts'] ?? [])));
        if ($parts->isEmpty()) {
            return back()->withErrors(['parts' => 'Elige al menos una pieza.']);
        }
        if ($request->boolean('is_published')) {
            $specs = $this->specs($parts);
            $problems = [...array_column(BuildValidator::issues($specs), 'message'), ...$parts->reject(fn (Product $p) => $p->specsVerified())->map(fn (Product $p) => 'Ficha pendiente: '.$p->name.'.')->values()->all(), ...array_map(fn ($m) => "Falta {$m}.", BuildValidator::missing($specs))];
            if ($problems) {
                return back()->withErrors(['parts' => 'Para publicarlo corrige: '.implode(' ', $problems)]);
            }
        }

        $build ??= new Build(['sort' => (int) Build::max('sort') + 1]);
        DB::transaction(function () use ($build, $data, $request, $parts) {
            if ($request->hasFile('image') || $request->boolean('remove_image')) {
                if ($build->image) {
                    Storage::disk('uploads')->delete($build->image);
                }
                $build->image = $request->hasFile('image') ? WebpImage::store($request->file('image'), 'builds', 1600, 85, 'image') : null;
            }
            $build->fill([
                'name' => $data['name'], 'tagline' => $data['tagline'] ?? null, 'use' => $data['use'], 'is_published' => $request->boolean('is_published'),
                'slug' => $build->slug ?: $this->slug($data['name']),
            ])->save();
            $build->syncParts($parts->map->id->all());
        });

        return $build->wasRecentlyCreated
            ? redirect()->route('admin.builds.edit', $build->id)->with('success', 'Equipo creado.')
            : back()->with('success', 'Equipo guardado.');
    }

    public function publish(Request $request, Build $build)
    {
        $on = $request->boolean('is_published');
        if ($on) {
            $build->load('products');
            $problems = $build->publicationProblems();
            if ($problems) {
                return back()->withErrors(['publish' => 'No se puede publicar: '.implode(' ', $problems)]);
            }
        }
        $build->update(['is_published' => $on]);

        return back();
    }

    public function move(Request $request, Build $build)
    {
        $direction = $request->integer('direction') < 0 ? -1 : 1;
        DB::transaction(function () use ($build, $direction) {
            $all = Build::orderBy('sort')->orderBy('id')->get()->values();
            $i = $all->search(fn (Build $b) => $b->id === $build->id);
            $j = $i + $direction;
            if ($j >= 0 && $j < $all->count()) {
                [$all[$i], $all[$j]] = [$all[$j], $all[$i]];
            }
            $all->each(fn (Build $b, $index) => $b->sort !== $index ? $b->update(['sort' => $index]) : null);
        });

        return back();
    }

    public function duplicate(Build $build)
    {
        $copy = $build->replicate(['slug', 'image']);
        $copy->fill(['name' => Str::limit($build->name.' (copia)', 80, ''), 'slug' => $this->slug($build->name.' copia'), 'is_published' => false, 'sort' => (int) Build::max('sort') + 1])->save();
        $copy->syncParts($build->load('products')->parts()->map->id->all());

        return redirect()->route('admin.builds.edit', $copy->id)->with('success', 'Copia creada (oculta).');
    }

    public function destroy(Build $build)
    {
        if ($build->image) {
            Storage::disk('uploads')->delete($build->image);
        }
        $build->delete();

        return redirect()->route('admin.builds')->with('success', 'Equipo borrado.');
    }

    /** Productos por id, con su slot del configurador como llave (descarta lo que no es pieza de PC). */
    private function lookup(array $ids): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        return Product::with('images', 'brand:id,name', 'category:id,builder_slot')->whereIn('id', $ids)->get()
            ->filter(fn (Product $p) => in_array($p->category?->builder_slot, Build::SLOTS, true))
            ->keyBy(fn (Product $p) => $p->category->builder_slot)->toBase();
    }

    private function specs(Collection $parts): array
    {
        return $parts->map(fn (Product $p) => $p->compatibilityPart())->all();
    }

    private function part(Product $p): array
    {
        return [
            'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'brand' => $p->brand?->name, 'image' => $p->imageOrPlaceholder(),
            'price' => $p->publicPrice(), 'cost' => $p->costInMxn(), 'published' => $p->is_published,
            'stock' => $p->stockLevel(), 'stock_label' => $p->stockLabel(),
        ];
    }

    private function summary(Build $b): array
    {
        $total = $b->total();
        [$level, $label] = $b->availability();

        return ['total' => $total, 'stock' => $level, 'stock_label' => $label, 'errors' => $b->errors()];
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'equipo';
        $slug = $base;
        for ($i = 2; Build::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
