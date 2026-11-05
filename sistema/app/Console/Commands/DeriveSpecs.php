<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\BuilderSpecs;
use App\Support\Hardware\SpecExtractor;
use App\Support\PendingSpecs;
use Illuminate\Console\Command;

class DeriveSpecs extends Command
{
    protected $signature = 'catalogo:fichas {--slot=} {--simular}';

    protected $description = 'Actualiza fichas derivadas respetando campos manuales e Icecat';

    public function handle(): int
    {
        if ($this->option('slot') && ! array_key_exists($this->option('slot'), BuilderSpecs::SCHEMAS)) {
            $this->error('Slot desconocido.');

            return self::FAILURE;
        }
        $counts = [];
        $examples = [];
        Product::with('category')->whereHas('category', fn ($q) => $q->whereNotNull('builder_slot')->when($this->option('slot'), fn ($q, $slot) => $q->where('builder_slot', $slot)))
            ->chunkById(200, function ($products) use (&$counts, &$examples) {
                foreach ($products as $p) {
                    $slot = $p->category->builder_slot;
                    $counts[$slot] ??= ['changed' => 0, 'pending' => 0];
                    $old = $p->specs;
                    $p->fill(SpecExtractor::merge($p, SpecExtractor::extract($slot, $p->name)));
                    if ($p->isDirty()) {
                        $counts[$slot]['changed']++;
                        if (count($examples[$slot] ?? []) < 3) {
                            $examples[$slot][] = $p->name.': '.json_encode(['antes' => $old, 'después' => $p->specs], JSON_UNESCAPED_UNICODE);
                        }
                        if (! $this->option('simular')) {
                            $p->save();
                        }
                    }
                    $counts[$slot]['pending'] += (int) ! $p->specsVerified();
                }
            });
        $this->table(['Slot', 'Cambian', 'Ficha pendiente'], collect($counts)->map(fn ($c, $slot) => [$slot, $c['changed'], $c['pending']])->values());
        foreach ($examples as $rows) {
            foreach ($rows as $row) {
                $this->line($row);
            }
        }
        if (! $this->option('simular')) {
            $this->call('equipos:revisar');
            PendingSpecs::forgetCounts();
        }

        return self::SUCCESS;
    }
}
