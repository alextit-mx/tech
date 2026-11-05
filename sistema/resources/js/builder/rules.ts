import chipsets from './chipsets.json';
/*
 * Reglas del configurador. Las mismas viven en PHP (App\Support\BuildValidator), que revalida al cotizar.
 */

export const SLOTS = [
    'cpu',
    'motherboard',
    'ram',
    'gpu',
    'storage',
    'cooler',
    'psu',
    'case',
] as const;
export type Slot = (typeof SLOTS)[number];

export type Specs = Record<
    string,
    string | number | boolean | string[] | undefined
>;

export interface Part {
    id: number;
    slot: Slot;
    name: string;
    brand: string | null;
    image: string | null;
    price: number | null;
    stock: 'ok' | 'low' | 'none';
    stockLabel: string;
    specs: Specs;
    estimated?: string[];
    url: string;
}

export interface Extra extends Omit<Part, 'slot' | 'specs'> {
    group: string;
}

export type Build = Partial<Record<Slot, Part>>;

export const SLOT_INFO: Record<
    Slot,
    { label: string; silk: string; short: string }
> = {
    cpu: { label: 'Procesador', silk: 'CPU', short: 'CPU' },
    motherboard: { label: 'Tarjeta madre', silk: 'MB', short: 'Tarjeta madre' },
    ram: { label: 'Memoria RAM', silk: 'DIMM', short: 'RAM' },
    gpu: { label: 'Tarjeta de video', silk: 'PCIe x16', short: 'GPU' },
    storage: { label: 'Almacenamiento', silk: 'M.2', short: 'SSD' },
    cooler: { label: 'Enfriamiento', silk: 'CPU FAN', short: 'Enfriamiento' },
    psu: { label: 'Fuente de poder', silk: 'ATX 24P', short: 'Fuente' },
    case: { label: 'Gabinete', silk: 'CASE', short: 'Gabinete' },
};

export const RARITY = [
    { name: 'Común', color: '#9AA3AE' },
    { name: 'Poco común', color: '#4ADE80' },
    { name: 'Raro', color: '#3B9BFF' },
    { name: 'Épico', color: '#B26BFF' },
    { name: 'Legendario', color: '#FFB020' },
    { name: 'Mítico', color: '#E30613' },
];

const num = (value: Specs[string]): number => Number(value ?? 0) || 0;
/** Valor de spec como texto para mensajes. */
const str = (value: Specs[string]): string =>
    Array.isArray(value)
        ? value.join(', ')
        : value === undefined
          ? ''
          : String(value);
const list = (value: Specs[string]): string[] =>
    Array.isArray(value) ? value.map(String) : [];

/** Memoria que acepta cada plataforma: decide la RAM cuando aún no hay tarjeta madre (LGA1700 acepta ambas). */
const PLATFORM_MEMORY: Record<string, string[]> = {
    AM5: ['DDR5'],
    LGA1851: ['DDR5'],
    sTR5: ['DDR5'],
    AM4: ['DDR4'],
    LGA1200: ['DDR4'],
    LGA1151: ['DDR4'],
    LGA1700: ['DDR4', 'DDR5'],
};
export function hasIgpu(build: Build): boolean {
    return build.cpu?.specs.igpu === true;
}

export type Issue = {
    level: 'incompatible' | 'riesgo' | 'desbalance';
    code: string;
    message: string;
    slots: Slot[];
};
export type CompatibilityParts = Partial<
    Record<Slot, { specs: Specs; estimated?: string[] }>
>;

export function issues(build: CompatibilityParts): Issue[] {
    const out: Issue[] = [];
    const cpu = build.cpu?.specs ?? {},
        mb = build.motherboard?.specs ?? {},
        ram = build.ram?.specs ?? {};
    const gpu = build.gpu?.specs ?? {},
        psu = build.psu?.specs ?? {},
        cooler = build.cooler?.specs ?? {},
        box = build.case?.specs ?? {};
    const has = (s: Specs, key: string) =>
        s[key] !== undefined && s[key] !== null;
    const add = (
        level: Issue['level'],
        code: string,
        message: string,
        fields: Partial<Record<Slot, string[]>>,
    ) => {
        const slots = Object.keys(fields) as Slot[];
        if (
            level === 'incompatible' &&
            slots.some((slot) =>
                fields[slot]?.some((key) =>
                    build[slot]?.estimated?.includes(key),
                ),
            )
        ) {
            level = 'riesgo';
            message += ' (dato por confirmar)';
        }
        out.push({ level, code, message, slots });
    };
    const power = has(cpu, 'power_w')
        ? num(cpu.power_w)
        : num(cpu.tdp_w) * 1.35;
    const powerText = String(Number(power.toFixed(10)));
    const powerField = has(cpu, 'power_w') ? 'power_w' : 'tdp_w';
    const platform = PLATFORM_MEMORY[str(cpu.socket)] ?? [];
    if (has(cpu, 'socket') && has(mb, 'socket') && cpu.socket !== mb.socket)
        add(
            'incompatible',
            'socket',
            `El procesador es ${str(cpu.socket)} y la tarjeta madre ${str(mb.socket)}.`,
            { cpu: ['socket'], motherboard: ['socket'] },
        );
    if (
        build.ram &&
        !build.motherboard &&
        platform.length &&
        !platform.includes(str(ram.memory_type))
    )
        add(
            'incompatible',
            'socket',
            `El procesador ${str(cpu.socket)} usa ${platform.join(' o ')}, no ${str(ram.memory_type) || 'memoria desconocida'}.`,
            { cpu: ['socket'], ram: ['memory_type'] },
        );
    if (
        has(ram, 'memory_type') &&
        has(mb, 'memory_type') &&
        ram.memory_type !== mb.memory_type
    )
        add(
            'incompatible',
            'memory_type',
            `La memoria ${str(ram.memory_type)} no entra en una tarjeta ${str(mb.memory_type)}.`,
            { ram: ['memory_type'], motherboard: ['memory_type'] },
        );
    if (
        has(mb, 'form_factor') &&
        has(box, 'form_factors') &&
        !list(box.form_factors).includes(str(mb.form_factor))
    )
        add(
            'incompatible',
            'form_factor',
            `La tarjeta madre ${str(mb.form_factor)} no cabe en el gabinete.`,
            { motherboard: ['form_factor'], case: ['form_factors'] },
        );
    if (
        has(ram, 'modules') &&
        has(mb, 'ram_slots') &&
        num(ram.modules) > num(mb.ram_slots)
    )
        add(
            'incompatible',
            'ram_slots',
            `La RAM tiene ${str(ram.modules)} módulos y la tarjeta madre solo ${str(mb.ram_slots)} ranuras.`,
            { ram: ['modules'], motherboard: ['ram_slots'] },
        );
    if (
        has(ram, 'capacity_gb') &&
        has(mb, 'max_ram_gb') &&
        num(ram.capacity_gb) > num(mb.max_ram_gb)
    )
        add(
            'incompatible',
            'ram_max',
            `La RAM suma ${str(ram.capacity_gb)} GB y la tarjeta madre admite ${str(mb.max_ram_gb)} GB.`,
            { ram: ['capacity_gb'], motherboard: ['max_ram_gb'] },
        );
    const chip = (
        chipsets as Record<
            string,
            {
                socket: string;
                class: number;
                supported: string[];
                bios: string[];
            }
        >
    )[str(mb.chipset)];
    if (chip && has(cpu, 'series')) {
        if (chip.bios.includes(str(cpu.series)))
            add(
                'riesgo',
                'chipset_cpu',
                `El chipset ${str(mb.chipset)} requiere verificar o actualizar el BIOS para ${str(cpu.series)}.`,
                { cpu: ['series'], motherboard: ['chipset'] },
            );
        else if (!chip.supported.includes(str(cpu.series)))
            add(
                'incompatible',
                'chipset_cpu',
                `El chipset ${str(mb.chipset)} no soporta ${str(cpu.series)}.`,
                { cpu: ['series'], motherboard: ['chipset'] },
            );
        const limit =
            ({ 1: 88, 2: 142, 3: 200 } as Record<number, number>)[chip.class] ??
            Infinity;
        if (chip.class < num(cpu.tier) - 2 || power > limit)
            add(
                'desbalance',
                'board_class',
                `El chipset ${str(mb.chipset)} (clase ${chip.class}) queda corto para el procesador de nivel ${num(cpu.tier)} y ${powerText} W máximos.`,
                { cpu: ['tier', 'power_w', 'tdp_w'], motherboard: ['chipset'] },
            );
    }
    if (
        has(cpu, 'socket') &&
        has(cooler, 'sockets') &&
        !list(cooler.sockets).includes(str(cpu.socket))
    )
        add(
            'incompatible',
            'cooler_socket',
            `El enfriamiento no es para socket ${str(cpu.socket)}.`,
            { cpu: ['socket'], cooler: ['sockets'] },
        );
    if (build.cpu && has(cooler, 'max_tdp_w') && num(cooler.max_tdp_w) < power)
        add(
            'incompatible',
            'cooler_power',
            `El enfriamiento disipa ${str(cooler.max_tdp_w)} W y el procesador puede consumir ${powerText} W.`,
            { cpu: [powerField], cooler: ['max_tdp_w'] },
        );
    if (has(gpu, 'length_mm') && has(box, 'max_gpu_length_mm')) {
        const gap = num(box.max_gpu_length_mm) - num(gpu.length_mm);
        if (gap < 10)
            add(
                gap < 0 ? 'incompatible' : 'riesgo',
                'gpu_length',
                `La tarjeta de video mide ${str(gpu.length_mm)} mm y el gabinete admite ${str(box.max_gpu_length_mm)} mm.${gap >= 0 ? ` Solo quedan ${gap} mm de margen.` : ''}`,
                { gpu: ['length_mm'], case: ['max_gpu_length_mm'] },
            );
    }
    const consumption = power + num(gpu.tdp_w) + 75,
        minimum = Number(
            Math.max(num(gpu.recommended_psu_w), consumption).toFixed(10),
        ),
        ideal = Math.ceil((consumption * 1.25) / 50) * 50;
    if (has(psu, 'watts')) {
        if (num(psu.watts) < minimum)
            add(
                'incompatible',
                'psu_watts',
                `La fuente entrega ${str(psu.watts)} W y el armado requiere al menos ${minimum} W.`,
                {
                    cpu: [powerField],
                    gpu: ['tdp_w', 'recommended_psu_w'],
                    psu: ['watts'],
                },
            );
        else if (num(psu.watts) < ideal)
            add(
                'desbalance',
                'psu_watts',
                `La fuente entrega ${str(psu.watts)} W; se recomiendan ${ideal} W para tener margen.`,
                { psu: ['watts'], cpu: ['power_w'], gpu: ['tdp_w'] },
            );
    }
    if (build.psu && build.gpu) {
        const cert = str(psu.certification) || 'Sin certificación';
        if (cert === 'Sin certificación' && num(gpu.tdp_w) > 150)
            add(
                'incompatible',
                'psu_quality',
                `La fuente sin certificación no es adecuada para una tarjeta de video de ${str(gpu.tdp_w)} W.`,
                { psu: ['certification'], gpu: ['tdp_w'] },
            );
        else if (
            num(gpu.tier) >= 4 &&
            !['80+ Gold', '80+ Platinum', '80+ Titanium'].includes(cert)
        )
            add(
                'desbalance',
                'psu_quality',
                `La tarjeta de video de nivel ${str(gpu.tier)} requiere una fuente 80+ Gold o superior; la actual es ${cert}.`,
                { gpu: ['tier'], psu: ['certification'] },
            );
        const connector = str(gpu.power_connector) || 'ninguno',
            eight = num(psu.pcie_8pin),
            required =
                (
                    { '8-pin': 1, '2x8-pin': 2, '3x8-pin': 3 } as Record<
                        string,
                        number
                    >
                )[connector] ?? 0;
        if (required > eight)
            add(
                'incompatible',
                'gpu_power_connector',
                `La tarjeta de video requiere ${required} conectores de 8 pines y la fuente tiene ${eight}.`,
                { gpu: ['power_connector'], psu: ['pcie_8pin'] },
            );
        if (connector === '16-pin' && !psu.pcie_16pin) {
            const needed = Math.ceil(num(gpu.tdp_w) / 150),
                adapter = eight >= needed;
            add(
                adapter ? 'riesgo' : 'incompatible',
                'gpu_power_connector',
                `La tarjeta de video usa 16 pines y la fuente no tiene conector nativo. ${adapter ? `Funciona con el adaptador de la tarjeta y ${needed} conectores de 8 pines.` : `Necesita ${needed} conectores de 8 pines para el adaptador y la fuente tiene ${eight}.`}`,
                {
                    gpu: ['power_connector', 'tdp_w'],
                    psu: ['pcie_16pin', 'pcie_8pin'],
                },
            );
        }
    }
    if (box.includes_psu) {
        if (build.psu)
            add(
                'desbalance',
                'case_has_psu',
                'El gabinete incluye fuente y elegiste otra: pagas dos fuentes.',
                { case: ['includes_psu'], psu: ['watts'] },
            );
        else if (num(gpu.tdp_w) > 75)
            add(
                'incompatible',
                'case_has_psu',
                `La fuente incluida del gabinete no está confirmada para una tarjeta de video de ${str(gpu.tdp_w)} W.`,
                { case: ['includes_psu'], gpu: ['tdp_w'] },
            );
    }
    if (!build.gpu && cpu.igpu === false)
        add(
            'incompatible',
            'no_video',
            'El procesador no tiene gráficos integrados y falta una tarjeta de video.',
            { cpu: ['igpu'], gpu: [] },
        );
    return out;
}

export function incompatibility(part: Part, build: Build): string | null {
    return (
        issues({ ...build, [part.slot]: part }).find(
            (issue) =>
                issue.level === 'incompatible' &&
                issue.slots.includes(part.slot),
        )?.message ?? null
    );
}

export function consumption(build: Build): number {
    return Math.ceil(
        num(build.cpu?.specs.power_w ?? num(build.cpu?.specs.tdp_w) * 1.35) +
            num(build.gpu?.specs.tdp_w) +
            75,
    );
}
export function recommendedPsu(build: Build): number {
    return Math.max(
        num(build.gpu?.specs.recommended_psu_w),
        Math.ceil((consumption(build) * 1.25) / 50) * 50,
    );
}
export function requiredSlots(build: Build): Slot[] {
    const slots: Slot[] = [
        'cpu',
        'motherboard',
        'ram',
        'storage',
        'psu',
        'case',
    ];
    return hasIgpu(build) ? slots : [...slots, 'gpu'];
}
export function missingSlots(build: Build): Slot[] {
    return requiredSlots(build).filter((slot) => !build[slot]);
}
export function warnings(build: Build): Issue[] {
    return issues(build).filter((issue) => issue.level !== 'incompatible');
}

/** Nivel de poder 0–100: la tarjeta de video pesa más que el procesador. */
export function powerLevel(build: Build): number {
    const cpu = num(build.cpu?.specs.tier);
    const gpu = build.gpu
        ? num(build.gpu.specs.tier)
        : hasIgpu(build)
          ? 0.6
          : 0;
    return Math.round(((gpu * 0.65 + cpu * 0.35) / 6) * 100);
}

/** Para qué resolución rinde, según el nivel de la tarjeta de video. Sin números de FPS. */
export function resolutions(build: Build): { label: string; on: boolean }[] {
    const tier = build.gpu
        ? num(build.gpu.specs.tier)
        : hasIgpu(build)
          ? 0.5
          : 0;
    return [
        { label: '1080p', on: tier >= 1 },
        { label: '1440p', on: tier >= 3 },
        { label: '4K', on: tier >= 4 },
    ];
}

/** Rareza 0–5: el tier si lo tiene (CPU/GPU) o la posición de precio dentro de su slot. */
export function rarity(part: Part, all: Part[]): number {
    const tier = num(part.specs.tier);
    if (tier) return Math.min(5, Math.max(0, tier - 1));
    const prices = all
        .filter((p) => p.slot === part.slot && p.price !== null)
        .map((p) => p.price as number)
        .sort((a, b) => a - b);
    if (part.price === null || prices.length < 2) return 2;
    const rank =
        prices.findIndex((p) => p >= (part.price as number)) /
        (prices.length - 1);
    return Math.min(5, Math.floor(rank * 6));
}

export function keySpecs(part: Pick<Part, 'slot' | 'specs'>): string {
    const s = part.specs;
    const parts: (string | false)[] = (() => {
        switch (part.slot) {
            case 'cpu':
                return [
                    `${str(s.cores)} núcleos`,
                    String(s.socket),
                    `${str(s.tdp_w)} W`,
                    s.igpu === true && 'gráficos integrados',
                ];
            case 'motherboard':
                return [
                    String(s.socket),
                    String(s.memory_type),
                    String(s.form_factor),
                ];
            case 'ram':
                return [
                    `${str(s.capacity_gb)} GB`,
                    String(s.memory_type),
                    `${str(s.speed_mhz)} MHz`,
                ];
            case 'gpu':
                return [
                    `${str(s.vram_gb)} GB`,
                    `${str(s.tdp_w)} W`,
                    `${str(s.length_mm)} mm`,
                ];
            case 'storage':
                return [
                    num(s.capacity_gb) >= 1000
                        ? `${num(s.capacity_gb) / 1000} TB`
                        : `${str(s.capacity_gb)} GB`,
                    String(s.type),
                ];
            case 'cooler':
                return [
                    s.type === 'líquida'
                        ? 'Enfriamiento líquido'
                        : 'Disipador de aire',
                    `hasta ${str(s.max_tdp_w)} W`,
                ];
            case 'psu':
                return [`${str(s.watts)} W`, String(s.certification ?? '')];
            case 'case':
                return [
                    list(s.form_factors).join(' / '),
                    `GPU hasta ${str(s.max_gpu_length_mm)} mm`,
                ];
            default:
                return [];
        }
    })();
    return parts
        .filter((p): p is string => Boolean(p) && p !== 'undefined')
        .join(' · ');
}

/* ---------------- Bases recomendadas (como armado.js del sitio, pero con productos reales) ---------------- */

export const USES = {
    gaming1080: 'Juegos en 1080p',
    gaming1440: 'Juegos en 1440p',
    gaming4k: 'Juegos en 4K',
    stream: 'Jugar y transmitir',
    create: 'Edición, diseño e IA',
    office: 'Escuela y oficina',
} as const;
export type Use = keyof typeof USES;

export const BUDGETS = {
    esencial: 'Esencial',
    equilibrada: 'Equilibrada',
    alta: 'Alta',
    extrema: 'Extrema',
} as const;
export type Budget = keyof typeof BUDGETS;
