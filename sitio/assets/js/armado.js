/*
 * Motor de "Arma tu PC": compatibilidad, consumo, rendimiento y
 * recomendaciones. Sin interfaz: lo usan el configurador y la página de equipos.
 */
import { BUDGETS, CATEGORIES, MEMORY, PARTS, STORAGE, TARGETS, USES } from "./catalogo.js";

export { BUDGETS, CATEGORIES, PARTS, USES };

export const byId = (category, id) => PARTS[category].find((part) => part.id === id) ?? null;

/* Peso del rendimiento contra el costo según el presupuesto. */
const WEIGHTS = {
    esencial: { perf: 1, cost: 2.2 },
    equilibrada: { perf: 2, cost: 1 },
    alta: { perf: 3, cost: 0.5 },
    extrema: { perf: 4, cost: 0.05 },
};

/* ---------------- Consumo y fuente ---------------- */

export function powerDraw(build) {
    const cpu = byId("cpu", build.cpu);
    const gpu = byId("gpu", build.gpu);
    /* Tarjeta madre, memoria, discos y ventiladores. */
    return (cpu?.power ?? 0) + (gpu?.power ?? 0) + 70;
}

/* Fuente recomendada: 35 % de margen para picos, redondeada a 50 W (mínimo 550 W). */
export function recommendedPsu(build) {
    return Math.max(550, Math.ceil((powerDraw(build) * 1.35) / 50) * 50);
}

/* ---------------- Compatibilidad ---------------- */

/*
 * Problemas de una pieza con el resto de la selección.
 * error: no funciona así; warning: funciona, pero conviene revisarlo.
 */
export function partIssues(category, part, build, use = "gaming1440") {
    const cpu = category === "cpu" ? part : byId("cpu", build.cpu);
    const board = category === "board" ? part : byId("board", build.board);
    const ram = category === "ram" ? part : byId("ram", build.ram);
    const gpu = category === "gpu" ? part : byId("gpu", build.gpu);
    const storage = category === "storage" ? part : byId("storage", build.storage);
    const cooler = category === "cooler" ? part : byId("cooler", build.cooler);
    const psu = category === "psu" ? part : byId("psu", build.psu);
    const box = category === "case" ? part : byId("case", build.case);
    const issues = [];
    /* between: las piezas que chocan (para proponer cuál cambiar). */
    const add = (level, text, between) => {
        if (between.includes(category)) issues.push({ level, text, between });
    };

    if (cpu && board && cpu.socket !== board.socket) {
        add("error", `${board.name} es para ${board.socket} y el procesador es ${cpu.socket}.`, ["cpu", "board"]);
    }
    if (board && ram && board.mem !== ram.mem) {
        add("error", `La tarjeta madre usa ${board.mem} y esta memoria es ${ram.mem}.`, ["board", "ram"]);
    }
    if (gpu && gpu.id === "none" && cpu && !cpu.igpu) {
        add("error", `${cpu.name} no trae gráficos: necesita tarjeta de video.`, ["gpu", "cpu"]);
    }
    if (board && box && !box.forms.includes(board.form)) {
        add("error", `Una tarjeta madre ${board.form} no cabe en este gabinete.`, ["board", "case"]);
    }
    if (gpu && box && gpu.length > box.maxGpu) {
        add("error", `La tarjeta de video mide cerca de ${gpu.length} mm y el gabinete admite ${box.maxGpu} mm.`, ["gpu", "case"]);
    }
    if (cooler && cpu && cooler.kind === "stock" && !cpu.cooler) {
        add("error", `${cpu.name} no incluye disipador.`, ["cooler", "cpu"]);
    }
    if (cooler && cpu && cooler.maxPower < cpu.power) {
        add("error", `No alcanza a enfriar los ${cpu.power} W del procesador.`, ["cooler", "cpu"]);
    }
    if (cooler && box && cooler.radiator > box.radiator) {
        add("error", `El radiador de ${cooler.radiator} mm no cabe en este gabinete.`, ["cooler", "case"]);
    }
    if (cooler && box && cooler.kind === "air" && cooler.height > box.coolerHeight) {
        add("error", "El disipador es más alto de lo que cabe en este gabinete.", ["cooler", "case"]);
    }
    if (psu && cpu && gpu) {
        const need = powerDraw({ ...build, cpu: cpu.id, gpu: gpu.id });
        const ideal = recommendedPsu({ ...build, cpu: cpu.id, gpu: gpu.id });
        /* Solo en la fuente: así no se bloquean procesadores ni tarjetas (se arregla cambiando la fuente). */
        if (psu.watts < need + 50) {
            add("error", `El equipo consume cerca de ${need} W: la fuente se queda corta.`, ["psu"]);
        } else if (psu.watts < ideal) {
            add("warning", `Recomendamos ${ideal} W para tener margen en los picos de consumo.`, ["psu"]);
        }
    }

    if (board && cpu && board.maxCpu && cpu.power > board.maxCpu) {
        add("warning", "Esta tarjeta madre está pensada para procesadores de 65 W; para este conviene una B650 o mejor.", ["board", "cpu"]);
    }
    if (storage && board && storage.gen === 5 && !board.gen5) {
        add("warning", "La tarjeta madre no tiene M.2 Gen5: el SSD funciona a velocidad Gen4.", ["storage", "board"]);
    }
    if (gpu && psu && gpu.cable16 && !psu.atx31) {
        add("warning", "Esta tarjeta usa el conector 12V-2x6: mejor una fuente ATX 3.1 con ese cable.", ["psu", "gpu"]);
    }
    if (cpu && gpu && gpu.tier > 0 && USES[use]?.kind !== "work" && gpu.tier - cpu.tier >= 3) {
        add("warning", "El procesador puede limitar a esta tarjeta de video en juegos.", ["cpu", "gpu"]);
    }
    if (ram && ram.size < 32 && ["gaming1440", "gaming4k", "stream", "create"].includes(use)) {
        add("warning", "Para este uso conviene 32 GB de memoria.", ["ram"]);
    }

    return issues;
}

/* Todos los avisos de la PC armada (sin repetir) y si ya está completa. */
export function review(build, use) {
    const seen = new Set();
    const issues = [];
    for (const { id } of CATEGORIES) {
        const part = byId(id, build[id]);
        if (!part) continue;
        for (const issue of partIssues(id, part, build, use)) {
            if (seen.has(issue.text)) continue;
            seen.add(issue.text);
            issues.push({ ...issue, category: id });
        }
    }
    const missing = CATEGORIES.filter(({ id }) => !byId(id, build[id]));
    return { issues, missing, ok: !missing.length && !issues.some((issue) => issue.level === "error") };
}

/* ---------------- Rendimiento esperado ---------------- */

const GAMING_TEXT = {
    0: "Escuela, oficina, video en 4K y juegos ligeros en 1080p.",
    1: "1080p en calidad alta; juegos competitivos con muchos cuadros por segundo.",
    2: "1080p en ultra y 1440p en calidad media a alta.",
    3: "1440p en calidad alta; 4K con DLSS o FSR.",
    4: "1440p en ultra y 4K en calidad alta con escalado.",
    5: "4K en ultra con trazado de rayos.",
    6: "4K en ultra con trazado de rayos, sin concesiones.",
};

export function performance(build) {
    const cpu = byId("cpu", build.cpu);
    const gpu = byId("gpu", build.gpu);
    const lines = [];
    if (gpu) lines.push({ label: "Juegos", text: gpu.tier === 0 && !cpu?.strongIgpu ? "Solo trabajo y video: no está pensada para jugar." : GAMING_TEXT[gpu.tier] });
    if (cpu) {
        lines.push({
            label: "Trabajo",
            text: cpu.work >= 4.5 ? "Render, edición 4K y compilación muy rápidos."
                : cpu.work >= 3.5 ? "Edición de video 4K y diseño 3D con soltura."
                : cpu.work >= 2.5 ? "Edición en 1080p, transmisiones y multitarea."
                : "Oficina, escuela y edición ligera.",
        });
    }
    return lines;
}

/* ---------------- Recomendaciones ---------------- */

function score(category, part, use, budget, build) {
    const [cpuTier, gpuTier] = TARGETS[use][budget];
    const weight = WEIGHTS[budget];
    const kind = USES[use].kind;

    switch (category) {
        case "cpu": {
            const perf = kind === "work" ? part.work : kind === "stream" ? (part.gaming + part.work) / 2 : kind === "office" ? part.work : part.gaming;
            const igpuNeeded = gpuTier === 0 ? (part.igpu ? (part.strongIgpu ? 6 : 0) : -100) : 0;
            return perf * weight.perf - part.cost * weight.cost - Math.abs(part.tier - cpuTier) * 6 + igpuNeeded;
        }
        case "gpu": {
            if (gpuTier === 0) return part.id === "none" ? 100 : -part.tier * 10;
            if (part.id === "none") return -100;
            /* CUDA para edición e IA; NVENC para transmitir. */
            const bonus = part.brand === "NVIDIA" ? (kind === "work" ? 2 : kind === "stream" ? 0.6 : 0) : 0;
            return part.gaming * weight.perf - part.cost * weight.cost - Math.abs(part.tier - gpuTier) * 6 + bonus;
        }
        case "board": {
            const compact = budget === "esencial" || use === "office";
            return -Math.abs(part.tier - Math.min(cpuTier, 5)) * 3 - part.cost * weight.cost + (part.form === (compact ? "mATX" : "ATX") ? 3 : 0) - (part.form === "ITX" ? 4 : 0);
        }
        case "ram":
            return -Math.abs(part.size - MEMORY[use][budget]) / 8 * 4 - part.cost * weight.cost * 0.5;
        case "storage":
            return -Math.abs(part.size - STORAGE[budget]) * 3 - part.cost * weight.cost * 0.5 + (part.gen === 5 && budget === "extrema" && kind === "work" ? 3 : 0);
        case "cooler":
            /* En una PC de oficina, lo sencillo y silencioso. */
            if (kind === "office") return -part.cost * 2 - part.tier;
            return -part.cost * (weight.cost + 0.4) + (budget === "extrema" && part.kind === "aio" ? 4 : 0)
                + (budget === "alta" || budget === "extrema" ? part.tier * 0.5 : 0);
        case "psu": {
            /* La más chica que deje margen; fuera de Esencial, Gold; con conector 12V-2x6, ATX 3.1. */
            const gpu = byId("gpu", build.gpu);
            const quality = budget === "esencial" ? 0 : (part.watts >= 650 ? 2 : 0) + (part.atx31 && budget !== "equilibrada" ? 1 : 0);
            return -part.watts / 100 - part.cost * 0.3 - (part.watts < recommendedPsu(build) ? 20 : 0)
                + quality + (gpu?.cable16 && part.atx31 ? 3 : 0);
        }
        case "case":
            if (kind === "office") return -part.cost * 2 - (part.forms.length === 1 ? 6 : 0);
            return -part.cost * (weight.cost + 0.3) + (budget === "alta" || budget === "extrema" ? part.tier * 0.8 : 0) - (part.forms.length === 1 ? 6 : 0);
        default:
            return 0;
    }
}

/* La mejor opción de una categoría que funcione con lo ya elegido (las demás piezas no cambian). */
export function recommendFor(category, build, use, budget) {
    const options = PARTS[category]
        .map((part) => ({ part, issues: partIssues(category, part, build, use) }))
        .filter(({ issues }) => !issues.some((issue) => issue.level === "error"))
        .map(({ part, issues }) => ({ part, value: score(category, part, use, budget, build) - issues.length * 2 }))
        .sort((a, b) => b.value - a.value);

    return options[0]?.part ?? null;
}

/* Una PC completa para el uso y presupuesto, en el orden en que se arma. */
export function recommend(use, budget) {
    const build = {};
    for (const category of ["cpu", "gpu", "board", "ram", "storage", "case", "cooler", "psu"]) {
        build[category] = recommendFor(category, build, use, budget)?.id ?? null;
    }
    return build;
}

/* ---------------- Para compartir y cotizar ---------------- */

export function buildFromQuery(search) {
    const params = new URLSearchParams(search);
    const build = {};
    for (const { id } of CATEGORIES) {
        const value = params.get(id);
        if (value && byId(id, value)) build[id] = value;
    }
    return {
        build,
        use: USES[params.get("uso")] ? params.get("uso") : null,
        budget: BUDGETS[params.get("presupuesto")] ? params.get("presupuesto") : null,
    };
}

export function queryFor(build, use, budget) {
    const params = new URLSearchParams();
    if (use) params.set("uso", use);
    if (budget) params.set("presupuesto", budget);
    for (const { id } of CATEGORIES) {
        if (build[id]) params.set(id, build[id]);
    }
    return params.toString();
}

export function totalPrice(build) {
    let total = 0;
    for (const { id } of CATEGORIES) {
        const part = byId(id, build[id]);
        if (!part) continue;
        if (typeof part.precio !== "number") return null;
        total += part.precio;
    }
    return total;
}

export function quoteText(build, use, budget) {
    const lines = ["Hola Tech Computer, quiero cotizar esta PC:"];
    if (use) lines.push(`Uso: ${USES[use].label}. Presupuesto: ${BUDGETS[budget]?.label ?? "por definir"}.`);
    for (const { id, label } of CATEGORIES) {
        const part = byId(id, build[id]);
        if (part) lines.push(`• ${label}: ${part.name}`);
    }
    return lines.join("\n");
}
