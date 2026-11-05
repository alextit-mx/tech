<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\BuilderSpecs;
use App\Support\Hardware\Icecat;
use App\Support\Hardware\IcecatSpecs;
use App\Support\PendingSpecs;
use Illuminate\Console\Command;

class EnrichIcecat extends Command
{
    protected $signature = 'catalogo:icecat {--slot=*} {--limit=} {--imagenes} {--simular} {--refrescar}';

    protected $description = 'Completa fichas e imágenes con Open Icecat respetando datos manuales';

    public function handle(Icecat $icecat): int
    {
        $slots = $this->option('slot') ?: array_keys(BuilderSpecs::SCHEMAS);
        if (array_diff($slots, array_keys(BuilderSpecs::SCHEMAS))) {
            $this->error('Slot desconocido.');

            return self::FAILURE;
        }
        $limit = $this->option('limit');
        if ($limit !== null && (filter_var($limit, FILTER_VALIDATE_INT) === false || (int) $limit < 1)) {
            $this->error('El límite debe ser un entero mayor que cero.');

            return self::FAILURE;
        }
        if (! config('services.icecat.username')) {
            $this->warn('Configura ICECAT_USERNAME para consultar Open Icecat.');

            return self::SUCCESS;
        }
        $query = Product::with(['category', 'brand'])->whereHas('category', fn ($q) => $q->whereIn('builder_slot', $slots))->orderBy('id');
        $counts = $previews = [];
        foreach ((clone $query)->when($limit !== null, fn ($q) => $q->limit((int) $limit))->get() as $product) {
            $slot = $product->category->builder_slot;
            $brand = $product->brand?->name ?? 'Sin marca';
            $key = $slot.'|'.$brand;
            $counts[$key] ??= [$slot, $brand, 0, 0, 0, 0, 0, 0, 0, 0];
            $result = $icecat->find($product, (bool) $this->option('refrescar'));
            foreach ($result['attempts'] as $attempt) {
                if ($attempt['code'] !== 200) {
                    $counts[$key][match ($attempt['code']) {
                        403 => 4, 404 => 5, default => 6
                    }]++;
                }
            }
            if ($result['attempts'] === []) {
                $counts[$key][6]++;
            }
            if ($result['data'] !== null) {
                $counts[$key][$result['method'] === 'gtin' ? 2 : 3]++;
                $oldSpecs = $product->specs ?? [];
                if (! filled($product->model)) {
                    $product->model = Icecat::modelName($result['data']);
                    $product->model_source = 'icecat';
                    $product->modelFromIcecat = true;
                }
                $oldSources = $product->spec_sources ?? [];
                $product->fill(IcecatSpecs::merge($product, IcecatSpecs::map($slot, $result['data'])));
                foreach ($product->specs as $field => $value) {
                    if (($product->spec_sources[$field] ?? null) === 'icecat' && (! array_key_exists($field, $oldSpecs) || $oldSpecs[$field] !== $value || ($oldSources[$field] ?? null) !== 'icecat')) {
                        $counts[$key][7]++;
                    }
                }
                $counts[$key][8] += count($product->spec_conflicts ?? []);
                if (! $this->option('simular')) {
                    if ($product->isDirty()) {
                        $product->save();
                    }
                    if ($this->option('imagenes')) {
                        $counts[$key][9] += $icecat->addImages($product, $result['data']);
                    }
                } else {
                    $previews[$product->id] = $product;
                }
            }
        }
        $this->table(['Slot', 'Marca', 'GTIN', 'N.º parte', '403', '404', 'Otros / sin código', 'Campos', 'Conflictos', 'Imágenes'], array_values($counts));
        $pending = [];
        foreach ($query->lazy(200) as $persisted) {
            $product = $previews[$persisted->id] ?? $persisted;
            if (! $product->specsVerified()) {
                $pending[] = [$product->id, $product->sku, $product->name, $product->category->builder_slot, $product->brand?->name ?? '', implode('|', $product->estimatedSpecs()), json_encode($product->spec_conflicts ?? [], JSON_UNESCAPED_UNICODE)];
            }
        }
        $icecat->writePending($pending);
        $this->info('Pendientes: '.count($pending).'. CSV: storage/app/icecat/pendientes.csv');
        if ($this->option('simular')) {
            $this->warn('Simulación: no se guardaron productos ni imágenes ni se revisaron equipos. La caché y el CSV sí se actualizaron.');
        } else {
            $this->call('equipos:revisar');
            PendingSpecs::forgetCounts();
        }

        return self::SUCCESS;
    }
}
