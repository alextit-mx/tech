<?php

namespace App\Support\Hardware;

class Compatibility
{
    public const PLATFORM_MEMORY = ['AM5' => ['DDR5'], 'LGA1851' => ['DDR5'], 'sTR5' => ['DDR5'], 'AM4' => ['DDR4'], 'LGA1200' => ['DDR4'], 'LGA1151' => ['DDR4'], 'LGA1700' => ['DDR4', 'DDR5']];

    /** @return list<array{level: string, code: string, message: string, slots: array}> */
    public static function check(array $parts): array
    {
        $out = [];
        $s = array_map(fn ($p) => $p['specs'] ?? [], $parts);
        $cpu = $s['cpu'] ?? [];
        $mb = $s['motherboard'] ?? [];
        $ram = $s['ram'] ?? [];
        $gpu = $s['gpu'] ?? [];
        $psu = $s['psu'] ?? [];
        $cooler = $s['cooler'] ?? [];
        $case = $s['case'] ?? [];
        $add = function (string $level, string $code, string $message, array $fields) use (&$out, $parts) {
            if ($level === 'incompatible') {
                foreach ($fields as $slot => $keys) {
                    if (array_intersect($keys, $parts[$slot]['estimated'] ?? [])) {
                        $level = 'riesgo';
                        $message .= ' (dato por confirmar)';
                        break;
                    }
                }
            }
            $out[] = ['level' => $level, 'code' => $code, 'message' => $message, 'slots' => array_keys($fields)];
        };
        $power = (float) ($cpu['power_w'] ?? (($cpu['tdp_w'] ?? 0) * 1.35));
        $platform = self::PLATFORM_MEMORY[$cpu['socket'] ?? ''] ?? [];
        if (isset($cpu['socket'], $mb['socket']) && $cpu['socket'] !== $mb['socket']) {
            $add('incompatible', 'socket', "El procesador es {$cpu['socket']} y la tarjeta madre {$mb['socket']}.", ['cpu' => ['socket'], 'motherboard' => ['socket']]);
        }
        if ($ram && ! $mb && $platform && ! in_array($ram['memory_type'] ?? null, $platform, true)) {
            $add('incompatible', 'socket', 'El procesador '.$cpu['socket'].' usa '.implode(' o ', $platform).', no '.($ram['memory_type'] ?? 'memoria desconocida').'.', ['cpu' => ['socket'], 'ram' => ['memory_type']]);
        }
        if (isset($ram['memory_type'], $mb['memory_type']) && $ram['memory_type'] !== $mb['memory_type']) {
            $add('incompatible', 'memory_type', "La memoria {$ram['memory_type']} no entra en una tarjeta {$mb['memory_type']}.", ['ram' => ['memory_type'], 'motherboard' => ['memory_type']]);
        }
        if (isset($mb['form_factor'], $case['form_factors']) && ! in_array($mb['form_factor'], $case['form_factors'], true)) {
            $add('incompatible', 'form_factor', "La tarjeta madre {$mb['form_factor']} no cabe en el gabinete.", ['motherboard' => ['form_factor'], 'case' => ['form_factors']]);
        }
        if (isset($ram['modules'], $mb['ram_slots']) && $ram['modules'] > $mb['ram_slots']) {
            $add('incompatible', 'ram_slots', "La RAM tiene {$ram['modules']} módulos y la tarjeta madre solo {$mb['ram_slots']} ranuras.", ['ram' => ['modules'], 'motherboard' => ['ram_slots']]);
        }
        if (isset($ram['capacity_gb'], $mb['max_ram_gb']) && $ram['capacity_gb'] > $mb['max_ram_gb']) {
            $add('incompatible', 'ram_max', "La RAM suma {$ram['capacity_gb']} GB y la tarjeta madre admite {$mb['max_ram_gb']} GB.", ['ram' => ['capacity_gb'], 'motherboard' => ['max_ram_gb']]);
        }
        $chip = ChipsetTable::DATA[$mb['chipset'] ?? ''] ?? null;
        if ($chip && isset($cpu['series'])) {
            if (in_array($cpu['series'], $chip['bios'], true)) {
                $add('riesgo', 'chipset_cpu', "El chipset {$mb['chipset']} requiere verificar o actualizar el BIOS para {$cpu['series']}.", ['cpu' => ['series'], 'motherboard' => ['chipset']]);
            } elseif (! in_array($cpu['series'], $chip['supported'], true)) {
                $add('incompatible', 'chipset_cpu', "El chipset {$mb['chipset']} no soporta {$cpu['series']}.", ['cpu' => ['series'], 'motherboard' => ['chipset']]);
            }
            $limit = [1 => 88, 2 => 142, 3 => 200][$chip['class']] ?? PHP_INT_MAX;
            if ($chip['class'] < ($cpu['tier'] ?? 0) - 2 || $power > $limit) {
                $add('desbalance', 'board_class', "El chipset {$mb['chipset']} (clase {$chip['class']}) queda corto para el procesador de nivel ".($cpu['tier'] ?? 0)." y {$power} W máximos.", ['cpu' => ['tier', 'power_w', 'tdp_w'], 'motherboard' => ['chipset']]);
            }
        }
        if (isset($cpu['socket'], $cooler['sockets']) && ! in_array($cpu['socket'], $cooler['sockets'], true)) {
            $add('incompatible', 'cooler_socket', "El enfriamiento no es para socket {$cpu['socket']}.", ['cpu' => ['socket'], 'cooler' => ['sockets']]);
        }
        if ($cpu && isset($cooler['max_tdp_w']) && $cooler['max_tdp_w'] < $power) {
            $add('incompatible', 'cooler_power', "El enfriamiento disipa {$cooler['max_tdp_w']} W y el procesador puede consumir {$power} W.", ['cpu' => [isset($cpu['power_w']) ? 'power_w' : 'tdp_w'], 'cooler' => ['max_tdp_w']]);
        }
        if (isset($gpu['length_mm'], $case['max_gpu_length_mm'])) {
            $gap = $case['max_gpu_length_mm'] - $gpu['length_mm'];
            if ($gap < 10) {
                $add($gap < 0 ? 'incompatible' : 'riesgo', 'gpu_length', "La tarjeta de video mide {$gpu['length_mm']} mm y el gabinete admite {$case['max_gpu_length_mm']} mm.".($gap >= 0 ? " Solo quedan {$gap} mm de margen." : ''), ['gpu' => ['length_mm'], 'case' => ['max_gpu_length_mm']]);
            }
        }
        $consumption = $power + ($gpu['tdp_w'] ?? 0) + 75;
        $minimum = max($gpu['recommended_psu_w'] ?? 0, $consumption);
        $ideal = (int) (ceil($consumption * 1.25 / 50) * 50);
        if (isset($psu['watts'])) {
            if ($psu['watts'] < $minimum) {
                $add('incompatible', 'psu_watts', "La fuente entrega {$psu['watts']} W y el armado requiere al menos {$minimum} W.", ['cpu' => [isset($cpu['power_w']) ? 'power_w' : 'tdp_w'], 'gpu' => ['tdp_w', 'recommended_psu_w'], 'psu' => ['watts']]);
            } elseif ($psu['watts'] < $ideal) {
                $add('desbalance', 'psu_watts', "La fuente entrega {$psu['watts']} W; se recomiendan {$ideal} W para tener margen.", ['psu' => ['watts'], 'cpu' => ['power_w'], 'gpu' => ['tdp_w']]);
            }
        }
        if ($psu && $gpu) {
            $cert = $psu['certification'] ?? 'Sin certificación';
            if ($cert === 'Sin certificación' && ($gpu['tdp_w'] ?? 0) > 150) {
                $add('incompatible', 'psu_quality', "La fuente sin certificación no es adecuada para una tarjeta de video de {$gpu['tdp_w']} W.", ['psu' => ['certification'], 'gpu' => ['tdp_w']]);
            } elseif (($gpu['tier'] ?? 0) >= 4 && ! in_array($cert, ['80+ Gold', '80+ Platinum', '80+ Titanium'], true)) {
                $add('desbalance', 'psu_quality', "La tarjeta de video de nivel {$gpu['tier']} requiere una fuente 80+ Gold o superior; la actual es {$cert}.", ['gpu' => ['tier'], 'psu' => ['certification']]);
            }
            $connector = $gpu['power_connector'] ?? 'ninguno';
            $eight = (int) ($psu['pcie_8pin'] ?? 0);
            $required = ['8-pin' => 1, '2x8-pin' => 2, '3x8-pin' => 3][$connector] ?? 0;
            if ($required > $eight) {
                $add('incompatible', 'gpu_power_connector', "La tarjeta de video requiere {$required} conectores de 8 pines y la fuente tiene {$eight}.", ['gpu' => ['power_connector'], 'psu' => ['pcie_8pin']]);
            }
            if ($connector === '16-pin' && ! ($psu['pcie_16pin'] ?? false)) {
                $needed = (int) ceil(($gpu['tdp_w'] ?? 0) / 150);
                $adapter = $eight >= $needed;
                $add($adapter ? 'riesgo' : 'incompatible', 'gpu_power_connector', 'La tarjeta de video usa 16 pines y la fuente no tiene conector nativo. '.($adapter ? "Funciona con el adaptador de la tarjeta y {$needed} conectores de 8 pines." : "Necesita {$needed} conectores de 8 pines para el adaptador y la fuente tiene {$eight}."), ['gpu' => ['power_connector', 'tdp_w'], 'psu' => ['pcie_16pin', 'pcie_8pin']]);
            }
        }
        if ($case['includes_psu'] ?? false) {
            if ($psu) {
                $add('desbalance', 'case_has_psu', 'El gabinete incluye fuente y elegiste otra: pagas dos fuentes.', ['case' => ['includes_psu'], 'psu' => ['watts']]);
            } elseif (($gpu['tdp_w'] ?? 0) > 75) {
                $add('incompatible', 'case_has_psu', "La fuente incluida del gabinete no está confirmada para una tarjeta de video de {$gpu['tdp_w']} W.", ['case' => ['includes_psu'], 'gpu' => ['tdp_w']]);
            }
        }
        if (! isset($s['gpu']) && ($cpu['igpu'] ?? null) === false) {
            $add('incompatible', 'no_video', 'El procesador no tiene gráficos integrados y falta una tarjeta de video.', ['cpu' => ['igpu'], 'gpu' => []]);
        }

        return $out;
    }
}
