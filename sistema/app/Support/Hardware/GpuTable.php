<?php

namespace App\Support\Hardware;

class GpuTable
{
    // NVIDIA especificaciones GeForce / RTX profesionales (nvidia.com); AMD Radeon especificaciones (amd.com). Conector típico: siempre supuesto, verificar ensamblador y variante.
    public const DATA = [
        'GEFORCE N210' => ['tdp_w' => 31, 'recommended_psu_w' => 300, 'tier' => 1, 'power_connector' => 'ninguno'],
        'GT 710' => ['tdp_w' => 19, 'recommended_psu_w' => 300, 'tier' => 1, 'power_connector' => 'ninguno'],
        'GT 730' => ['tdp_w' => 38, 'recommended_psu_w' => 300, 'tier' => 1, 'power_connector' => 'ninguno'],
        'GT 1030' => ['tdp_w' => 30, 'recommended_psu_w' => 300, 'tier' => 1, 'power_connector' => 'ninguno'],
        'QUADRO P1000' => ['tdp_w' => 47, 'recommended_psu_w' => 300, 'tier' => 1, 'power_connector' => 'ninguno'],
        'RTX A400' => ['tdp_w' => 50, 'recommended_psu_w' => 300, 'tier' => 1, 'power_connector' => 'ninguno'],
        'RTX 3050' => ['tdp_w' => 130, 'recommended_psu_w' => 550, 'tier' => 1, 'power_connector' => '8-pin'],
        'RTX 4060' => ['tdp_w' => 115, 'recommended_psu_w' => 550, 'tier' => 2, 'power_connector' => '8-pin'],
        'RTX 5050' => ['tdp_w' => 130, 'recommended_psu_w' => 550, 'tier' => 2, 'power_connector' => '8-pin'],
        'RTX 5060' => ['tdp_w' => 145, 'recommended_psu_w' => 550, 'tier' => 2, 'power_connector' => '8-pin'],
        'RTX 5060 TI' => ['tdp_w' => 180, 'recommended_psu_w' => 600, 'tier' => 3, 'power_connector' => '8-pin'],
        'RTX 5070' => ['tdp_w' => 250, 'recommended_psu_w' => 650, 'tier' => 4, 'power_connector' => '16-pin'],
        'RTX 5070 TI' => ['tdp_w' => 300, 'recommended_psu_w' => 750, 'tier' => 4, 'power_connector' => '16-pin'],
        'RTX 5080' => ['tdp_w' => 360, 'recommended_psu_w' => 850, 'tier' => 5, 'power_connector' => '16-pin'],
        'RTX 5090' => ['tdp_w' => 575, 'recommended_psu_w' => 1000, 'tier' => 6, 'power_connector' => '16-pin'],
        'RX 6900 XT' => ['tdp_w' => 300, 'recommended_psu_w' => 850, 'tier' => 4, 'power_connector' => '2x8-pin'],
        'RX 7600' => ['tdp_w' => 165, 'recommended_psu_w' => 550, 'tier' => 2, 'power_connector' => '8-pin'],
        'RX 9060 XT' => ['tdp_w' => 182, 'recommended_psu_w' => 450, 'tier' => 3, 'power_connector' => '8-pin'],
        'RX 9070' => ['tdp_w' => 220, 'recommended_psu_w' => 650, 'tier' => 4, 'power_connector' => '2x8-pin'],
        'RX 9070 XT' => ['tdp_w' => 304, 'recommended_psu_w' => 750, 'tier' => 5, 'power_connector' => '2x8-pin'],
    ];
}
