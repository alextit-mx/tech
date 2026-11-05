<?php

namespace App\Support;

use App\Support\Hardware\Compatibility;

/**
 * Las reglas del configurador (resources/js/builder/rules.ts) del lado del servidor.
 * Recibe slot => specs y devuelve los motivos por los que la build no se puede cotizar.
 */
class BuildValidator
{
    /** Memoria que acepta cada plataforma (igual que PLATFORM_MEMORY en rules.ts). */
    public const PLATFORM_MEMORY = Compatibility::PLATFORM_MEMORY;

    public const LABELS = ['cpu' => 'procesador', 'motherboard' => 'tarjeta madre', 'ram' => 'memoria RAM', 'gpu' => 'tarjeta de video', 'storage' => 'almacenamiento', 'psu' => 'fuente de poder', 'case' => 'gabinete'];

    /** @param  array<string, array<string, mixed>>  $parts */
    public static function errors(array $parts): array
    {
        return array_column(array_filter(self::issues($parts), fn ($issue) => $issue['level'] === 'incompatible'), 'message');
    }

    public static function issues(array $parts): array
    {
        return Compatibility::check(array_map(fn ($part) => isset($part['specs']) ? $part : ['specs' => $part, 'estimated' => []], $parts));
    }

    public static function warnings(array $parts): array
    {
        return array_column(array_filter(self::issues($parts), fn ($issue) => $issue['level'] !== 'incompatible'), 'message');
    }

    /**
     * Piezas que faltan para una PC completa; se anotan en la cotización.
     * El motor verifica por separado la ausencia de salida de video.
     *
     * @return list<string>
     */
    public static function missing(array $parts): array
    {
        $parts = array_map(fn ($part) => $part['specs'] ?? $part, $parts);
        $required = ['cpu', 'motherboard', 'ram', 'storage', 'psu', 'case'];
        if (! ($parts['cpu']['igpu'] ?? false)) {
            $required[] = 'gpu';
        }

        return array_values(array_map(fn ($slot) => self::LABELS[$slot], array_filter($required, fn ($slot) => ! isset($parts[$slot]))));
    }

    public static function consumption(array $parts): int
    {
        $parts = array_map(fn ($part) => $part['specs'] ?? $part, $parts);

        return (int) ceil(($parts['cpu']['power_w'] ?? (($parts['cpu']['tdp_w'] ?? 0) * 1.35)) + ($parts['gpu']['tdp_w'] ?? 0) + 75);
    }
}
