<?php

namespace App\Support;

class BuilderSpecs
{
    public const SCHEMAS = [
        'cpu' => ['series' => 'string', 'power_w' => 'integer', 'socket' => 'string', 'cores' => 'integer', 'threads' => 'integer', 'tdp_w' => 'integer', 'igpu' => 'boolean', 'tier' => 'tier'],
        'motherboard' => ['chipset' => 'string', 'ram_slots' => 'integer', 'max_ram_gb' => 'integer', 'socket' => 'string', 'memory_type' => 'memory', 'form_factor' => 'form', 'm2_slots' => 'integer'],
        'ram' => ['memory_type' => 'memory', 'capacity_gb' => 'integer', 'modules' => 'integer', 'speed_mhz' => 'integer'],
        'gpu' => ['recommended_psu_w' => 'integer', 'power_connector' => 'connector', 'tdp_w' => 'integer', 'vram_gb' => 'integer', 'length_mm' => 'integer', 'tier' => 'tier'],
        'storage' => ['type' => 'storage', 'capacity_gb' => 'integer'],
        'psu' => ['pcie_16pin' => 'boolean', 'pcie_8pin' => 'integer', 'watts' => 'integer', 'certification' => 'certification'],
        'cooler' => ['type' => 'cooler', 'sockets' => 'list', 'max_tdp_w' => 'integer'],
        'case' => ['includes_psu' => 'boolean', 'form_factors' => 'list', 'max_gpu_length_mm' => 'integer'],
    ];

    public const OPTIONAL = ['series', 'power_w', 'chipset', 'ram_slots', 'max_ram_gb', 'recommended_psu_w', 'power_connector', 'pcie_16pin', 'pcie_8pin', 'includes_psu'];

    public const CERTIFICATIONS = ['Sin certificación', '80+', '80+ Bronze', '80+ Silver', '80+ Gold', '80+ Platinum', '80+ Titanium'];

    public const LABELS = [
        'series' => 'Serie', 'power_w' => 'Consumo máximo (W)', 'chipset' => 'Chipset', 'ram_slots' => 'Ranuras de RAM', 'max_ram_gb' => 'RAM máxima (GB)', 'recommended_psu_w' => 'Fuente recomendada (W)', 'power_connector' => 'Conector de energía', 'pcie_16pin' => 'PCIe de 16 pines nativo', 'pcie_8pin' => 'Conectores PCIe de 8 pines', 'includes_psu' => 'Incluye fuente',
        'socket' => 'Socket', 'cores' => 'Núcleos', 'threads' => 'Hilos', 'tdp_w' => 'TDP oficial (W)', 'igpu' => 'Gráficos integrados',
        'tier' => 'Nivel', 'memory_type' => 'Tipo de memoria', 'form_factor' => 'Formato', 'm2_slots' => 'Ranuras M.2',
        'capacity_gb' => 'Capacidad (GB)', 'modules' => 'Módulos', 'speed_mhz' => 'Velocidad (MHz)', 'vram_gb' => 'Memoria de video (GB)',
        'length_mm' => 'Largo (mm)', 'type' => 'Tipo', 'watts' => 'Potencia (W)', 'certification' => 'Certificación',
        'sockets' => 'Sockets compatibles', 'max_tdp_w' => 'Disipa hasta (W)', 'form_factors' => 'Formatos de tarjeta madre',
        'max_gpu_length_mm' => 'Tarjeta de video hasta (mm)',
    ];

    public const OPTIONS = [
        'socket' => ['AM4', 'AM5', 'LGA1151', 'LGA1200', 'LGA1700', 'LGA1851', 'sTR5'],
        'memory' => ['DDR3', 'DDR4', 'DDR5'],
        'form' => ['ATX', 'mATX', 'ITX', 'E-ATX', 'CEB', 'EEB'],
        'storage' => ['NVMe', 'SATA SSD', 'HDD'],
        'cooler' => ['aire', 'líquida'],
        'certification' => self::CERTIFICATIONS,
        'connector' => ['ninguno', '8-pin', '2x8-pin', '3x8-pin', '16-pin'],
    ];

    public static function fields(string $slot): array
    {
        $fields = [];
        foreach (self::SCHEMAS[$slot] ?? [] as $key => $type) {
            $options = self::OPTIONS[match ($key) {
                'socket', 'sockets' => 'socket', 'form_factors' => 'form', default => $type,
            }] ?? [];
            $fields[$key] = ['label' => self::label($key), 'type' => $type, 'options' => $options];
        }

        return $fields;
    }

    public static function editorSchema(): array
    {
        return array_combine(array_keys(self::SCHEMAS), array_map(self::fields(...), array_keys(self::SCHEMAS)));
    }

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public static function valid(?string $slot, mixed $specs): bool
    {
        if (! is_array($specs)) {
            return false;
        }
        if (! $slot || ! isset(self::SCHEMAS[$slot])) {
            return collect($specs)->every(fn ($row) => is_array($row) && isset($row['name'], $row['value']));
        }
        foreach (self::SCHEMAS[$slot] as $key => $type) {
            if ($specs !== [] && ! array_key_exists($key, $specs) && ! in_array($key, self::OPTIONAL, true)) {
                return false;
            }
            if (! array_key_exists($key, $specs)) {
                continue;
            }
            $value = $specs[$key];
            if ($type === 'integer' || $type === 'tier') {
                if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0 || ($type === 'tier' && ($value < 1 || $value > 6))) {
                    return false;
                }
            } elseif ($type === 'boolean' && ! is_bool($value)) {
                return false;
            } elseif ($type === 'list' && ! is_array($value)) {
                return false;
            } elseif (in_array($type, ['memory', 'form', 'storage', 'cooler', 'certification', 'connector'], true) && ! in_array($value, self::OPTIONS[$type], true)) {
                return false;
            } elseif ($type === 'string' && ! is_string($value)) {
                return false;
            }
        }

        return true;
    }
}
