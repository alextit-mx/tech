<?php

namespace App\Support;

class BuilderSpecs
{
    public const SCHEMAS = [
        'cpu' => ['socket' => 'string', 'cores' => 'integer', 'threads' => 'integer', 'tdp_w' => 'integer', 'igpu' => 'boolean', 'tier' => 'tier'],
        'motherboard' => ['socket' => 'string', 'memory_type' => 'memory', 'form_factor' => 'form', 'm2_slots' => 'integer'],
        'ram' => ['memory_type' => 'memory', 'capacity_gb' => 'integer', 'modules' => 'integer', 'speed_mhz' => 'integer'],
        'gpu' => ['tdp_w' => 'integer', 'vram_gb' => 'integer', 'length_mm' => 'integer', 'tier' => 'tier'],
        'storage' => ['type' => 'storage', 'capacity_gb' => 'integer'],
        'psu' => ['watts' => 'integer', 'certification' => 'string'],
        'cooler' => ['type' => 'cooler', 'sockets' => 'list', 'max_tdp_w' => 'integer'],
        'case' => ['form_factors' => 'list', 'max_gpu_length_mm' => 'integer'],
    ];

    public static function valid(?string $slot, mixed $specs): bool
    {
        if (! is_array($specs)) {
            return false;
        }
        if (! $slot || ! isset(self::SCHEMAS[$slot])) {
            return collect($specs)->every(fn ($row) => is_array($row) && isset($row['name'], $row['value']));
        }
        foreach (self::SCHEMAS[$slot] as $key => $type) {
            if ($specs !== [] && ! array_key_exists($key, $specs)) {
                return false;
            }
            if (! array_key_exists($key, $specs)) {
                continue;
            }
            $value = $specs[$key];
            if ($type === 'integer' || $type === 'tier') {
                if (! filter_var($value, FILTER_VALIDATE_INT) || ($type === 'tier' && ($value < 1 || $value > 6))) {
                    return false;
                }
            } elseif ($type === 'boolean' && ! is_bool($value)) {
                return false;
            } elseif ($type === 'list' && ! is_array($value)) {
                return false;
            } elseif (in_array($type, ['memory', 'form', 'storage', 'cooler'], true) && ! in_array($value, match ($type) {
                'memory' => ['DDR4', 'DDR5'],'form' => ['ATX', 'mATX', 'ITX'],'storage' => ['NVMe', 'SATA SSD', 'HDD'],'cooler' => ['aire', 'líquida']
            }, true)) {
                return false;
            } elseif ($type === 'string' && ! is_string($value)) {
                return false;
            }
        }

        return true;
    }
}
