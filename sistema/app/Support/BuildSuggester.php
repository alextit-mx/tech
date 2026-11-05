<?php

namespace App\Support;

use App\Models\Product;
use App\Support\Hardware\Compatibility;
use Illuminate\Support\Collection;

/**
 * Arma un equipo con fichas confirmadas y existencias del día.
 * Mismas metas de nivel, memoria y almacenamiento; la compatibilidad la decide BuildValidator.
 */
class BuildSuggester
{
    /** [nivel de CPU, nivel de GPU] por uso y presupuesto (GPU 0 = basta con gráficos integrados). */
    private const TARGETS = [
        'gaming1080' => ['esencial' => [1, 1], 'equilibrada' => [2, 2], 'alta' => [3, 3], 'extrema' => [4, 4]],
        'gaming1440' => ['esencial' => [2, 2], 'equilibrada' => [3, 3], 'alta' => [4, 4], 'extrema' => [4, 5]],
        'gaming4k' => ['esencial' => [3, 3], 'equilibrada' => [4, 4], 'alta' => [4, 5], 'extrema' => [5, 6]],
        'stream' => ['esencial' => [2, 2], 'equilibrada' => [3, 3], 'alta' => [4, 4], 'extrema' => [5, 5]],
        'create' => ['esencial' => [2, 1], 'equilibrada' => [3, 2], 'alta' => [4, 3], 'extrema' => [5, 5]],
        'office' => ['esencial' => [1, 0], 'equilibrada' => [1, 0], 'alta' => [2, 0], 'extrema' => [3, 1]],
    ];

    private const MEMORY = ['esencial' => 16, 'equilibrada' => 32, 'alta' => 32, 'extrema' => 64];

    private const STORAGE_GB = ['esencial' => 1000, 'equilibrada' => 1000, 'alta' => 2000, 'extrema' => 4000];

    public const MAX_EVALUATIONS = 20_000;

    public const MAX_SECONDS = 5.0;

    private ?Collection $bySlot = null;

    private array $partSpecs = [];

    private int $evaluations = 0;

    private float $started = 0;

    private ?string $limitReason = null;

    public function __construct(
        private readonly int $maxEvaluations = self::MAX_EVALUATIONS,
        private readonly float $maxSeconds = self::MAX_SECONDS,
    ) {}

    /** @return array{parts: array, stepped: bool, unavailable: bool, message: ?string} */
    public static function suggest(string $use, string $budget): array
    {
        return (new self)->forUse($use, $budget);
    }

    /** Reutiliza el catálogo en el comando; cada armado tiene su propio presupuesto de búsqueda. */
    public function forUse(string $use, string $budget): array
    {
        $this->started = hrtime(true) / 1e9;
        $this->evaluations = 0;
        $this->limitReason = null;
        $targets = self::TARGETS[$use][$budget] ?? null;
        if (! $targets) {
            return self::unavailable();
        }
        $this->loadCandidates();
        if ($this->limitReached()) {
            return self::unavailable($this->limitReason);
        }
        for ($step = 0; $step <= 2; $step++) {
            $cpuTier = max(1, $targets[0] - $step);
            $gpuTier = $targets[1] === 0 ? 0 : max(1, $targets[1] - $step);
            // Para oficina, primero integrados; si no hay, se intenta con GPU antes del CPU.
            foreach ($gpuTier === 0 ? [false, true] : [true] as $withGpu) {
                $order = [...($withGpu ? ['gpu'] : []), 'cpu', 'motherboard', 'ram', 'psu', 'cooler', 'case', 'storage'];
                $candidates = $this->bySlot->map(fn (Collection $products, string $slot) => $products->filter(function (Product $p) use ($slot, $cpuTier, $gpuTier, $budget, $withGpu) {
                    $s = $this->partSpecs[$p->id]['specs'];

                    return match ($slot) {
                        'cpu' => ($s['tier'] ?? 0) >= $cpuTier && ($withGpu || ($s['igpu'] ?? false)),
                        'gpu' => ($s['tier'] ?? 0) >= max(1, $gpuTier),
                        'ram' => ($s['capacity_gb'] ?? 0) >= self::MEMORY[$budget],
                        'storage' => ($s['capacity_gb'] ?? 0) >= self::STORAGE_GB[$budget],
                        default => true,
                    };
                }));
                if ($this->limitReached()) {
                    return self::unavailable($this->limitReason);
                }
                // Una pieza ausente no se resuelve recorriendo combinaciones de los otros slots.
                if (collect($order)->contains(fn ($slot) => $candidates->get($slot, collect())->isEmpty())) {
                    continue;
                }
                $parts = $this->search($candidates, $order, [], []);
                if ($this->limitReached()) {
                    return self::unavailable($this->limitReason);
                }
                if ($parts !== null) {
                    return ['parts' => $parts, 'stepped' => $step > 0, 'unavailable' => false, 'message' => $step > 0 ? 'Bajamos '.($step === 1 ? 'un escalón' : 'dos escalones').' de rendimiento para ofrecer un armado compatible con las existencias de hoy.' : null];
                }
            }
        }

        return self::unavailable();
    }

    private function loadCandidates(): void
    {
        if ($this->bySlot !== null) {
            return;
        }
        $products = Product::with('category:id,builder_slot')->where('is_published', true)
            ->where('price_mode', 'show')->where('price', '>', 0)
            ->where(fn ($q) => $q->where('stock', '>', 0)->orWhere('stock_24h', '>', 0))
            ->whereHas('category', fn ($q) => $q->whereNotNull('builder_slot')->where('is_visible', true))
            ->orderBy('price')->orderBy('id')->get();
        $verified = collect();
        foreach ($products as $product) {
            if ($this->limitReached()) {
                return;
            }
            if ($product->specsVerified()) {
                $verified->push($product);
                $this->partSpecs[$product->id] = $product->compatibilityPart();
            }
        }
        $this->bySlot = $verified->groupBy(fn (Product $p) => $p->category->builder_slot)
            ->map(fn (Collection $products, string $slot) => match ($slot) {
                'ram' => $products->sortBy(fn (Product $p) => ($this->partSpecs[$p->id]['specs']['modules'] ?? 0) === 2 ? 0 : 1),
                'storage' => $products->sortBy(fn (Product $p) => ($this->partSpecs[$p->id]['specs']['type'] ?? '') === 'NVMe' ? 0 : 1),
                default => $products,
            });
    }

    private static function unavailable(?string $reason = null): array
    {
        return ['parts' => [], 'stepped' => false, 'unavailable' => true, 'message' => 'Este armado no está disponible con las existencias de hoy'.($reason ? ': '.$reason : '')];
    }

    private function limitReached(): bool
    {
        if ($this->limitReason !== null) {
            return true;
        }
        if (hrtime(true) / 1e9 - $this->started >= $this->maxSeconds) {
            $this->limitReason = 'se alcanzó el límite de tiempo de búsqueda ('.$this->maxSeconds.' s).';
        }

        return $this->limitReason !== null;
    }

    private function search(Collection $bySlot, array $order, array $build, array $specs): ?array
    {
        if ($this->limitReached()) {
            return null;
        }
        if ($order === []) {
            return $build;
        }
        $slot = array_shift($order);
        $viable = 0;
        foreach ($bySlot->get($slot, collect()) as $part) {
            if ($this->limitReached()) {
                return null;
            }
            // Cuenta también los candidatos rechazados y comparte el tope entre todos los escalones.
            if ($this->evaluations >= $this->maxEvaluations) {
                $this->limitReason = 'se alcanzó el límite de combinaciones evaluadas ('.$this->maxEvaluations.').';

                return null;
            }
            $this->evaluations++;
            $nextSpecs = [...$specs, $slot => $this->partSpecs[$part->id]];
            if (Compatibility::check($nextSpecs) !== []) {
                continue;
            }
            $result = $this->search($bySlot, $order, [...$build, $slot => $part], $nextSpecs);
            if ($result !== null) {
                return $result;
            }
            if ($this->limitReached() || ++$viable >= 8) {
                break;
            }
        }

        return null;
    }

    /** @param array<string, Product> $build */
    public static function specs(array $build): array
    {
        return array_map(fn (Product $p) => $p->compatibilityPart(), $build);
    }
}
