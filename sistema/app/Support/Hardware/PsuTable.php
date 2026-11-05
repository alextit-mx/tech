<?php

namespace App\Support\Hardware;

class PsuTable
{
    // Fichas de líneas Corsair, Cooler Master, XPG, EVGA, Gigabyte, MSI, ASUS, DeepCool, Thermaltake y demás fabricantes. Revisiones ambiguas conservan pcie_16pin como supuesto.
    public const DATA = [
        'CORSAIR.*\bRM\d+E\b' => ['80+ Gold', true],
        'CORSAIR.*\bRM\d+X?\b' => ['80+ Gold', false],
        'CORSAIR.*\bCX\d+M?\b' => ['80+ Bronze', false],
        'CORSAIR.*\bHX\d+I?\b' => ['80+ Platinum', false],
        'CORSAIR.*\bSF\d+\b' => ['80+ Platinum', true],
        'COOLER MASTER.*MWE.*V3' => ['80+ Gold', true],
        'COOLER MASTER.*(?:MWE|G700|G800|ELITE)' => ['80+ Gold', false],
        'XPG.*CORE REACTOR II' => ['80+ Gold', true],
        'XPG.*PYLON II' => ['80+ Bronze', true],
        'XPG.*(?:PYLON|PROBE)' => ['80+ Bronze', false],
        'XPG.*PYMCORE' => ['80+ Gold', true],
        'EVGA.*(?:G2|G3|G5|G6)' => ['80+ Gold', false],
        'GIGABYTE.*(?:PG5|P650G)' => ['80+ Gold', true],
        'GIGABYTE.*UD\d+GM' => ['80+ Gold', false],
        'MSI.*(?:MAG|MPG|MEG|PRO).*PCIE\s*5' => ['80+ Gold', true],
        'MSI.*A\d+BN' => ['80+ Bronze', false],
        'MSI.*A\d+GN II' => ['80+ Gold', true],
        'ASUS.*PRIME' => ['80+ Gold', true],
        'ASUS.*(?:TUF|ROG)' => ['80+ Gold', false],
        'DEEPCOOL.*PL\d+D' => ['80+ Bronze', true],
        'DEEPCOOL.*(?:PX|PQ)\d+G' => ['80+ Gold', true],
        'DEEPCOOL.*PM\d+D' => ['80+ Gold', false],
        'GAMDIAS.*KRATOS' => ['80+ Bronze', false],
        'INWIN.*(?:P75FII|VE85)' => ['80+ Gold', false],
        'NACEB.*NA-0635' => ['80+ Bronze', false],
        'NACEB.*EVOTEC' => ['Sin certificación', false],
        'NACEB.*GN-1000' => ['80+ Gold', false],
        'NZXT.*C\d+' => ['80+ Bronze', false],
        'SHARKOON.*REBEL P20' => ['80+ Gold', true],
        'SHARKOON.*SHP' => ['80+ Bronze', false],
        'THERMALTAKE.*(?:GF3|TOUGHPOWER GT|TOUGHPOWER PT|TOUGHPOWER TF3)' => ['80+ Gold', true],
        'THERMALTAKE.*BX1' => ['80+ Bronze', false],
        '(?:ACTECK.*(?:BLAZAR|EVO)|XCASE.*(?:500W|600SGR))' => ['Sin certificación', false],
        'XZEAL.*XZPS500B' => ['80+ Bronze', false],
    ];

    /** Conectores PCIe 6+2 (no EPS de CPU) y presencia de cable de 16 pines incluido. */
    public const CONNECTORS = [
        // Corsair: fichas «Tech Specs / PCIe connectors» de cada CP (corsair.com, búsqueda por CP-902xxxx); RMx SHIFT usa cable 12VHPWR.
        'CORSAIR.*CP-9020195\b' => [4, false],
        'CORSAIR.*CP-9020196\b' => [6, false],
        'CORSAIR.*CP-9020180\b' => [6, false],
        'CORSAIR.*CP-9020061\b' => [4, false],
        'CORSAIR.*CP-9020262\b' => [2, true],
        'CORSAIR.*CP-9020200\b' => [4, false],
        'CORSAIR.*CP-902027[78]\b' => [2, false],
        'CORSAIR.*CP-9020279\b' => [2, false],
        'CORSAIR.*CP-9020123\b' => [4, false],
        'CORSAIR.*CP-9020222\b' => [4, false],
        'CORSAIR.*CP-9020259\b' => [6, true],
        'CORSAIR.*CP-9020263\b' => [3, true],
        'CORSAIR.*CP-9020264\b' => [4, true],
        'CORSAIR.*CP-902029[35]\b' => [3, true],
        'CORSAIR.*CP-902029[46]\b' => [3, true],
        'CORSAIR.*CP-9020297\b' => [4, true],
        'CORSAIR.*CP-9020252\b' => [4, true],
        'CORSAIR.*CP-9020253\b' => [8, true],
        'CORSAIR.*CP-902027[345]\b' => [4, true],
        'CORSAIR.*CP-9020276\b' => [6, true],
        'CORSAIR.*CP-90202(?:70|85)\b' => [3, true],
        'CORSAIR.*CP-902025[67]\b' => [4, true],
        // ASUS: apartados «Connectors» en Tech Specs de PRIME, TUF Gaming y ROG Strix (asus.com / rog.asus.com).
        'ASUS.*(?:ROG-STRIX-850G-AURA-GAMING|ROG.*STRIX.*850.*AURA)' => [3, true],
        'ASUS.*(?:PRIME.*850|AP-850G)' => [3, true],
        'ASUS.*TUF.*850' => [3, true],
        'ASUS.*TUF.*1200' => [4, true],
        'ASUS.*ROG.*STRIX.*750' => [4, false],
        // Cooler Master: «PCI-e 6+2 Pin Connectors» y «12V-2x6» de estas referencias (coolermaster.com, Specifications).
        'COOLER MASTER.*MPE-(?:7506|8506)-ACA?G' => [4, true],
        'COOLER MASTER.*MPW-(?:7001|8001)-ACAAG' => [4, false],
        'COOLER MASTER.*MPE-6502-ACAAG-3' => [2, true],
        // XPG: tablas «Connector» de CORE REACTOR II y PYLON (primera generación), xpg.com, Specifications.
        'XPG.*CORE ?REACTOR ?II.*1000' => [8, true],
        'XPG.*PYLON\s*(?:650|750)(?!\d)' => [4, false],
        // EVGA: fichas SuperNOVA, número de PCIe 6+2, no número de cables (evga.com, Specs).
        'EVGA.*(?:650|750)\s*G[2356]\b' => [4, false],
        'EVGA.*850BQ\b' => [6, false],
        'EVGA.*850\s*G[2356]\b' => [6, false],
        // Gigabyte: «Connectors» de GP-UDxxxGM y GP-P650G-PG5 (gigabyte.com, Specification).
        'GIGABYTE.*P650G-PG5' => [2, true],
        'GIGABYTE.*UD-?750-?GM.*PG5' => [4, true],
        'GIGABYTE.*UD-?850-?GM.*PG5' => [4, true],
        'GIGABYTE.*UD-?1000-?GM.*PG5' => [4, true],
        'GIGABYTE.*UD-?850-?GM\b' => [4, false],
        'GIGABYTE.*UD1000GM\b' => [6, false],
        // MSI: «PCI-E 6+2 PIN» / «PCI-E 16 PIN» en Specifications (msi.com).
        'MSI.*A1000G\b.*PCIE' => [6, true],
        'MSI.*A650BN\b' => [2, false],
        'MSI.*A850GL\b' => [4, true],
        'MSI.*A1000GL\b' => [4, true],
        'MSI.*A(?:750|850)GF\b' => [6, false],
        // DeepCool: «PCI-E (6+2)pin» y «12VHPWR» de PL-D, PM-D y PX-G.
        'DEEPCOOL.*PL(?:650|750)D\b' => [2, true],
        'DEEPCOOL.*PM650D\b' => [4, false],
        'DEEPCOOL.*PX850G\b' => [3, true],
        'DEEPCOOL.*PX1200G\b' => [3, true],
        // NZXT C Bronze (PA-xB), Sharkoon SHP Bronze y Thermaltake GF3: fichas de cableado.
        'NZXT.*PA-[67]B\dBB' => [4, false],
        'SHARKOON.*SHP.*(?:500|600)' => [2, false],
        'THERMALTAKE.*(?:850W.*GF3|GF3.*850W)' => [4, true],
        'THERMALTAKE.*(?:1200W.*GF3|GF3.*1200W)' => [4, true],
    ];
}
