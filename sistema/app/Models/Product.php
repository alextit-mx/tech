<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['sku', 'name', 'slug', 'category_id', 'brand_id', 'short_description', 'description', 'cost', 'cost_currency', 'price', 'price_mode', 'stock', 'is_published', 'is_featured', 'specs', 'warranty_months'];

    protected function casts(): array
    {
        return ['cost' => 'decimal:2', 'price' => 'decimal:2', 'stock' => 'integer', 'is_published' => 'boolean', 'is_featured' => 'boolean', 'specs' => 'array', 'warranty_months' => 'integer'];
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

    /** Precio para el público: null si el producto se cotiza. */
    public function publicPrice(): ?float
    {
        return $this->price_mode === 'show' && $this->price !== null ? (float) $this->price : null;
    }

    public function stockLabel(): string
    {
        return match (true) {
            $this->stock > 3 => 'En existencia',
            $this->stock > 0 => 'Últimas piezas',
            default => 'Sobre pedido',
        };
    }

    public function imageUrl(): ?string
    {
        $path = $this->images->first()?->path;

        return $path ? asset('uploads/'.$path) : null;
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
