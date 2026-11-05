<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warranty extends Model
{
    protected $fillable = ['folio', 'customer_id', 'user_id', 'quote_id', 'service_order_id', 'starts_at', 'terms', 'voided_at', 'void_reason'];

    protected $appends = ['status'];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'voided_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        // chaperone(): cada partida conoce su garantía sin otra consulta (la vigencia depende de starts_at).
        return $this->hasMany(WarrantyItem::class)->chaperone();
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** Vigente si alguna partida sigue cubierta; la vigencia se calcula por partida. */
    protected function status(): Attribute
    {
        return Attribute::get(function () {
            if ($this->voided_at) {
                return 'void';
            }
            if (! $this->relationLoaded('items')) {
                return null;
            }

            return $this->items->contains(fn (WarrantyItem $item) => $item->isActive()) ? 'active' : 'expired';
        });
    }
}
