<?php

namespace App\Support\Hardware;

use App\Models\Product;

class SpecExtractor
{
    /** @return array{specs: array, sources: array} */
    public static function extract(string $slot, string $name): array
    {
        $n = mb_strtoupper($name);
        $specs = $sources = [];
        $set = function (string $key, mixed $value, string $source = 'nombre') use (&$specs, &$sources) {
            $specs[$key] = $value;
            $sources[$key] = $source;
        };
        $match = fn (string $pattern) => preg_match($pattern, $n, $m) ? $m : [];
        if ($slot === 'cpu') {
            // Coincidir el modelo completo evita confundir 9950X3D2 con 9950X3D.
            foreach (CpuTable::DATA as $model => $row) {
                if (preg_match('/(?<![A-Z0-9])'.preg_quote($model, '/').'(?![A-Z0-9])/', $n)) {
                    foreach ($row as $key => $value) {
                        $set($key, $value, 'tabla');
                    }
                    break;
                }
            }
            if (! isset($specs['socket']) && ($m = $match('/\b(AM[45]|LGA\s*1[278]00|LGA\s*1851|STR5)\b/'))) {
                $set('socket', str_replace(' ', '', $m[1]) === 'STR5' ? 'sTR5' : str_replace(' ', '', $m[1]));
            }
        } elseif ($slot === 'motherboard') {
            foreach (ChipsetTable::DATA as $chip => $row) {
                if (preg_match('/\b'.preg_quote($chip, '/').'(?!\d|E)/', $n)) {
                    $set('chipset', $chip);
                    $set('socket', $row['socket'], 'tabla');
                    break;
                }
            }
            if ($m = $match('/(?:\b|(?<=\dX))DDR([345])\b/')) {
                $set('memory_type', 'DDR'.$m[1]);
            }
            if ($m = $match('/\b(\d)\s*X\s*DDR[345]/')) {
                $set('ram_slots', (int) $m[1]);
            }
            if ($m = $match('/\b(\d+)\s*GB\b/')) {
                $set('max_ram_gb', (int) $m[1]);
            }
            if ($match('/\b(?:M-?ATX|MICRO.?ATX|MINI ATX)\b/')) {
                $set('form_factor', 'mATX');
            } elseif ($match('/\b(?:MINI-?)?ITX\b/')) {
                $set('form_factor', 'ITX');
            } elseif ($match('/\b(?:E-ATX|EEB|CEB)\b/')) {
                $set('form_factor', $match('/\b(E-ATX|EEB|CEB)\b/')[1]);
            } elseif ($match('/\bATX\b/')) {
                $set('form_factor', 'ATX');
            }
        } elseif ($slot === 'ram') {
            if ($m = $match('/DDR([345])/')) {
                $set('memory_type', 'DDR'.$m[1]);
            }
            if ($m = $match('/(?<!\d)(\d+)\s*[X×]\s*(\d+)\s*GB/')) {
                $set('modules', (int) $m[1]);
                $set('capacity_gb', (int) $m[1] * (int) $m[2]);
            } elseif ($m = $match('/(?<!\d)(\d+)\s*GB/')) {
                $set('capacity_gb', (int) $m[1]);
                $set('modules', 1, 'supuesto');
            }
            if ($m = $match('/(?<![A-Z0-9])(\d{4,5})(?:\s*MHZ\b|(?=\s|$))/')) {
                if ((int) $m[1] >= 1333 && (int) $m[1] <= 12000) {
                    $set('speed_mhz', (int) $m[1]);
                }
            }
        } elseif ($slot === 'gpu') {
            foreach (GpuTable::DATA as $chip => $row) {
                $pattern = str_replace(' ', '\s*', preg_quote($chip, '/'));
                if ($chip === 'QUADRO P1000') {
                    $pattern .= '(?:V2)?';
                }
                if (preg_match('/\b'.$pattern.'(?![A-Z0-9]|\s*(?:TI|SUPER|XT|XTX)\b)/', $n)) {
                    foreach ($row as $key => $value) {
                        $set($key, $value, $key === 'power_connector' ? 'supuesto' : 'tabla');
                    }
                    break;
                }
            }
            if ($m = $match('/\b(\d+)\s*GB\b/')) {
                $set('vram_gb', (int) $m[1]);
            }
            $set('length_mm', 340, 'supuesto');
            if ($m = $match('/\b(\d{2,3})\s*MM\b/')) {
                $set('length_mm', (int) $m[1]);
            }
            if ($m = $match('/\b(16-PIN|[23]X8-PIN|8-PIN|NINGUNO)\b/')) {
                $set('power_connector', strtolower($m[1]));
            }
        } elseif ($slot === 'psu') {
            if (preg_match_all('/(?<![A-Z0-9-])(\d{3,4})\s*W\b/', $n, $matches)) {
                foreach ($matches[1] as $w) {
                    if ($w >= 300 && $w <= 1600) {
                        $set('watts', (int) $w);
                        break;
                    }
                }
            }
            $set('certification', 'Sin certificación', 'supuesto');
            $set('pcie_16pin', false, 'supuesto');
            $set('pcie_8pin', 0, 'supuesto');
            foreach (PsuTable::DATA as $pattern => [$cert, $native]) {
                if (preg_match('/'.$pattern.'/', $n)) {
                    $set('certification', $cert, 'tabla');
                    if ($native) {
                        $set('pcie_16pin', true, 'tabla');
                    }
                    break;
                }
            }
            foreach (PsuTable::CONNECTORS as $pattern => [$eight, $sixteen]) {
                if (preg_match('/'.$pattern.'/', $n)) {
                    $set('pcie_8pin', $eight, 'tabla');
                    $set('pcie_16pin', $sixteen, 'tabla');
                    break;
                }
            }
            if ($m = $match('/\b(TITANIUM|PLATINUM|GOLD|SILVER|BRONZE|BRONCE)\b/')) {
                $set('certification', '80+ '.ucfirst(strtolower($m[1] === 'BRONCE' ? 'BRONZE' : $m[1])));
            } elseif ($match('/80\s*\+|80\s*PLUS/')) {
                $set('certification', '80+');
            }
            if (($sources['pcie_16pin'] ?? '') !== 'tabla' && $match('/ATX\s*3\.[01]|PCI(?:E|EX)\s*5(?:\.[01])?|16[ -]PIN|12V[ -]?(?:2X6|HPWR)/')) {
                $set('pcie_16pin', true);
            }
            if ($m = $match('/\b(\d)\s*X\s*8[ -]PIN/')) {
                $set('pcie_8pin', (int) $m[1]);
            }
        } elseif ($slot === 'cooler') {
            $set('type', $match('/LIQUID|LÍQUID|AIO|WATER/') ? 'líquida' : 'aire');
            $set('max_tdp_w', 95, 'supuesto');
            if ($m = $match('/\b(\d{2,3})\s*W\b/')) {
                $set('max_tdp_w', (int) $m[1]);
            }
            foreach (CoolerTable::DATA as $pattern => $row) {
                if (preg_match('/'.$pattern.'/', $n)) {
                    foreach ($row as $key => $value) {
                        $set($key, $value, 'tabla');
                    }
                    break;
                }
            }
            if (! isset($specs['sockets'])) {
                if (preg_match_all('/\b(?:AM[45]|LGA\s*(?:1151|1200|1700|1851)|STR5)\b/', $n, $m)) {
                    $set('sockets', array_values(array_unique(array_map(fn ($s) => $s === 'STR5' ? 'sTR5' : str_replace(' ', '', $s), $m[0]))));
                } else {
                    $sockets = [];
                    if ($match('/\bAMD\b/')) {
                        $sockets = ['AM4', 'AM5'];
                    }
                    if ($match('/\bINTEL\b/')) {
                        $sockets = [...$sockets, 'LGA1700', 'LGA1851', 'LGA1200'];
                    }
                    if ($sockets) {
                        $set('sockets', $sockets, 'supuesto');
                    }
                }
            }
        } elseif ($slot === 'case') {
            $mini = (bool) $match('/MINI|MICRO|\bM-?ATX\b/');
            $set('form_factors', $mini ? ['mATX', 'ITX'] : ['ATX', 'mATX', 'ITX'], 'supuesto');
            if ($match('/\bE-ATX\b/')) {
                $set('form_factors', ['E-ATX', 'ATX', 'mATX', 'ITX']);
            } elseif ($match('/\b(?:M-?ATX|MICRO[ -]+ATX|MINI[ -]TORRE)\b/')) {
                $set('form_factors', ['mATX', 'ITX']);
            } elseif ($match('/\bATX\b/')) {
                $set('form_factors', ['ATX', 'mATX', 'ITX']);
            } elseif ($match('/\b(?:MINI-)?ITX\b/')) {
                $set('form_factors', ['ITX']);
            }
            $set('max_gpu_length_mm', $mini ? 280 : 320, 'supuesto');
            if ($match('/\b(?:S\s*\/\s*FUENTE|SIN\s+FUENTE|S\s*\/\s*F)\b/')) {
                $set('includes_psu', false);
            } elseif ($match('/C\s*\/\s*FUENTE|CON\s+FUENTE|INCLUYE\s+FUENTE|\b\d{3,4}\s*W\b/')) {
                $set('includes_psu', true);
            } else {
                $set('includes_psu', false, 'supuesto');
            }
        } elseif ($slot === 'storage') {
            if ($m = $match('/\b(\d+(?:\.\d+)?)\s*(TB|GB)\b/')) {
                $set('capacity_gb', (int) round((float) $m[1] * ($m[2] === 'TB' ? 1000 : 1)));
            }
            if ($match('/NVME|PCI[ -]?E/')) {
                $set('type', 'NVMe');
            } elseif ($match('/SSD|ESTADO S[ÓO]LIDO/u')) {
                $set('type', 'SATA SSD');
            } elseif ($match('/HDD|DISCO DURO|\bDD\b|\d+\s*RPM\b|\bRPM\b|3[.,]5(?:[\"”]|\b)/')) {
                $set('type', 'HDD');
            } elseif ($match('/SATA/')) {
                $set('type', 'SATA SSD');
            }
        }

        return ['specs' => $specs, 'sources' => $sources];
    }

    /** Mezcla por campo; datos confirmados o enriquecidos se conservan incluso si el nombre cambió. */
    public static function merge(Product $product, array $derived): array
    {
        $specs = $product->specs ?? [];
        $sources = $product->spec_sources ?? [];
        foreach ($derived['specs'] as $key => $value) {
            if (! in_array($sources[$key] ?? '', ['manual', 'icecat'], true)) {
                $specs[$key] = $value;
                $sources[$key] = $derived['sources'][$key];
            }
        }

        foreach ($specs as $key => $value) {
            if (! isset($sources[$key]) || $sources[$key] === '') {
                $sources[$key] = 'supuesto';
            }
        }

        return ['specs' => $specs, 'spec_sources' => $sources];
    }
}
