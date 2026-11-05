<?php

namespace App\Models;

use App\Support\BuildValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Equipo armado: una pieza por slot del configurador; precio y disponibilidad salen de sus productos en vivo. */
class Build extends Model
{
    /** Mismo orden que SLOTS en resources/js/builder/rules.ts. */
    public const SLOTS = ['cpu', 'motherboard', 'ram', 'gpu', 'storage', 'cooler', 'psu', 'case'];

    public const SLOT_LABELS = ['cpu' => 'Procesador', 'motherboard' => 'Tarjeta madre', 'ram' => 'Memoria RAM', 'gpu' => 'Tarjeta de video', 'storage' => 'Almacenamiento', 'cooler' => 'Enfriamiento', 'psu' => 'Fuente de poder', 'case' => 'Gabinete'];

    /** Mismos usos que USES en rules.ts. */
    public const USES = ['gaming1080' => 'Juegos en 1080p', 'gaming1440' => 'Juegos en 1440p', 'gaming4k' => 'Juegos en 4K', 'stream' => 'Jugar y transmitir', 'create' => 'Edición, diseño e IA', 'office' => 'Escuela y oficina'];

    protected $fillable = ['name', 'slug', 'tagline', 'use', 'image', 'is_published', 'sort'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'sort' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('shop.home'));
        static::deleted(fn () => Cache::forget('shop.home'));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('slot', 'quantity')->withTrashed();
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_published', true)->orderBy('sort')->orderBy('id');
    }

    /**
     * Productos en el orden de los slots, con el slot como llave. Colección base: en la de Eloquent,
     * only()/except() filtran por id del modelo y no por la llave del slot.
     */
    public function parts(): Collection
    {
        return $this->products->sortBy(fn (Product $p) => array_search($p->pivot->slot, self::SLOTS, true))->keyBy(fn (Product $p) => $p->pivot->slot)->toBase();
    }

    /** slot => product_id, para guardar y para el enlace del configurador. */
    public function syncParts(array $slotProducts): void
    {
        $rows = [];
        foreach (self::SLOTS as $slot) {
            if (! empty($slotProducts[$slot])) {
                $rows[(int) $slotProducts[$slot]] = ['slot' => $slot, 'quantity' => 1];
            }
        }
        $this->products()->sync($rows);
        $this->unsetRelation('products');
        Cache::forget('shop.home');
    }

    /** Una pieza que no se vende (oculta, borrada o sin precio) vuelve todo el equipo "Cotizar". */
    public function total(): ?float
    {
        $sum = 0.0;
        foreach ($this->products as $p) {
            $price = ! $p->trashed() && $p->is_published ? $p->publicPrice() : null;
            if ($price === null) {
                return null;
            }
            $sum += $price * $p->pivot->quantity;
        }

        return $this->products->isEmpty() ? null : $sum;
    }

    /** La peor disponibilidad de sus piezas: 2 = inmediata, 1 = mañana, 0 = sobre pedido. */
    public function availability(): array
    {
        $level = $this->products->min(fn (Product $p) => $p->trashed() ? 0 : ($p->stock > 0 ? 2 : ($p->stock_24h > 0 ? 1 : 0))) ?? 0;

        return [['none', 'Sobre pedido'], ['low', 'Llega mañana'], ['ok', 'Entrega inmediata']][$level];
    }

    public function errors(): array
    {
        return BuildValidator::errors($this->parts()->map(fn (Product $p) => $p->compatibilityPart())->all());
    }

    public function publicationProblems(): array
    {
        $parts = $this->parts();
        $data = $parts->map(fn (Product $p) => $p->compatibilityPart())->all();

        return [...array_column(BuildValidator::issues($data), 'message'),
            ...array_map(fn ($m) => "Falta {$m}.", BuildValidator::missing($data)),
            ...$parts->filter(fn (Product $p) => ! $p->specsVerified())->map(fn (Product $p) => "Ficha pendiente: {$p->name}.")->values()->all()];
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset('uploads/'.$this->image) : null;
    }

    /** Abre el equipo en el configurador para personalizarlo. */
    public function builderUrl(): string
    {
        return url('/arma-tu-pc').'?'.http_build_query(['b' => $this->parts()->pluck('id')->implode('.'), 'n' => $this->name]);
    }

    /** Lo que se lee de un vistazo en la tarjeta: procesador, video, memoria y almacenamiento. */
    public function highlights(): array
    {
        $parts = $this->parts();
        $out = [];
        foreach (['cpu' => 'CPU', 'gpu' => 'GPU', 'ram' => 'RAM', 'storage' => 'SSD'] as $slot => $label) {
            if ($p = $parts->get($slot)) {
                $out[] = ['label' => $label, 'value' => self::shortName($p)];
            }
        }
        if (! $parts->has('gpu') && ($parts->get('cpu')?->specs['igpu'] ?? false)) {
            $out[] = ['label' => 'GPU', 'value' => 'Gráficos integrados'];
        }

        return $out;
    }

    /** "Procesador AMD Ryzen 7 5700X 8 núcleos…" → "AMD Ryzen 7 5700X" (sin la categoría al inicio, máximo 5 palabras). */
    public static function shortName(Product $p): string
    {
        $name = preg_replace('/^(procesador|tarjeta de video|tarjeta madre|memoria( ram)?|ram|ssd|disco( duro)?( estado solido)?|unidad de estado solido|fuente( de poder)?|gabinete|disipador|enfriamiento( liquido)?)\s+/iu', '', $p->name);

        return implode(' ', array_slice(preg_split('/\s+/', (string) $name), 0, 5));
    }
}
