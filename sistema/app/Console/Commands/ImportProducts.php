<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\PriceChange;
use App\Models\Product;
use App\Support\Hardware\SpecExtractor;
use App\Support\PendingSpecs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa la lista de precios ya convertida por herramientas/lista_precios.py.
 * Crea o actualiza por SKU (código de barras): nombre, marca, categoría, precio con IVA, existencias y garantía.
 * Respeta lo que se editó a mano en el portal: costo y campos manuales o de Icecat no se pisan.
 */
class ImportProducts extends Command
{
    protected $signature = 'productos:importar
        {archivo : CSV generado por herramientas/lista_precios.py}
        {--simular : Solo cuenta lo que haría, sin guardar}
        {--ocultar-faltantes : Oculta en la tienda los productos que ya no vienen en la lista}';

    protected $description = 'Importa la lista de precios (CSV) a productos y tienda en línea';

    /** Categorías que la lista necesita y el árbol inicial no trae: slug => [nombre, slug del padre]. */
    private const NEW_CATEGORIES = [
        'computadoras' => ['Computadoras', null],
        'laptops' => ['Laptops', 'computadoras'],
        'tablets' => ['Tablets', 'computadoras'],
        'all-in-one' => ['All in One', 'computadoras'],
        'computadoras-de-escritorio' => ['Escritorio y mini PC', 'computadoras'],
        'ventiladores-y-pasta-termica' => ['Ventiladores y pasta térmica', 'componentes'],
        'mousepads' => ['Mousepads', 'perifericos-gamer'],
        'escritorios' => ['Escritorios', 'perifericos-gamer'],
        'consolas' => ['Consolas', 'perifericos-gamer'],
        'webcams-y-streaming' => ['Webcams y streaming', 'perifericos-gamer'],
        'bocinas' => ['Bocinas y audio', 'perifericos-gamer'],
        'escaneres' => ['Escáneres', 'impresoras-y-consumibles'],
        'cintas-y-etiquetas' => ['Cintas y etiquetas', 'impresoras-y-consumibles'],
        'mochilas-y-fundas' => ['Mochilas y fundas', 'accesorios'],
        'otros-accesorios' => ['Otros accesorios', 'accesorios'],
        'memoria-laptop' => ['Memoria para laptop', 'componentes'],
        'seguridad-y-videovigilancia' => ['Seguridad y videovigilancia', null],
        'proyectores-y-pantallas' => ['Proyectores y pantallas', null],
        'software-y-licencias' => ['Software y licencias', null],
    ];

    public function handle(): int
    {
        $path = $this->argument('archivo');
        if (! is_readable($path)) {
            $this->error("No encuentro {$path}.");

            return self::FAILURE;
        }

        $rows = $this->rows($path);
        $simulate = (bool) $this->option('simular');
        $counts = ['nuevos' => 0, 'actualizados' => 0, 'sin cambios' => 0, 'cambios de precio' => 0, 'sin categoría' => 0];

        DB::transaction(function () use ($rows, $simulate, &$counts) {
            $categories = $this->categories($simulate);
            $brands = [];

            foreach ($rows as $row) {
                $categoryId = $categories[$row['category']] ?? null;
                if (! $categoryId) {
                    $counts['sin categoría']++;

                    continue;
                }

                $product = Product::withTrashed()->firstOrNew(['sku' => $row['sku']]);
                $isNew = ! $product->exists;
                $oldPrice = $product->price;

                $brandId = null;
                if ($row['brand'] !== '') {
                    $slug = Str::slug($row['brand']);
                    $brandId = $brands[$slug] ??= $simulate ? 0 : Brand::firstOrCreate(['slug' => $slug], ['name' => $row['brand']])->id;
                }

                $slot = Category::find($categoryId)?->builder_slot;
                $derived = SpecExtractor::extract($slot ?? '', $row['name']);
                $product->fill(SpecExtractor::merge($product, $derived));

                $product->fill([
                    'name' => $row['name'],
                    'category_id' => $categoryId,
                    'brand_id' => $brandId ?: $product->brand_id,
                    'price' => $row['price'],
                    'price_mode' => 'show',
                    // CENTRO = entrega inmediata; 24H = llega al día siguiente.
                    'stock' => (int) $row['stock'],
                    'stock_24h' => (int) $row['stock_24h'],
                    'is_published' => (bool) $row['published'],
                    'short_description' => $row['condition'] !== 'Nuevo' ? $row['condition'] : null,
                    'warranty_months' => $row['warranty_months'] !== '' ? (int) $row['warranty_months'] : $product->warranty_months,
                ]);
                if ($isNew) {
                    $product->slug = $this->uniqueSlug($row['name'], $row['sku']);
                    $product->cost_currency = 'MXN';
                }

                if (! $isNew && ! $product->isDirty()) {
                    $counts['sin cambios']++;

                    continue;
                }
                $counts[$isNew ? 'nuevos' : 'actualizados']++;
                $priceChanged = ! $isNew && (float) $oldPrice !== (float) $row['price'];
                $counts['cambios de precio'] += (int) $priceChanged;
                if ($simulate) {
                    continue;
                }

                if ($product->trashed()) {
                    $product->deleted_at = null;
                }
                $product->save();
                if ($priceChanged) {
                    PriceChange::create(['product_id' => $product->id, 'user_id' => null, 'old_price' => $oldPrice, 'new_price' => $product->price, 'old_cost' => $product->cost, 'new_cost' => $product->cost]);
                }
            }

            if ($this->option('ocultar-faltantes') && ! $simulate) {
                $hidden = Product::where('is_published', true)->whereNotIn('sku', array_column($rows, 'sku'))->update(['is_published' => false]);
                $this->line("Ocultos por no venir en la lista: {$hidden}");
            }
        });

        $this->table(['Resultado', 'Productos'], collect($counts)->map(fn ($n, $k) => [$k, $n])->values());
        $this->info($simulate ? 'Simulación: no se guardó nada.' : 'Importación terminada.');

        if (! $simulate) {
            $this->call('equipos:revisar');
            PendingSpecs::forgetCounts();
        }

        return self::SUCCESS;
    }

    /** @return list<array<string, string>> */
    private function rows(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '\\');
        $rows = [];
        while (($line = fgetcsv($handle, escape: '\\')) !== false) {
            if (count($line) === count($header)) {
                $rows[] = array_combine($header, $line);
            }
        }
        fclose($handle);

        return $rows;
    }

    /** @return array<string, int> slug => id, creando las categorías nuevas que falten. */
    private function categories(bool $simulate): array
    {
        if (! $simulate) {
            $sort = (int) Category::max('sort') + 1;
            foreach (self::NEW_CATEGORIES as $slug => [$name, $parent]) {
                Category::firstOrCreate(['slug' => $slug], [
                    'name' => $name,
                    'parent_id' => $parent ? Category::where('slug', $parent)->value('id') : null,
                    'sort' => $sort++,
                    'is_visible' => true,
                ]);
            }
        }

        return Category::pluck('id', 'slug')->all();
    }

    private function uniqueSlug(string $name, string $sku): string
    {
        $slug = Str::slug(Str::limit($name, 90, ''));

        // Remates y cajas abiertas repiten nombre y terminan en el mismo código que el original: el SKU completo es único.
        return Product::withTrashed()->where('slug', $slug)->exists() ? $slug.'-'.Str::slug($sku) : $slug;
    }
}
