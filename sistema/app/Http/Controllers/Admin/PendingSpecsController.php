<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PendingSpecsRequest;
use App\Models\Product;
use App\Support\BuilderSpecs;
use App\Support\BuildReviewer;
use App\Support\PendingSpecs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PendingSpecsController extends Controller
{
    public function index(Request $request)
    {
        $pending = PendingSpecs::products();
        $counts = PendingSpecs::counts($pending);
        $slot = array_key_exists((string) $request->slot, $counts) ? $request->slot : array_search(max($counts), $counts, true);
        $search = trim((string) $request->search);
        $query = Product::whereIn('id', $pending->modelKeys())->whereHas('category', fn ($q) => $q->where('builder_slot', $slot))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$search}%"))))
            ->when($request->boolean('in_stock'), fn ($q) => $q->whereRaw('(stock + stock_24h) > 0'))
            ->with(['category', 'brand', 'images'])->orderByDesc('is_published')->orderByRaw('(stock + stock_24h) desc')->orderBy('name')->orderBy('id');

        return Inertia::render('admin/PendingSpecs', [
            'products' => $query->paginate(25)->withQueryString()->through(PendingSpecs::row(...)),
            'slots' => PendingSpecs::SLOTS, 'counts' => $counts, 'schema' => BuilderSpecs::fields($slot),
            'filters' => ['slot' => $slot, 'search' => $search, 'in_stock' => $request->boolean('in_stock')],
        ]);
    }

    public function update(PendingSpecsRequest $request, Product $product)
    {
        DB::transaction(function () use ($request, $product) {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $specs = $product->specs ?? [];
            $sources = $product->spec_sources ?? [];
            foreach ($request->validated('specs') as $key => $value) {
                $type = BuilderSpecs::SCHEMAS[$product->category->builder_slot][$key];
                $specs[$key] = match ($type) {
                    'boolean' => (bool) $value, 'integer', 'tier' => (int) $value, default => $value,
                };
                $sources[$key] = 'manual';
            }
            $product->update(['specs' => $specs, 'spec_sources' => $sources,
                'spec_conflicts' => array_values(array_filter($product->spec_conflicts ?? [], fn ($c) => ! array_key_exists($c['field'], $request->validated('specs')))),
            ]);
        });
        PendingSpecs::forgetCounts();
        $product->refresh()->load(['category', 'brand', 'images']);

        return response()->json(['product' => PendingSpecs::row($product), 'complete' => $product->specsVerified()]);
    }

    public function review(BuildReviewer $reviewer)
    {
        return response()->json($reviewer->review());
    }
}
