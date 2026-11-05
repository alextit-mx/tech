<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductSearch
{
    public static function normalize(?string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower(Str::ascii($value ?? ''))));
    }

    public static function compact(string $value): string
    {
        return preg_replace('/[\s.\-]+/u', '', self::normalize($value));
    }

    public static function text(Product $product): string
    {
        $text = self::normalize(implode(' ', [$product->name, $product->sku, $product->model, $product->brand?->name, $product->category?->name]));

        return $text.' '.self::compact($text);
    }

    private static function sqlNormalize(string $expression, bool $compact = false): string
    {
        $expression = "LOWER(COALESCE($expression, ''))";
        foreach (['Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n'] as $from => $to) {
            $expression = "REPLACE($expression, '$from', '$to')";
        }
        if ($compact) {
            foreach ([' ', '-', '.', "\t", "\n"] as $character) {
                $expression = "REPLACE($expression, '$character', '')";
            }
        }

        return $expression;
    }

    public static function apply(Builder $query, string $term): Builder
    {
        $term = self::normalize($term);
        if ($term === '') {
            return $query;
        }
        $tokens = preg_split('/\s+/', $term);
        $columns = ['products.name', 'products.sku', 'products.model', '(SELECT name FROM brands WHERE brands.id = products.brand_id)', '(SELECT name FROM categories WHERE categories.id = products.category_id)'];
        foreach ($tokens as $token) {
            // Los comodines del vendedor son texto literal, no operadores SQL.
            $pattern = '%'.self::escape(self::compact($token)).'%';
            $query->where(function ($where) use ($columns, $pattern) {
                $where->whereRaw("products.search_text LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhere(function ($fallback) use ($columns, $pattern) {
                        $fallback->where(fn ($empty) => $empty->whereNull('products.search_text')->orWhere('products.search_text', ''))
                            ->where(function ($fields) use ($columns, $pattern) {
                                foreach ($columns as $column) {
                                    $fields->orWhereRaw(self::sqlNormalize($column, true)." LIKE ? ESCAPE '!'", [$pattern]);
                                }
                            });
                    });
            });
        }
        $sku = self::sqlNormalize('products.sku', true);
        $model = self::sqlNormalize('products.model', true);
        $name = self::sqlNormalize('products.name', true);
        $exact = self::compact($term);
        $prefix = self::escape($exact).'%';
        $allName = implode(' AND ', array_fill(0, count($tokens), "$name LIKE ? ESCAPE '!'"));

        return $query->orderByRaw("CASE WHEN $sku = ? OR $model = ? THEN 0 WHEN $sku LIKE ? ESCAPE '!' OR $model LIKE ? ESCAPE '!' THEN 1 WHEN $name LIKE ? ESCAPE '!' THEN 2 WHEN $allName THEN 3 ELSE 4 END", [$exact, $exact, $prefix, $prefix, $prefix, ...array_map(fn ($token) => '%'.self::escape(self::compact($token)).'%', $tokens)])
            ->orderByRaw('CASE WHEN products.stock > 0 OR products.stock_24h > 0 THEN 0 ELSE 1 END')->orderByDesc('products.is_published');
    }

    private static function escape(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
