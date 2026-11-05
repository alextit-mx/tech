<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['warranty_id', 'description', 'serial', 'months'];

    protected $appends = ['ends_at', 'active'];

    protected $hidden = ['warranty'];

    protected function casts(): array
    {
        return ['months' => 'integer'];
    }

    public function warranty(): BelongsTo
    {
        return $this->belongsTo(Warranty::class);
    }

    public function endsAt(): CarbonInterface
    {
        return $this->warranty->starts_at->copy()->addMonthsNoOverflow($this->months);
    }

    public function isActive(): bool
    {
        return ! $this->warranty->voided_at && $this->endsAt()->endOfDay()->isFuture();
    }

    public function getEndsAtAttribute(): string
    {
        return $this->endsAt()->toDateString();
    }

    public function getActiveAttribute(): bool
    {
        return $this->isActive();
    }
}
