<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever('setting:'.$key, fn () => Setting::query()->whereKey($key)->value('value') ?? $default);
    }
}
