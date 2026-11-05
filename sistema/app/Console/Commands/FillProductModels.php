<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\Hardware\Icecat;
use Illuminate\Console\Command;

class FillProductModels extends Command
{
    protected $signature = 'catalogo:modelos {--rehacer : Recalcula modelos de Icecat, conserva los manuales} {--simular : Muestra los cambios sin guardarlos}';

    protected $description = 'Completa modelos vacíos exclusivamente desde la caché local de Icecat';

    public function handle(Icecat $icecat): int
    {
        $count = 0;
        foreach (Product::with('brand')->where(fn ($q) => $q->whereNull('model')->orWhere('model', '')->when($this->option('rehacer'), fn ($q) => $q->orWhere('model_source', 'icecat')->orWhereNull('model_source')))->lazyById(200) as $product) {
            if (filled($product->model) && $product->model_source !== 'icecat' && $product->model !== $icecat->cachedModel($product, original: true)) {
                continue;
            }
            if (! ($model = $icecat->cachedModel($product))) {
                continue;
            }
            $this->line($product->sku.' → '.$model);
            if (! $this->option('simular')) {
                $product->getConnection()->transaction(function () use ($product, $model) {
                    $current = Product::whereKey($product->id)->lockForUpdate()->first();
                    if ($current && $current->model === $product->model && $current->model_source === $product->model_source) {
                        $current->model = $model;
                        $current->model_source = 'icecat';
                        $current->modelFromIcecat = true;
                        $current->save();
                    }
                });
            }
            $count++;
        }
        $this->info(($this->option('simular') ? 'Simulación: ' : 'Modelos completados: ').$count.'. Solo se leyó la caché local.');

        return self::SUCCESS;
    }
}
