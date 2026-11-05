<?php

namespace App\Models;

use App\Support\ProductSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    public bool $modelFromIcecat = false;

    protected $fillable = ['sku', 'name', 'model', 'model_source', 'slug', 'category_id', 'brand_id', 'short_description', 'description', 'cost', 'cost_currency', 'price', 'price_mode', 'stock', 'stock_24h', 'is_published', 'is_featured', 'specs', 'spec_sources', 'spec_conflicts', 'warranty_months'];

    protected function casts(): array
    {
        return ['cost' => 'decimal:2', 'price' => 'decimal:2', 'stock' => 'integer', 'stock_24h' => 'integer', 'is_published' => 'boolean', 'is_featured' => 'boolean', 'specs' => 'array', 'spec_sources' => 'array', 'spec_conflicts' => 'array', 'warranty_months' => 'integer'];
    }

    public const CRITICAL_SPECS = [
        'cpu' => ['socket', 'series', 'tdp_w', 'power_w', 'igpu', 'tier'],
        'motherboard' => ['socket', 'chipset', 'memory_type', 'form_factor', 'ram_slots'],
        'ram' => ['memory_type', 'capacity_gb', 'modules'],
        'gpu' => ['tdp_w', 'recommended_psu_w', 'length_mm', 'power_connector', 'tier'],
        'psu' => ['watts', 'certification', 'pcie_16pin', 'pcie_8pin'],
        'cooler' => ['sockets', 'max_tdp_w'],
        'case' => ['form_factors', 'max_gpu_length_mm', 'includes_psu'],
        'storage' => ['type', 'capacity_gb'],
    ];

    public function estimatedSpecs(): array
    {
        $missing = array_filter(self::CRITICAL_SPECS[$this->category?->builder_slot] ?? [], fn ($key) => ! array_key_exists($key, $this->specs ?? []) || ($this->specs[$key] ?? null) === null || ($this->specs[$key] ?? null) === '' || ! in_array($this->spec_sources[$key] ?? null, ['manual', 'icecat', 'tabla', 'nombre'], true));

        return array_values(array_unique([...$missing, ...array_column($this->spec_conflicts ?? [], 'field')]));
    }

    public function specsVerified(): bool
    {
        return $this->estimatedSpecs() === [];
    }

    public function compatibilityPart(): array
    {
        return ['specs' => $this->specs ?? [], 'estimated' => array_values(array_unique([...$this->estimatedSpecs(), ...array_keys(array_filter($this->spec_sources ?? [], fn ($source) => $source === 'supuesto'))]))];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort');
    }

    public function priceChanges(): HasMany
    {
        return $this->hasMany(PriceChange::class)->latest();
    }

    public function builds(): BelongsToMany
    {
        return $this->belongsToMany(Build::class)->withPivot('slot', 'quantity');
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if ($product->isDirty('model') && ! $product->isDirty('model_source')) {
                $product->model_source = $product->modelFromIcecat ? 'icecat' : 'manual';
            }
            foreach (['brand', 'category'] as $relation) {
                if ($product->isDirty($relation.'_id')) {
                    $product->unsetRelation($relation);
                }
            }
            $product->search_text = ProductSearch::text($product);
        });
        // La portada de la tienda se guarda 5 min en caché; cualquier cambio de producto la renueva.
        static::saved(function (Product $product) {
            $product->modelFromIcecat = false;
            Cache::forget('shop.home');
        });
        static::deleted(fn () => Cache::forget('shop.home'));
    }

    /** Insignia de condición para la tienda (viene de la lista de precios en short_description). */
    public function condition(): ?string
    {
        return preg_match('/caja abierta|remate|descontinuad/i', (string) $this->short_description) ? $this->short_description : null;
    }

    /** Precio para el público: null si el producto se cotiza. */
    public function publicPrice(): ?float
    {
        return $this->price_mode === 'show' && $this->price !== null ? (float) $this->price : null;
    }

    /** stock = en el local (entrega inmediata); stock_24h = llega al día siguiente. */
    public function stockLabel(): string
    {
        return match ($this->stockLevel()) {
            'ok' => 'Entrega inmediata',
            'low' => 'Llega mañana',
            default => 'Sobre pedido',
        };
    }

    /** ok (verde) · low (ámbar) · none (gris): lo usan las etiquetas de tienda, ficha, carrito y configurador. */
    public function stockLevel(): string
    {
        return $this->stock > 0 ? 'ok' : ($this->stock_24h > 0 ? 'low' : 'none');
    }

    public function imageUrl(): ?string
    {
        $path = $this->images->first()?->path;

        return $path ? asset('uploads/'.$path) : null;
    }

    /** Foto del producto o la imagen genérica de la marca. */
    public function imageOrPlaceholder(): string
    {
        return $this->imageUrl() ?? asset('img/producto-sin-foto.svg');
    }

    public function costInMxn(): ?float
    {
        return $this->cost === null ? null : (float) $this->cost * ($this->cost_currency === 'USD' ? (float) setting('exchange_rate', 1) : 1);
    }

    public function margin(): ?float
    {
        $net = $this->price === null ? null : (float) $this->price / 1.16;
        $cost = $this->costInMxn();

        return $net && $cost !== null ? ($net - $cost) / $net : null;
    }
}
