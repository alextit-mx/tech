<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'sort'];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('shop.home'));
        static::deleted(fn () => Cache::forget('shop.home'));
    }
}
