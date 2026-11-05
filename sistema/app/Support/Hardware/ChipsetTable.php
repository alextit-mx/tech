<?php

namespace App\Support\Hardware;

class ChipsetTable
{
    // AMD socket AM4/AM5 chipset compatibility (amd.com); Intel chipset specifications (intel.com). BIOS: verificar siempre lista de CPU y revisión del fabricante de la tarjeta.
    public const DATA = [
        'A320' => ['socket' => 'AM4', 'class' => 1, 'supported' => ['Ryzen 1000', 'Ryzen 2000', 'Ryzen 3000', 'Ryzen 3000G', 'Athlon 3000'], 'bios' => ['Ryzen 5000']],
        'A520' => ['socket' => 'AM4', 'class' => 2, 'supported' => ['Ryzen 3000', 'Ryzen 5000'], 'bios' => []],
        'B450' => ['socket' => 'AM4', 'class' => 2, 'supported' => ['Ryzen 1000', 'Ryzen 2000', 'Ryzen 3000', 'Ryzen 3000G', 'Athlon 3000'], 'bios' => ['Ryzen 5000']],
        'B550' => ['socket' => 'AM4', 'class' => 3, 'supported' => ['Ryzen 3000', 'Ryzen 5000'], 'bios' => []],
        'X570' => ['socket' => 'AM4', 'class' => 4, 'supported' => ['Ryzen 2000', 'Ryzen 3000', 'Ryzen 3000G'], 'bios' => ['Ryzen 5000']],
        'A620' => ['socket' => 'AM5', 'class' => 1, 'supported' => ['Ryzen 7000'], 'bios' => ['Ryzen 8000', 'Ryzen 9000']],
        'B650' => ['socket' => 'AM5', 'class' => 3, 'supported' => ['Ryzen 7000'], 'bios' => ['Ryzen 8000', 'Ryzen 9000']],
        'B650E' => ['socket' => 'AM5', 'class' => 3, 'supported' => ['Ryzen 7000'], 'bios' => ['Ryzen 8000', 'Ryzen 9000']],
        'X670' => ['socket' => 'AM5', 'class' => 4, 'supported' => ['Ryzen 7000'], 'bios' => ['Ryzen 8000', 'Ryzen 9000']],
        'X670E' => ['socket' => 'AM5', 'class' => 4, 'supported' => ['Ryzen 7000'], 'bios' => ['Ryzen 8000', 'Ryzen 9000']],
        'B840' => ['socket' => 'AM5', 'class' => 2, 'supported' => ['Ryzen 7000', 'Ryzen 8000', 'Ryzen 9000'], 'bios' => []],
        'B850' => ['socket' => 'AM5', 'class' => 4, 'supported' => ['Ryzen 7000', 'Ryzen 8000', 'Ryzen 9000'], 'bios' => []],
        'X870' => ['socket' => 'AM5', 'class' => 4, 'supported' => ['Ryzen 7000', 'Ryzen 8000', 'Ryzen 9000'], 'bios' => []],
        'X870E' => ['socket' => 'AM5', 'class' => 4, 'supported' => ['Ryzen 7000', 'Ryzen 8000', 'Ryzen 9000'], 'bios' => []],
        'H510' => ['socket' => 'LGA1200', 'class' => 2, 'supported' => ['Core 10', 'Core 11'], 'bios' => []],
        'B560' => ['socket' => 'LGA1200', 'class' => 2, 'supported' => ['Core 10', 'Core 11'], 'bios' => []],
        'H610' => ['socket' => 'LGA1700', 'class' => 1, 'supported' => ['Core 12'], 'bios' => ['Core 13', 'Core 14']],
        'B660' => ['socket' => 'LGA1700', 'class' => 3, 'supported' => ['Core 12'], 'bios' => ['Core 13', 'Core 14']],
        'H670' => ['socket' => 'LGA1700', 'class' => 3, 'supported' => ['Core 12'], 'bios' => ['Core 13', 'Core 14']],
        'Z690' => ['socket' => 'LGA1700', 'class' => 4, 'supported' => ['Core 12'], 'bios' => ['Core 13', 'Core 14']],
        'B760' => ['socket' => 'LGA1700', 'class' => 3, 'supported' => ['Core 12', 'Core 13'], 'bios' => ['Core 14']],
        'Z790' => ['socket' => 'LGA1700', 'class' => 4, 'supported' => ['Core 12', 'Core 13'], 'bios' => ['Core 14']],
        'H810' => ['socket' => 'LGA1851', 'class' => 1, 'supported' => ['Core Ultra 200'], 'bios' => []],
        'B860' => ['socket' => 'LGA1851', 'class' => 3, 'supported' => ['Core Ultra 200'], 'bios' => []],
        'Z890' => ['socket' => 'LGA1851', 'class' => 4, 'supported' => ['Core Ultra 200'], 'bios' => []],
        'TRX50' => ['socket' => 'sTR5', 'class' => 4, 'supported' => ['Threadripper 7000', 'Threadripper PRO 7000'], 'bios' => ['Threadripper 9000', 'Threadripper PRO 9000']],
        'WRX90' => ['socket' => 'sTR5', 'class' => 4, 'supported' => ['Threadripper PRO 7000'], 'bios' => ['Threadripper PRO 9000']],
    ];
}
