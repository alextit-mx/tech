<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class Folio
{
    /** Siguiente folio del año, p. ej. COT-2026-0001. Llamar dentro de una transacción. */
    public static function next(string $prefix): string
    {
        $key = $prefix.'-'.now()->year;

        DB::table('sequences')->insertOrIgnore(['key' => $key, 'value' => 0]);
        $value = DB::table('sequences')->where('key', $key)->lockForUpdate()->value('value') + 1;
        DB::table('sequences')->where('key', $key)->update(['value' => $value]);

        return sprintf('%s-%04d', $key, $value);
    }
}
