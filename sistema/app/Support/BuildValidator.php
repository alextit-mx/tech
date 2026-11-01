<?php

namespace App\Support;

/**
 * Las reglas del configurador (resources/js/builder/rules.ts) del lado del servidor.
 * Recibe slot => specs y devuelve los motivos por los que la build no se puede cotizar.
 */
class BuildValidator
{
    public const LABELS = ['cpu' => 'procesador', 'motherboard' => 'tarjeta madre', 'ram' => 'memoria RAM', 'gpu' => 'tarjeta de video', 'storage' => 'almacenamiento', 'psu' => 'fuente de poder', 'case' => 'gabinete'];

    /** @param  array<string, array<string, mixed>>  $parts */
    public static function errors(array $parts): array
    {
        $errors = [];
        $cpu = $parts['cpu'] ?? null;
        $mb = $parts['motherboard'] ?? null;
        $ram = $parts['ram'] ?? null;
        $gpu = $parts['gpu'] ?? null;
        $cooler = $parts['cooler'] ?? null;
        $case = $parts['case'] ?? null;
        $psu = $parts['psu'] ?? null;

        $required = ['cpu', 'motherboard', 'ram', 'storage', 'psu', 'case'];
        if (! ($cpu['igpu'] ?? false)) {
            $required[] = 'gpu';
        }
        foreach ($required as $slot) {
            if (! isset($parts[$slot])) {
                $errors[] = 'Falta '.self::LABELS[$slot].'.';
            }
        }

        if ($cpu && $mb && ($cpu['socket'] ?? null) !== ($mb['socket'] ?? null)) {
            $errors[] = "El procesador es {$cpu['socket']} y la tarjeta madre {$mb['socket']}.";
        }
        if ($ram && $mb && ($ram['memory_type'] ?? null) !== ($mb['memory_type'] ?? null)) {
            $errors[] = "La memoria {$ram['memory_type']} no entra en una tarjeta {$mb['memory_type']}.";
        }
        if ($mb && $case && ! in_array($mb['form_factor'] ?? null, (array) ($case['form_factors'] ?? []), true)) {
            $errors[] = "La tarjeta madre {$mb['form_factor']} no cabe en el gabinete.";
        }
        if ($gpu && $case && (int) ($gpu['length_mm'] ?? 0) > (int) ($case['max_gpu_length_mm'] ?? 0)) {
            $errors[] = 'La tarjeta de video no cabe en el gabinete.';
        }
        if ($cooler && $cpu && ! in_array($cpu['socket'] ?? null, (array) ($cooler['sockets'] ?? []), true)) {
            $errors[] = "El enfriamiento no es para socket {$cpu['socket']}.";
        }
        if ($cooler && $cpu && (int) ($cpu['tdp_w'] ?? 0) > (int) ($cooler['max_tdp_w'] ?? 0)) {
            $errors[] = 'El enfriamiento no alcanza para el procesador.';
        }
        if ($psu && (int) ($psu['watts'] ?? 0) < self::consumption($parts)) {
            $errors[] = 'La fuente de poder no alcanza para la build.';
        }

        return $errors;
    }

    public static function consumption(array $parts): int
    {
        return (int) ($parts['cpu']['tdp_w'] ?? 0) + (int) ($parts['gpu']['tdp_w'] ?? 0) + 75;
    }
}
