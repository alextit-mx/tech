<?php

namespace App\Support\Hardware;

class CoolerTable
{
    private const MAINSTREAM = ['LGA1700', 'LGA1200', 'LGA1151', 'LGA1150', 'LGA1155', 'AM5', 'AM4'];

    private const CURRENT = ['LGA1851', ...self::MAINSTREAM];

    private const HEDT = ['LGA2066', 'LGA2011-v3', 'LGA2011', ...self::MAINSTREAM];

    /** Solo se incluye max_tdp_w cuando el fabricante declara capacidad térmica; el radiador no determina el TDP. */
    public const DATA = [
        // DeepCool, fichas AK/AG, «Technical Spec / Application For» y «heat dissipation» (deepcool.com/products/Cooling/cpuaircoolers).
        // G2/SE/V2 se separan: no heredan la cifra térmica de la primera generación.
        'DEEPCOOL.*\bAK400\s+G2\b' => ['sockets' => self::CURRENT],
        'DEEPCOOL.*\bAK620\s+G2\b' => ['sockets' => self::CURRENT],
        'DEEPCOOL.*\bAK(?:400|620)\s+DIGITAL SE\b' => ['sockets' => self::CURRENT],
        'DEEPCOOL.*\bAG400\b.*\bV2\b' => ['sockets' => self::CURRENT],
        'DEEPCOOL.*\bAG620\b.*\bV2\b' => ['sockets' => self::CURRENT],
        'DEEPCOOL.*\bAK400\b(?!\s+(?:G2|DIGITAL PRO))' => ['max_tdp_w' => 220, 'sockets' => self::MAINSTREAM],
        'DEEPCOOL.*\bAG400\b' => ['max_tdp_w' => 220, 'sockets' => self::MAINSTREAM],
        'DEEPCOOL.*\bAK500\b(?!\s+DIGITAL PRO)' => ['max_tdp_w' => 240, 'sockets' => self::HEDT],
        'DEEPCOOL.*\b(?:AK620|AG620)\b' => ['max_tdp_w' => 260, 'sockets' => self::HEDT],
        // AS500 / AS500 PLUS, «220W TDP», ficha Application For de la generación original.
        'DEEPCOOL.*\bAS500\b' => ['max_tdp_w' => 220, 'sockets' => ['LGA2066', 'LGA2011-v3', 'LGA2011', 'LGA1200', 'LGA1151', 'LGA1150', 'LGA1155', 'AM4', 'AM3+', 'AM3', 'AM2+', 'AM2', 'FM2+', 'FM2', 'FM1']],
        // DeepCool LS520/LS720 y LT520: «Application For», sin TDP publicado en Technical Spec.
        'DEEPCOOL.*\b(?:LS520|LS720|LT520)\b' => ['sockets' => self::HEDT],
        // DeepCool LD/LP/LQ/LT, Application For; las revisiones modernas incorporan LGA1851.
        'DEEPCOOL.*\b(?:LD24|LD36|LP240|LP360|LQ240|LQ360|LT240|LT360)\b' => ['sockets' => self::CURRENT],
        'DEEPCOOL.*\bLE(?:240|360)\s+V2\b' => ['sockets' => self::CURRENT],
        // Cooler Master, «CPU Socket» de Hyper 212 Halo / Spectrum V3 y MasterLiquid Core II (coolermaster.com).
        'COOLER MASTER.*\bHYPER 212\s+(?:HALO|SPECTRUM V3)\b' => ['sockets' => self::CURRENT],
        'COOLER MASTER.*MASTER\s*LIQUID\s+(?:240|360)\s+CORE II\b' => ['sockets' => self::CURRENT],
        // Corsair, Tech Specs «Cooling Socket Support», sin potencia térmica nominal (corsair.com).
        'CORSAIR.*\bCW-9060043\b' => ['sockets' => ['LGA2066', 'LGA2011-v3', 'LGA2011', 'LGA1200', 'LGA1151', 'LGA1150', 'LGA1155', 'LGA1156', 'AM4', 'AM3', 'AM2', 'sTR4', 'sTRX4']],
        'CORSAIR.*\bCW-906100[157]\b' => ['sockets' => self::HEDT],
        'CORSAIR.*\bNAUTILUS\s+(?:240|360)\b' => ['sockets' => ['LGA1851', 'LGA1700', 'AM5', 'AM4']],
        'CORSAIR.*\bTITAN 360\b' => ['sockets' => ['LGA1851', 'LGA1700', 'AM5', 'AM4']],
        // ASUS ROG Ryuo III / Strix LC III y ProArt LC 360, Tech Specs «CPU Support» (asus.com).
        'ASUS.*(?:RYUO III 240|STRIX LC III 360|PROART LC 360)\b' => ['sockets' => ['LGA1851', 'LGA1700', 'LGA1200', 'LGA1151', 'LGA1150', 'LGA1155', 'LGA1156', 'AM5', 'AM4']],
        // MSI MAG CoreLiquid A12/A13/A15, Specifications «CPU SOCKET» (msi.com).
        'MSI.*CORELIQUID\s+A1[235]\s+(?:240|360)(?:MM)?\b' => ['sockets' => ['LGA1851', 'LGA1700', 'AM5', 'AM4']],
        // NZXT T120 y Kraken Elite, «CPU Compatibility» (nzxt.com); no publican TDP.
        'NZXT.*\bT120\b' => ['sockets' => self::MAINSTREAM],
        'NZXT.*\bKRAKEN\s+(?:240|280|360)\s+ELITE\b' => ['sockets' => self::CURRENT],
        // Thermaltake Astria, «Cooling Power» de 210/230/265 W y «Compatibility» (thermaltake.com).
        'THERMALTAKE.*\bASTRIA 200\b' => ['max_tdp_w' => 210, 'sockets' => self::HEDT],
        'THERMALTAKE.*\bASTRIA 400\b' => ['max_tdp_w' => 230, 'sockets' => self::HEDT],
        'THERMALTAKE.*\bASTRIA 600\b' => ['max_tdp_w' => 265, 'sockets' => self::HEDT],
        // Thermaltake TH V3 / LA, «Compatibility», sin TDP declarado para estas AIO.
        'THERMALTAKE.*\b(?:LA(?:240|360)|TH(?:240|360)(?:-S)? V3)\b' => ['sockets' => ['LGA1851', 'LGA1700', 'LGA1200', 'LGA1151', 'LGA1150', 'LGA1155', 'LGA1156', 'LGA2066', 'LGA2011-v3', 'LGA2011', 'AM5', 'AM4', 'AM3+', 'AM3', 'AM2+', 'AM2', 'FM2+', 'FM2', 'FM1']],
        // XPG Levante II 360, Specifications «CPU Socket Compatibility» (xpg.com).
        'XPG.*\bLEVANTE II 360\b' => ['sockets' => self::CURRENT],
        // HYTE THICC Q60, Tech Specs «Socket Compatibility» (hyte.com).
        'HYTE.*\bTHICC Q60\b' => ['sockets' => self::MAINSTREAM],
        // Sharkoon S90, «Compatibility» (sharkoon.com/product/S90).
        'SHARKOON.*\bS90\b' => ['sockets' => [...self::HEDT, 'LGA1156']],
        // InWin MR24/MR36, Specifications «CPU Socket Support» (in-win.com).
        'INWIN.*\bMR(?:24|36)\b' => ['sockets' => ['LGA1851', 'LGA1700', 'LGA1200', 'LGA1151', 'LGA1150', 'LGA1155', 'LGA1156', 'LGA2066', 'LGA2011-v3', 'LGA2011', 'AM5', 'AM4', 'AM3+', 'AM3', 'AM2+', 'AM2', 'FM2+', 'FM2', 'FM1', 'sTR4', 'sTRX4']],
    ];
}
