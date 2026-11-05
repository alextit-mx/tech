<?php

namespace App\Console\Commands;

use App\Models\Build;
use App\Models\Product;
use App\Support\BuildSuggester;
use Illuminate\Console\Command;

/** Crea o actualiza los equipos armados sugeridos con el catálogo del día (correr después de importar la lista). */
class SuggestBuilds extends Command
{
    protected $signature = 'equipos:sugeridos {--solo-nuevos : No toca los equipos sugeridos que ya existen} {--simular : Calcula sin guardar ni modificar equipos}';

    protected $description = 'Arma los equipos sugeridos de la tienda con productos publicados, con precio y existencias';

    /** slug => [nombre, uso, presupuesto, frase] */
    private const PRESETS = [
        'gamer-1080p' => ['Gamer 1080p', 'gaming1080', 'equilibrada', 'Juega fluido en Full HD sin gastar de más.'],
        'gamer-1440p' => ['Gamer 1440p', 'gaming1440', 'equilibrada', 'El punto ideal: QHD con fluidez.'],
        'ultra-4k' => ['Ultra 4K', 'gaming4k', 'alta', 'Máximo detalle para jugar en 4K.'],
        'juega-y-transmite' => ['Juega y transmite', 'stream', 'alta', 'Núcleos de sobra para jugar y transmitir a la vez.'],
        'estacion-creativa' => ['Estación creativa', 'create', 'alta', 'Edición de video, diseño 3D e IA local.'],
        'oficina-y-escuela' => ['Oficina y escuela', 'office', 'equilibrada', 'Rápida y confiable para el día a día.'],
    ];

    public function handle(): int
    {
        $sort = 0;
        $suggester = new BuildSuggester;
        $simulate = $this->option('simular');
        if ($simulate) {
            $this->info('Simulación: no se guardará ningún cambio.');
        }
        foreach (self::PRESETS as $slug => [$name, $use, $budget, $tagline]) {
            $sort++;
            $existing = Build::where('slug', $slug)->first();
            if ($existing && $this->option('solo-nuevos')) {
                $this->line("· {$name}: ya existe, se queda igual.");

                continue;
            }
            $this->line("· {$name}: calculando…");
            $started = hrtime(true);
            $suggestion = $suggester->forUse($use, $budget);
            $elapsed = sprintf('%.3f s', (hrtime(true) - $started) / 1e9);
            if ($suggestion['unavailable']) {
                if (! $simulate) {
                    $existing?->update(['is_published' => false]);
                }
                $this->warn("✗ {$name}: ".$suggestion['message']." ({$elapsed})");

                continue;
            }
            $parts = $suggestion['parts'];
            if ($simulate) {
                $total = array_sum(array_map(fn (Product $p) => (float) $p->price, $parts));
                $this->info(sprintf('✓ %s: $%s (%s)', $name, number_format($total, 0), $elapsed));

                continue;
            }
            $build = $existing ?? new Build(['slug' => $slug, 'sort' => $sort]);
            $build->fill(['name' => $name, 'tagline' => $tagline, 'use' => $use, 'is_published' => true])->save();
            $build->syncParts(array_map(fn (Product $p) => $p->id, $parts));
            $build->load('products');
            $this->info(sprintf('✓ %s: %s (%s)', $name, $build->total() !== null ? '$'.number_format($build->total(), 0) : 'Cotizar', $elapsed));
        }

        return self::SUCCESS;
    }
}
