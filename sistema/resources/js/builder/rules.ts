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

export function hasIgpu(build: Build): boolean {
    return build.cpu?.specs.igpu === true;
}

/** Motivo por el que `part` no entra en la build actual, o null si es compatible. */
export function incompatibility(part: Part, build: Build): string | null {
    const { cpu, motherboard: mb, ram, gpu, cooler } = build;
    const box = build.case;
    const s = part.specs;
    switch (part.slot) {
        case 'cpu':
            if (mb && s.socket !== mb.specs.socket)
                return `Socket ${str(s.socket)} ≠ ${str(mb.specs.socket)} de tu tarjeta madre`;
            if (
                cooler &&
                !list(cooler.specs.sockets).includes(String(s.socket))
            )
                return `Tu disipador no es para ${str(s.socket)}`;
            if (cooler && num(s.tdp_w) > num(cooler.specs.max_tdp_w))
                return `Pide ${str(s.tdp_w)} W; tu disipador llega a ${str(cooler.specs.max_tdp_w)} W`;
            return null;
        case 'motherboard':
            if (cpu && s.socket !== cpu.specs.socket)
                return `Socket ${str(s.socket)} ≠ ${str(cpu.specs.socket)}`;
            if (ram && s.memory_type !== ram.specs.memory_type)
                return `Usa ${str(s.memory_type)}; tu memoria es ${str(ram.specs.memory_type)}`;
            if (
                box &&
                !list(box.specs.form_factors).includes(String(s.form_factor))
            )
                return `${str(s.form_factor)} no cabe en tu gabinete`;
            return null;
        case 'ram':
            if (mb && s.memory_type !== mb.specs.memory_type)
                return `${str(s.memory_type)} no entra en una tarjeta ${str(mb.specs.memory_type)}`;
            return null;
        case 'gpu':
            if (box && num(s.length_mm) > num(box.specs.max_gpu_length_mm))
                return `Mide ${str(s.length_mm)} mm; tu gabinete admite ${str(box.specs.max_gpu_length_mm)} mm`;
            return null;
        case 'cooler':
            if (cpu && !list(s.sockets).includes(String(cpu.specs.socket)))
                return `No es para socket ${str(cpu.specs.socket)}`;
            if (cpu && num(cpu.specs.tdp_w) > num(s.max_tdp_w))
                return `Disipa ${str(s.max_tdp_w)} W; tu procesador pide ${str(cpu.specs.tdp_w)} W`;
            return null;
        case 'case':
            if (
                mb &&
                !list(s.form_factors).includes(String(mb.specs.form_factor))
            )
                return `No admite tarjetas ${str(mb.specs.form_factor)}`;
            if (gpu && num(gpu.specs.length_mm) > num(s.max_gpu_length_mm))
                return `Tu tarjeta de video mide ${str(gpu.specs.length_mm)} mm; aquí caben ${str(s.max_gpu_length_mm)} mm`;
            return null;
        case 'psu':
            if (num(s.watts) < consumption(build))
                return `${str(s.watts)} W no alcanza para ${consumption(build)} W`;
            return null;
        default:
            return null;
    }
}

export function consumption(build: Build): number {
    return num(build.cpu?.specs.tdp_w) + num(build.gpu?.specs.tdp_w) + 75;
}

export function recommendedPsu(build: Build): number {
    return Math.ceil((consumption(build) * 1.4) / 50) * 50;
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
    return hasIgpu(build)
        ? slots
        : [...slots.slice(0, 3), 'gpu', ...slots.slice(3)];
}

export function missingSlots(build: Build): Slot[] {
    return requiredSlots(build).filter((slot) => !build[slot]);
}

/** Avisos de la build completa: incompatibilidades (bloquean) y fuente justa (solo aviso). */
export function warnings(
    build: Build,
): { slot: Slot; message: string; blocking: boolean }[] {
    const out: { slot: Slot; message: string; blocking: boolean }[] = [];
    for (const slot of SLOTS) {
        const part = build[slot];
        if (!part) continue;
        const reason = incompatibility(part, build);
        if (reason) out.push({ slot, message: reason, blocking: true });
    }
    if (!build.gpu && build.cpu && !hasIgpu(build))
        out.push({
            slot: 'gpu',
            message:
                'Tu procesador no tiene gráficos integrados: elige una tarjeta de video',
            blocking: true,
        });
    const psu = build.psu;
    if (
        psu &&
        num(psu.specs.watts) >= consumption(build) &&
        num(psu.specs.watts) < recommendedPsu(build)
    ) {
        out.push({
            slot: 'psu',
            message: `Funciona, pero lo ideal son ${recommendedPsu(build)} W o más`,
            blocking: false,
        });
    }
    // Una sola vez por par de piezas (cpu↔mb aparece en las dos).
    return out.filter(
        (w, i) => out.findIndex((o) => o.message === w.message) === i,
    );
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

const TARGETS: Record<Use, Record<Budget, [number, number]>> = {
    gaming1080: {
        esencial: [1, 1],
        equilibrada: [2, 2],
        alta: [3, 3],
        extrema: [4, 4],
    },
    gaming1440: {
        esencial: [2, 2],
        equilibrada: [3, 3],
        alta: [4, 4],
        extrema: [4, 5],
    },
    gaming4k: {
        esencial: [3, 3],
        equilibrada: [4, 4],
        alta: [4, 5],
        extrema: [5, 6],
    },
    stream: {
        esencial: [2, 2],
        equilibrada: [3, 3],
        alta: [4, 4],
        extrema: [5, 5],
    },
    create: {
        esencial: [2, 1],
        equilibrada: [3, 2],
        alta: [4, 3],
        extrema: [5, 5],
    },
    office: {
        esencial: [1, 0],
        equilibrada: [1, 0],
        alta: [2, 0],
        extrema: [3, 1],
    },
};
const MEMORY: Record<Budget, number> = {
    esencial: 16,
    equilibrada: 32,
    alta: 32,
    extrema: 64,
};
const STORAGE_GB: Record<Budget, number> = {
    esencial: 1000,
    equilibrada: 1000,
    alta: 2000,
    extrema: 4000,
};

/** Precio para ordenar: lo que se cotiza va al final. */
const cost = (p: Part) => p.price ?? 1e9;
const cheapest = (parts: Part[]) =>
    [...parts].sort((a, b) => cost(a) - cost(b))[0];

function pickAtLeast(
    parts: Part[],
    key: string,
    target: number,
): Part | undefined {
    const enough = parts.filter((p) => num(p.specs[key]) >= target);
    return enough.length
        ? cheapest(enough)
        : [...parts].sort((a, b) => num(b.specs[key]) - num(a.specs[key]))[0];
}

export function recommend(all: Part[], use: Use, budget: Budget): Build {
    const of = (slot: Slot) => all.filter((p) => p.slot === slot);
    const [cpuTier, gpuTier] = TARGETS[use][budget];
    const build: Build = {};

    build.cpu =
        pickAtLeast(
            gpuTier === 0
                ? of('cpu').filter((p) => p.specs.igpu === true)
                : of('cpu'),
            'tier',
            cpuTier,
        ) ?? pickAtLeast(of('cpu'), 'tier', cpuTier);
    if (gpuTier > 0 || !hasIgpu(build))
        build.gpu = pickAtLeast(of('gpu'), 'tier', Math.max(1, gpuTier));

    const fits = (slot: Slot) =>
        of(slot).filter((p) => !incompatibility(p, build));
    build.motherboard = cheapest(fits('motherboard'));
    build.ram = pickAtLeast(fits('ram'), 'capacity_gb', MEMORY[budget]);
    build.storage =
        pickAtLeast(
            of('storage').filter((p) => p.specs.type !== 'HDD'),
            'capacity_gb',
            STORAGE_GB[budget],
        ) ?? pickAtLeast(of('storage'), 'capacity_gb', STORAGE_GB[budget]);
    build.case = cheapest(fits('case'));
    build.cooler = cheapest(fits('cooler'));
    build.psu = pickAtLeast(of('psu'), 'watts', recommendedPsu(build));
    for (const slot of SLOTS) if (!build[slot]) delete build[slot];
    return build;
}
