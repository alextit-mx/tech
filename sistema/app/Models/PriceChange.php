<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceChange extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'user_id', 'old_price', 'new_price', 'old_cost', 'new_cost', 'created_at'];

    protected function casts(): array
    {
        return ['old_price' => 'decimal:2', 'new_price' => 'decimal:2', 'old_cost' => 'decimal:2', 'new_cost' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
