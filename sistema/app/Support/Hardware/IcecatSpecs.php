<?php

namespace App\Support\Hardware;

use App\Models\Product;
use App\Support\BuilderSpecs;
use Illuminate\Support\Str;

class IcecatSpecs
{
    public static function map(string $slot, array $data): array
    {
        $features = [];
        foreach ($data['FeaturesGroups'] ?? [] as $group) {
            foreach ($group['Features'] ?? [] as $feature) {
                $name = $feature['Feature']['Name']['Value'] ?? '';
                $value = $feature['PresentationValue'] ?? $feature['Value'] ?? null;
                if (is_scalar($value) && trim((string) $value) !== '') {
                    $features[self::key($name)] = trim((string) $value);
                }
            }
        }
        $get = function (string ...$names) use ($features): ?string {
            foreach ($names as $name) {
                if (isset($features[self::key($name)])) {
                    return $features[self::key($name)];
                }
            }

            return null;
        };
        $specs = [];
        $set = function (string $field, mixed $value) use (&$specs) {
            if ($value !== null) {
                $specs[$field] = $value;
            }
        };
        if ($slot === 'motherboard') {
            $set('ram_slots', self::number($get('Número de ranuras de memoria')));
            $set('max_ram_gb', self::number($get('Memoria interna máxima')));
            $chipset = preg_replace('/^(?:AMD|Intel)\s+/i', '', $get('Chipset') ?? '');
            $set('chipset', $chipset !== '' ? strtoupper($chipset) : null);
            $sockets = self::sockets($get('Socket de procesador'));
            $set('socket', count($sockets) === 1 ? $sockets[0] : null);
            $set('memory_type', self::memory($get('tipos de memoria compatibles')));
            $forms = self::forms($get('Factor de forma'));
            $set('form_factor', count($forms) === 1 ? $forms[0] : null);
            $set('m2_slots', self::number($get('Número de ranuras M.2 (M)')));
        } elseif ($slot === 'ram') {
            $set('capacity_gb', self::number($get('Memoria interna')));
            if (preg_match('/^(\d+)\s*[x×]\s*(\d+)\s*GB$/iu', $get('Diseño de memoria (módulos x tamaño)') ?? '', $m)) {
                $set('modules', (int) $m[1]);
                $set('capacity_gb', (int) $m[1] * (int) $m[2]);
            }
            $set('memory_type', self::memory($get('Tipo de memoria interna')));
            $set('speed_mhz', self::number($get('Velocidad de memoria del reloj')));
        } elseif ($slot === 'gpu') {
            $set('length_mm', self::number($get('Longitud')));
            $set('tdp_w', self::number($get('Consumo energético', 'Potencia de diseño térmico (TDP)')));
            $set('recommended_psu_w', self::number($get('Suministro de energía al sistema mínimo')));
            $set('vram_gb', self::number($get('Capacidad memoria de adaptador gráfico')));
            $connector = self::key($get('Conectores de energia suplementario') ?? '');
            if (preg_match('/^(?:([1-3])\s*x\s*)?(8|16)[ -]pin$/', $connector, $m)) {
                $count = (int) ($m[1] ?: 1);
                $set('power_connector', $m[2] === '16' ? ($count === 1 ? '16-pin' : null) : ($count === 1 ? '8-pin' : $count.'x8-pin'));
            } elseif (in_array($connector, ['ninguno', 'no', '0'], true)) {
                $set('power_connector', 'ninguno');
            }
        } elseif ($slot === 'psu') {
            $set('watts', self::number($get('Potencia total')));
            $cert = str_replace(['80 PLUS', '80 Plus', '80 plus'], '80+', $get('Certificación 80 PLUS') ?? '');
            foreach (BuilderSpecs::CERTIFICATIONS as $allowed) {
                if (strcasecmp($cert, $allowed) === 0) {
                    $set('certification', $allowed);
                }
            }
            $eight = self::number($get('Conectores de poder PCI Express (8 pin)'));
            $sixPlusTwo = self::number($get('Conectores de poder PCI Express (6 + 2 pin)'));
            $set('pcie_8pin', $eight !== null || $sixPlusTwo !== null ? ($eight ?? 0) + ($sixPlusTwo ?? 0) : null);
            $sixteen = self::number($get('Conectores de alimentación PCI Express (16 pines)'));
            $set('pcie_16pin', $sixteen !== null ? $sixteen > 0 : null);
        } elseif ($slot === 'storage') {
            $interface = implode(' ', array_filter([
                $get('Interfaz', 'Interfaz de unidad de almacenamiento', 'Interfaz de disco duro', 'SSD interface', 'Hard drive interface'),
                $get('Tipo de unidad', 'Tipo de unidad de almacenamiento', 'Tipo de almacenamiento', 'Storage media', 'Drive type'),
                $get('Factor de forma de disco SSD', 'Factor de forma de la unidad SSD', 'SSD form factor'),
            ]));
            $normalized = strtoupper($interface);
            if (preg_match('/NVME|PCI[ -]?E|M\.2/', $normalized) || self::boolean($get('NVMe', 'NVM Express (NVMe)')) === true) {
                $set('type', 'NVMe');
            } elseif (preg_match('/HDD|DISCO DURO|HARD DISK/', $normalized) || self::number($get('Velocidad de rotación de disco duro', 'Hard drive speed')) !== null) {
                $set('type', 'HDD');
            } elseif (str_contains($normalized, 'SATA')) {
                $set('type', 'SATA SSD');
            }
            $capacity = $get('Capacidad del SSD', 'Capacidad de disco duro', 'Capacidad', 'SSD capacity', 'Hard drive capacity');
            if ($capacity !== null && preg_match('/^(\d+(?:[.,]\d+)?)\s*(TB|GB)\b/i', $capacity, $m)) {
                $set('capacity_gb', (int) round((float) str_replace(',', '.', $m[1]) * (strtoupper($m[2]) === 'TB' ? 1000 : 1)));
            }
        } elseif ($slot === 'case') {
            $set('max_gpu_length_mm', self::number($get('Máxima longitud de la tarjeta de gráficos', 'Longitud máxima de la tarjeta gráfica', 'Longitud máxima de tarjeta de video')));
            $set('includes_psu', self::boolean($get('Fuente de alimentación incluida')));
            $forms = self::forms($get('Formas de factor de tarjeta madre soportadas', 'Factores de forma de la tarjeta madre compatibles'));
            $set('form_factors', $forms ?: null);
        } elseif ($slot === 'cooler') {
            $set('max_tdp_w', self::number($get('Potencia de diseño térmico (TDP)', 'TDP')));
            $sockets = self::sockets($get('Sockets de procesador soportados', 'Socket de procesador'));
            $set('sockets', $sockets ?: null);
        }

        return $specs;
    }

    /** Icecat mejora cada campo; los conflictos permanecen hasta la confirmación del equipo. */
    public static function merge(Product $product, array $official): array
    {
        $specs = $product->specs ?? [];
        $sources = $product->spec_sources ?? [];
        $conflicts = array_column($product->spec_conflicts ?? [], null, 'field');
        $identity = match ($product->category?->builder_slot) {
            'ram' => ['capacity_gb', 'modules'], 'psu' => ['watts'], 'gpu' => ['vram_gb'], 'motherboard' => ['chipset', 'socket'], default => [],
        };
        foreach ($official as $field => $value) {
            $source = $sources[$field] ?? null;
            if ($source === 'manual') {
                continue;
            }
            // El socket tabulado se deriva del chipset leído en el nombre.
            $fromName = $source === 'nombre' || ($field === 'socket' && $source === 'tabla');
            if ($fromName && in_array($field, $identity, true) && array_key_exists($field, $specs) && $specs[$field] !== $value) {
                $conflicts[$field] = ['field' => $field, 'actual' => $specs[$field], 'icecat' => $value];

                continue;
            }
            $specs[$field] = $value;
            $sources[$field] = 'icecat';
        }

        return ['specs' => $specs, 'spec_sources' => $sources, 'spec_conflicts' => array_values($conflicts)];
    }

    private static function key(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }

    private static function number(?string $value): ?int
    {
        if ($value === null || ! preg_match('/^(\d+(?:[.,]\d+)?)(?:\s*[^\d].*)?$/u', $value, $m)) {
            return null;
        }

        return (int) round((float) str_replace(',', '.', $m[1]));
    }

    private static function boolean(?string $value): ?bool
    {
        return match (self::key($value ?? '')) {
            'si', 'yes', 'y', '1' => true, 'no', 'n', '0' => false, default => null,
        };
    }

    private static function memory(?string $value): ?string
    {
        preg_match_all('/\bDDR[345]\b/i', $value ?? '', $m);
        $types = array_values(array_unique(array_map('strtoupper', $m[0])));

        return count($types) === 1 ? $types[0] : null;
    }

    private static function sockets(?string $value): array
    {
        preg_match_all('/\b(?:AM[45]|LGA\s*(?:1151|1200|1700|1851)|sTR5)\b/i', $value ?? '', $m);

        return array_values(array_unique(array_map(fn ($s) => strtoupper($s) === 'STR5' ? 'sTR5' : preg_replace('/\s+/', '', strtoupper($s)), $m[0])));
    }

    private static function forms(?string $value): array
    {
        $forms = [];
        foreach (preg_split('/[,;]+/', self::key($value ?? '')) as $form) {
            $form = preg_replace('/[\s_-]+/', '', $form);
            $normalized = match ($form) {
                'atx' => 'ATX', 'microatx', 'matx' => 'mATX', 'miniitx', 'itx' => 'ITX', 'eatx', 'extendedatx' => 'E-ATX', 'ceb' => 'CEB', 'eeb' => 'EEB', default => null,
            };
            if ($normalized !== null) {
                $forms[] = $normalized;
            }
        }

        return array_values(array_unique($forms));
    }
}
