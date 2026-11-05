<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceVisit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['service_plan_id', 'user_id', 'visited_at', 'device', 'work'];

    protected function casts(): array
    {
        return ['visited_at' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
