<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class PendingSpecs
{
    private const COUNTS_CACHE_KEY = 'pending-specs.counts';

    public const SLOTS = ['case' => 'Gabinetes', 'cooler' => 'Disipadores', 'psu' => 'Fuentes', 'gpu' => 'Tarjetas de video', 'cpu' => 'Procesadores', 'ram' => 'Memoria RAM', 'motherboard' => 'Tarjetas madre', 'storage' => 'Almacenamiento'];

    public static function products(): Collection
    {
        return Product::query()->select(['id', 'category_id', 'specs', 'spec_sources', 'spec_conflicts'])
            ->with('category:id,builder_slot')->whereHas('category', fn ($q) => $q->whereIn('builder_slot', array_keys(self::SLOTS)))
            ->get()->filter(fn (Product $p) => $p->estimatedSpecs() !== []);
    }

    public static function counts(?Collection $products = null): array
    {
        return Cache::remember(self::COUNTS_CACHE_KEY, now()->addMinutes(10), function () use ($products) {
            $counts = array_fill_keys(array_keys(self::SLOTS), 0);
            foreach ($products ?? self::products() as $product) {
                $counts[$product->category->builder_slot]++;
            }

            return $counts;
        });
    }

    public static function forgetCounts(): void
    {
        Cache::forget(self::COUNTS_CACHE_KEY);
    }

    public static function row(Product $p): array
    {
        $pending = array_values(array_intersect(Product::CRITICAL_SPECS[$p->category->builder_slot], $p->estimatedSpecs()));

        return [...$p->only(['id', 'name', 'sku', 'stock', 'stock_24h', 'is_published']),
            'brand' => $p->brand?->name, 'image' => $p->imageOrPlaceholder(), 'pending' => $pending,
            'specs' => array_intersect_key($p->specs ?? [], array_flip($pending)),
            'sources' => array_intersect_key($p->spec_sources ?? [], array_flip($pending)),
            'conflicts' => array_values(array_filter($p->spec_conflicts ?? [], fn ($c) => in_array($c['field'], $pending, true))),
        ];
    }
}
