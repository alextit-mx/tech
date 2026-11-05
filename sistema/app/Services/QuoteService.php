<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use App\Support\Folio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuoteService
{
    /**
     * Cotización desde la tienda o el configurador. Los precios salen de la base; lo que mande el navegador se ignora.
     *
     * @param  array{name: string, phone: string, email?: ?string}  $contact
     * @param  array<int, array{product_id: int, quantity?: int}>  $items
     */
    public function createFromCart(array $contact, array $items, string $source, ?string $buildName = null, ?string $notes = null): Quote
    {
        return DB::transaction(function () use ($contact, $items, $source, $buildName, $notes) {
            $customer = Customer::fromContact($contact['name'], $contact['phone'], $contact['email'] ?? null);
            $quote = $this->newQuote($customer, null, $source);
            $quote->fill(['build_name' => $buildName, 'notes' => $notes])->save();

            $quantities = collect($items)->groupBy('product_id')->map(fn ($rows) => min(99, max(1, (int) $rows->sum(fn ($row) => $row['quantity'] ?? 1))));
            $products = Product::whereIn('id', $quantities->keys())->where('is_published', true)->get()->keyBy('id');

            $lines = $quantities->filter(fn ($qty, $id) => $products->has($id))
                ->map(fn ($qty, $id) => $this->lineFromProduct($products[$id], $qty))
                ->values()->all();

            return $this->syncItems($quote, $lines);
        });
    }

    /** Alta o edición desde el portal. Partidas con `product_id` toman costo del producto; el precio lo decide el vendedor. */
    public function saveFromAdmin(?Quote $quote, array $data, User $user): Quote
    {
        return DB::transaction(function () use ($quote, $data, $user) {
            $customer = Customer::findOrFail($data['customer_id']);
            $quote ??= $this->newQuote($customer, $user, 'admin');
            $quote->fill([
                'customer_id' => $customer->id,
                'status' => $data['status'] ?? $quote->status,
                'valid_until' => $data['valid_until'] ?? $quote->valid_until,
                'discount' => $data['discount'] ?? 0,
                'notes' => $data['notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'build_name' => $data['build_name'] ?? $quote->build_name,
            ]);
            $quote->user_id ??= $user->id;
            $quote->save();

            $existing = $quote->items()->get()->keyBy('product_id');
            $products = Product::whereIn('id', collect($data['items'])->pluck('product_id')->filter())->get()->keyBy('id');
            $lines = collect($data['items'])->map(function (array $row) use ($products, $existing) {
                $product = isset($row['product_id']) ? $products->get($row['product_id']) : null;

                return [
                    'product_id' => $product?->id,
                    'sku' => $product?->sku ?? ($row['sku'] ?? null),
                    ...collect($product ? $this->lineFromProduct($product) : [])->only(['brand', 'model', 'warranty'])->all(),
                    ...array_intersect_key($row, array_flip(['brand', 'model', 'warranty'])),
                    'description' => $row['description'],
                    'quantity' => (int) $row['quantity'],
                    'unit_price' => $row['unit_price'] === null || $row['unit_price'] === '' ? null : round((float) $row['unit_price'], 2),
                    // Conserva el costo copiado al cotizar; solo partidas nuevas toman el costo actual.
                    'unit_cost' => $product ? ($existing->get($product->id)?->unit_cost ?? $product->costInMxn()) : null,
                ];
            })->all();

            return $this->syncItems($quote, $lines);
        });
    }

    public function duplicate(Quote $original, User $user): Quote
    {
        return DB::transaction(function () use ($original, $user) {
            $copy = $this->newQuote($original->customer, $user, 'admin');
            $copy->fill($original->only(['discount', 'notes', 'internal_notes', 'build_name']))->save();

            return $this->syncItems($copy, $original->items->map(fn ($item) => $item->only(['product_id', 'sku', 'brand', 'model', 'warranty', 'description', 'quantity', 'unit_price', 'unit_cost']))->all());
        });
    }

    /** @return array<string, mixed> */
    public function lineFromProduct(Product $product, int $quantity = 1): array
    {
        return [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'brand' => $product->brand?->name,
            'model' => $product->model,
            'warranty' => self::warrantyText($product->warranty_months),
            'description' => trim(($product->brand?->name ? $product->brand->name.' ' : '').$product->name),
            'quantity' => $quantity,
            'unit_price' => $product->price_mode === 'show' && $product->price !== null ? (float) $product->price : null,
            'unit_cost' => $product->costInMxn(),
        ];
    }

    public static function warrantyText(?int $months): ?string
    {
        if (! $months) {
            return null;
        }

        return $months % 12 === 0
            ? ($months / 12).' '.($months === 12 ? 'AÑO' : 'AÑOS')
            : $months.' '.($months === 1 ? 'MES' : 'MESES');
    }

    private function newQuote(Customer $customer, ?User $user, string $source): Quote
    {
        $quote = new Quote([
            'folio' => Folio::next('COT'),
            'customer_id' => $customer->id,
            'user_id' => $user?->id,
            'source' => $source,
            'status' => $source === 'admin' ? 'draft' : 'sent',
            'valid_until' => now()->addDays((int) setting('quote_validity_days', 3))->toDateString(),
            'public_token' => Str::random(40),
        ]);
        $quote->save();

        return $quote;
    }

    /** Reemplaza partidas y recalcula: total = partidas con precio − descuento. */
    private function syncItems(Quote $quote, array $lines): Quote
    {
        $quote->items()->delete();
        foreach (array_values($lines) as $index => $line) {
            $line['line_total'] = $line['unit_price'] === null ? null : round($line['unit_price'] * $line['quantity'], 2);
            $quote->items()->create($line + ['sort' => $index]);
        }
        $sum = collect($lines)->sum(fn ($line) => $line['unit_price'] === null ? 0 : $line['unit_price'] * $line['quantity']);
        $quote->total = max(0, round($sum - (float) $quote->discount, 2));
        $quote->save();

        return $quote->load('items', 'customer');
    }
}
