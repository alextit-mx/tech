<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Banner del carrusel de la tienda (solo imagen; el texto va dentro de la imagen). */
class Banner extends Model
{
    protected $fillable = ['image', 'image_mobile', 'alt', 'link', 'sort', 'is_active', 'starts_at', 'ends_at'];

    protected $appends = ['image_url', 'image_mobile_url'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'starts_at' => 'date', 'ends_at' => 'date', 'sort' => 'integer'];
    }

    /** Activos y dentro de su vigencia (si la tienen). */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhereDate('starts_at', '<=', today()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', today()))
            ->orderBy('sort')->orderBy('id');
    }

    public function getImageUrlAttribute(): string
    {
        return asset('uploads/'.$this->image);
    }

    public function getImageMobileUrlAttribute(): ?string
    {
        return $this->image_mobile ? asset('uploads/'.$this->image_mobile) : null;
    }
}
