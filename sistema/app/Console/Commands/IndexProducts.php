<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\ProductSearch;
use Illuminate\Console\Command;

class IndexProducts extends Command
{
    protected $signature = 'catalogo:indexar {--simular : Muestra el total sin guardar cambios}';

    protected $description = 'Regenera el índice normalizado de búsqueda de productos';

    public function handle(): int
    {
        $count = 0;
        foreach (Product::with(['brand', 'category'])->lazyById(200) as $product) {
            if (! $this->option('simular')) {
                Product::whereKey($product->id)->update(['search_text' => ProductSearch::text($product)]);
            }
            $count++;
        }
        $this->info(($this->option('simular') ? 'Simulación: ' : 'Productos indexados: ').$count);

        return self::SUCCESS;
    }
}
