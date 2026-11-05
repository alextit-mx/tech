<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Póliza de mantenimiento: visitas incluidas durante una vigencia. */
class ServicePlan extends Model
{
    protected $fillable = ['folio', 'customer_id', 'name', 'visits_included', 'price', 'starts_at', 'ends_at', 'notes'];

    protected function casts(): array
    {
        return ['visits_included' => 'integer', 'price' => 'decimal:2', 'starts_at' => 'date', 'ends_at' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(ServiceVisit::class)->latest('visited_at');
    }

    public function remainingVisits(): int
    {
        return max(0, $this->visits_included - ($this->visits_count ?? $this->visits()->count()));
    }

    public function isActive(): bool
    {
        return $this->ends_at->endOfDay()->isFuture();
    }
}
