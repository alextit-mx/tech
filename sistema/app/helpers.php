<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever('setting:'.$key, fn () => Setting::query()->whereKey($key)->value('value') ?? $default);
    }
}

if (! function_exists('money')) {
    /** $12,499 o $12,499.50 (pesos, sin centavos cuando son cero). */
    function money(float|string|null $amount): string
    {
        $amount = (float) $amount;

        return '$'.number_format($amount, fmod($amount, 1.0) === 0.0 ? 0 : 2);
    }
}
