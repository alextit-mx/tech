/*
 * Catálogo de "Arma tu PC". Para actualizarlo solo se edita este archivo.
 *
 * - Procesadores y tarjetas de video: modelos actuales (2026).
 * - Tarjeta madre, memoria, almacenamiento, fuente, enfriamiento y gabinete:
 *   por tipo, para cotizar la marca que haya en existencia.
 * - tier: nivel 1 (entrada) a 6 (entusiasta). gaming/work: rendimiento
 *   relativo en juegos y en trabajo. cost: costo relativo (no es precio).
 * - power: watts que consume trabajando (para calcular la fuente).
 * - precio (opcional, en pesos): si se llena, el configurador suma el total.
 */

export const USES = {
    gaming1080: { label: "Juegos en 1080p", hint: "Competitivos y títulos nuevos con buena fluidez.", kind: "gaming" },
    gaming1440: { label: "Juegos en 1440p", hint: "Más detalle en monitores de 27 pulgadas.", kind: "gaming" },
    gaming4k: { label: "Juegos en 4K", hint: "La mejor imagen en monitores 4K o pantallas grandes.", kind: "gaming" },
    stream: { label: "Jugar y transmitir", hint: "Jugar y transmitir en Twitch o YouTube a la vez.", kind: "stream" },
    create: { label: "Edición, diseño e IA", hint: "Video, 3D, render y modelos de IA en tu equipo.", kind: "work" },
    office: { label: "Escuela y oficina", hint: "Clases, documentos, video y juegos ligeros.", kind: "office" },
};

export const BUDGETS = {
    esencial: { label: "Esencial", hint: "Lo necesario, bien elegido." },
    equilibrada: { label: "Equilibrada", hint: "El mejor rendimiento por peso." },
    alta: { label: "Alta", hint: "Para jugar en alto por años." },
    extrema: { label: "Extrema", hint: "Lo más rápido que hay." },
};

/* Nivel buscado de [procesador, tarjeta de video] según uso y presupuesto (0 = gráficos integrados). */
export const TARGETS = {
    gaming1080: { esencial: [1, 1], equilibrada: [2, 2], alta: [3, 3], extrema: [4, 4] },
    gaming1440: { esencial: [2, 2], equilibrada: [3, 3], alta: [4, 4], extrema: [4, 5] },
    gaming4k: { esencial: [3, 3], equilibrada: [4, 4], alta: [4, 5], extrema: [5, 6] },
    stream: { esencial: [2, 2], equilibrada: [3, 3], alta: [4, 4], extrema: [5, 5] },
    create: { esencial: [2, 1], equilibrada: [3, 2], alta: [4, 3], extrema: [5, 5] },
    office: { esencial: [1, 0], equilibrada: [1, 0], alta: [2, 0], extrema: [3, 1] },
};

/* Memoria (GB) y almacenamiento (TB) buscados. */
export const MEMORY = {
    gaming1080: { esencial: 16, equilibrada: 32, alta: 32, extrema: 32 },
    gaming1440: { esencial: 32, equilibrada: 32, alta: 32, extrema: 64 },
    gaming4k: { esencial: 32, equilibrada: 32, alta: 64, extrema: 64 },
    stream: { esencial: 32, equilibrada: 32, alta: 64, extrema: 64 },
    create: { esencial: 32, equilibrada: 32, alta: 64, extrema: 96 },
    office: { esencial: 16, equilibrada: 16, alta: 32, extrema: 32 },
};

export const STORAGE = {
    esencial: 1, equilibrada: 1, alta: 2, extrema: 4,
};

export const CATEGORIES = [
    { id: "cpu", label: "Procesador", silk: "CPU", icon: "cpu" },
    { id: "board", label: "Tarjeta madre", silk: "MB", icon: "circuit-board" },
    { id: "ram", label: "Memoria RAM", silk: "DIMM", icon: "memory-stick" },
    { id: "gpu", label: "Tarjeta de video", silk: "PCIe x16", icon: "gamepad-2" },
    { id: "storage", label: "Almacenamiento", silk: "M.2", icon: "hard-drive" },
    { id: "cooler", label: "Enfriamiento", silk: "CPU FAN", icon: "fan" },
    { id: "psu", label: "Fuente de poder", silk: "ATX 24P", icon: "zap" },
    { id: "case", label: "Gabinete", silk: "CHASSIS", icon: "box" },
];

export const PARTS = {
    cpu: [
        { id: "r5-5600", name: "AMD Ryzen 5 5600", brand: "AMD", socket: "AM4", cores: "6 núcleos, 12 hilos", power: 90, tier: 1, gaming: 2, work: 1.5, cost: 2, cooler: true, igpu: false,
            note: "La entrada más económica para jugar; plataforma AM4 con DDR4." },
        { id: "i5-14400f", name: "Intel Core i5-14400F", brand: "Intel", socket: "LGA1700", cores: "10 núcleos, 16 hilos", power: 150, tier: 1, gaming: 2.5, work: 2.2, cost: 3, cooler: true, igpu: false,
            note: "Buen rendimiento para jugar en 1080p; necesita tarjeta de video." },
        { id: "r5-8600g", name: "AMD Ryzen 5 8600G", brand: "AMD", socket: "AM5", cores: "6 núcleos, 12 hilos", power: 90, tier: 1, gaming: 1.5, work: 1.8, cost: 3, cooler: true, igpu: true, strongIgpu: true,
            note: "Trae gráficos Radeon 760M: sirve sin tarjeta de video para escuela, oficina y juegos ligeros." },
        { id: "r5-7600", name: "AMD Ryzen 5 7600", brand: "AMD", socket: "AM5", cores: "6 núcleos, 12 hilos", power: 90, tier: 2, gaming: 3, work: 2, cost: 3.5, cooler: true, igpu: true,
            note: "AM5 con DDR5 al mejor precio; se puede actualizar después a un Ryzen más potente." },
        { id: "r5-9600x", name: "AMD Ryzen 5 9600X", brand: "AMD", socket: "AM5", cores: "6 núcleos, 12 hilos", power: 90, tier: 2, gaming: 3.2, work: 2.3, cost: 4, cooler: false, igpu: true,
            note: "Arquitectura Zen 5: muy buen rendimiento para jugar con bajo consumo." },
        { id: "r7-9700x", name: "AMD Ryzen 7 9700X", brand: "AMD", socket: "AM5", cores: "8 núcleos, 16 hilos", power: 90, tier: 3, gaming: 3.5, work: 3, cost: 5.5, cooler: false, igpu: true,
            note: "Ocho núcleos eficientes para jugar y transmitir." },
        { id: "u5-245k", name: "Intel Core Ultra 5 245K", brand: "Intel", socket: "LGA1851", cores: "14 núcleos, 14 hilos", power: 160, tier: 3, gaming: 3.2, work: 3.2, cost: 5, cooler: false, igpu: true,
            note: "Plataforma Intel actual; buen equilibrio entre juegos y trabajo." },
        { id: "r7-9800x3d", name: "AMD Ryzen 7 9800X3D", brand: "AMD", socket: "AM5", cores: "8 núcleos, 16 hilos, 3D V-Cache", power: 160, tier: 4, gaming: 5, work: 3, cost: 8, cooler: false, igpu: true,
            note: "El procesador más rápido para jugar gracias a su caché 3D." },
        { id: "r9-9900x", name: "AMD Ryzen 9 9900X", brand: "AMD", socket: "AM5", cores: "12 núcleos, 24 hilos", power: 160, tier: 4, gaming: 3.6, work: 4, cost: 7, cooler: false, igpu: true,
            note: "Doce núcleos para editar video y renderizar sin esperar." },
        { id: "u7-265k", name: "Intel Core Ultra 7 265K", brand: "Intel", socket: "LGA1851", cores: "20 núcleos, 20 hilos", power: 220, tier: 4, gaming: 3.6, work: 4.2, cost: 6.5, cooler: false, igpu: true,
            note: "Muchos núcleos para trabajo pesado y buen rendimiento en juegos." },
        { id: "r9-9950x3d", name: "AMD Ryzen 9 9950X3D", brand: "AMD", socket: "AM5", cores: "16 núcleos, 32 hilos, 3D V-Cache", power: 230, tier: 5, gaming: 5, work: 5, cost: 12, cooler: false, igpu: true,
            note: "Lo máximo para jugar y crear en el mismo equipo." },
        { id: "u9-285k", name: "Intel Core Ultra 9 285K", brand: "Intel", socket: "LGA1851", cores: "24 núcleos, 24 hilos", power: 250, tier: 5, gaming: 3.9, work: 4.8, cost: 11, cooler: false, igpu: true,
            note: "El Intel más potente para render, compilación y edición." },
    ],

    board: [
        { id: "b550m", name: "AMD B550 Micro-ATX", socket: "AM4", mem: "DDR4", form: "mATX", tier: 1, cost: 2, gen5: false,
            note: "Para Ryzen 5000 con DDR4." },
        { id: "b550", name: "AMD B550 ATX con Wi-Fi", socket: "AM4", mem: "DDR4", form: "ATX", tier: 2, cost: 3, gen5: false,
            note: "AM4 con más puertos y Wi-Fi." },
        { id: "a620m", name: "AMD A620 Micro-ATX", socket: "AM5", mem: "DDR5", form: "mATX", tier: 1, cost: 2, gen5: false, maxCpu: 120,
            note: "La entrada a AM5; pensada para procesadores de 65 W." },
        { id: "b650m", name: "AMD B650 Micro-ATX con Wi-Fi", socket: "AM5", mem: "DDR5", form: "mATX", tier: 2, cost: 3, gen5: false,
            note: "La más elegida para Ryzen 7000 y 9000." },
        { id: "b650", name: "AMD B650 ATX con Wi-Fi", socket: "AM5", mem: "DDR5", form: "ATX", tier: 3, cost: 3.6, gen5: false,
            note: "Tamaño completo con buena alimentación para cualquier Ryzen." },
        { id: "b650i", name: "AMD B650 Mini-ITX con Wi-Fi", socket: "AM5", mem: "DDR5", form: "ITX", tier: 3, cost: 5, gen5: false,
            note: "Para PCs compactas." },
        { id: "b850", name: "AMD B850 ATX con Wi-Fi 7", socket: "AM5", mem: "DDR5", form: "ATX", tier: 3, cost: 4.5, gen5: true,
            note: "Wi-Fi 7 y ranura M.2 Gen5." },
        { id: "x870", name: "AMD X870 ATX con Wi-Fi 7 y USB4", socket: "AM5", mem: "DDR5", form: "ATX", tier: 4, cost: 6, gen5: true,
            note: "USB4, PCIe 5.0 y alimentación para los Ryzen 9." },
        { id: "x870e", name: "AMD X870E ATX gama alta", socket: "AM5", mem: "DDR5", form: "ATX", tier: 5, cost: 9, gen5: true,
            note: "Lo mejor de AM5: más ranuras Gen5 y alimentación reforzada." },
        { id: "h610m-d4", name: "Intel H610 Micro-ATX DDR4", socket: "LGA1700", mem: "DDR4", form: "mATX", tier: 1, cost: 1.8, gen5: false,
            note: "La opción más económica para Core de 12.ª a 14.ª generación." },
        { id: "b760m", name: "Intel B760 Micro-ATX DDR5 con Wi-Fi", socket: "LGA1700", mem: "DDR5", form: "mATX", tier: 2, cost: 3, gen5: false,
            note: "Core de 14.ª generación con DDR5." },
        { id: "b760", name: "Intel B760 ATX DDR5 con Wi-Fi", socket: "LGA1700", mem: "DDR5", form: "ATX", tier: 2, cost: 3.6, gen5: false,
            note: "Tamaño completo para Core de 14.ª generación." },
        { id: "b860m", name: "Intel B860 Micro-ATX con Wi-Fi", socket: "LGA1851", mem: "DDR5", form: "mATX", tier: 2, cost: 3.6, gen5: false,
            note: "Para Core Ultra 200S." },
        { id: "b860", name: "Intel B860 ATX con Wi-Fi", socket: "LGA1851", mem: "DDR5", form: "ATX", tier: 3, cost: 4.5, gen5: false,
            note: "Core Ultra en tamaño completo." },
        { id: "z890", name: "Intel Z890 ATX con Wi-Fi 7 y Thunderbolt", socket: "LGA1851", mem: "DDR5", form: "ATX", tier: 4, cost: 7, gen5: true,
            note: "Para los Core Ultra 7 y 9: overclock, Thunderbolt y M.2 Gen5." },
    ],

    ram: [
        { id: "ddr4-16", name: "16 GB DDR4 (2 × 8 GB) 3200 MT/s", mem: "DDR4", size: 16, tier: 1, cost: 1 },
        { id: "ddr4-32", name: "32 GB DDR4 (2 × 16 GB) 3600 MT/s", mem: "DDR4", size: 32, tier: 2, cost: 2 },
        { id: "ddr5-16", name: "16 GB DDR5 (2 × 8 GB) 5600 MT/s", mem: "DDR5", size: 16, tier: 1, cost: 1.6 },
        { id: "ddr5-32", name: "32 GB DDR5 (2 × 16 GB) 6000 MT/s CL30", mem: "DDR5", size: 32, tier: 3, cost: 2.8,
            note: "La medida ideal para jugar hoy." },
        { id: "ddr5-64", name: "64 GB DDR5 (2 × 32 GB) 6000 MT/s", mem: "DDR5", size: 64, tier: 4, cost: 5 },
        { id: "ddr5-96", name: "96 GB DDR5 (2 × 48 GB) 6000 MT/s", mem: "DDR5", size: 96, tier: 5, cost: 7.5,
            note: "Para proyectos grandes de video, 3D e IA." },
    ],

    gpu: [
        { id: "none", name: "Sin tarjeta de video", brand: "", vram: 0, power: 0, length: 0, tier: 0, gaming: 0, cost: 0,
            note: "Usa los gráficos del procesador." },
        { id: "arc-b580", name: "Intel Arc B580 12 GB", brand: "Intel", vram: 12, power: 190, length: 270, tier: 1, gaming: 1.8, cost: 2.6,
            note: "12 GB de memoria a buen precio para 1080p." },
        { id: "rtx-5060", name: "NVIDIA GeForce RTX 5060 8 GB", brand: "NVIDIA", vram: 8, power: 145, length: 240, tier: 1, gaming: 2, cost: 3,
            note: "1080p en alto con DLSS 4 y generación de cuadros." },
        { id: "rx-9060xt", name: "AMD Radeon RX 9060 XT 16 GB", brand: "AMD", vram: 16, power: 180, length: 260, tier: 2, gaming: 2.5, cost: 3.6,
            note: "16 GB para 1080p ultra y 1440p." },
        { id: "rtx-5060ti", name: "NVIDIA GeForce RTX 5060 Ti 16 GB", brand: "NVIDIA", vram: 16, power: 180, length: 250, tier: 2, gaming: 2.6, cost: 4,
            note: "16 GB, DLSS 4 y bajo consumo." },
        { id: "rtx-5070", name: "NVIDIA GeForce RTX 5070 12 GB", brand: "NVIDIA", vram: 12, power: 250, length: 290, tier: 3, gaming: 3.4, cost: 5.5,
            note: "1440p en alto con trazado de rayos." },
        { id: "rx-9070", name: "AMD Radeon RX 9070 16 GB", brand: "AMD", vram: 16, power: 220, length: 300, tier: 3, gaming: 3.6, cost: 5.8,
            note: "16 GB y FSR 4 para 1440p." },
        { id: "rx-9070xt", name: "AMD Radeon RX 9070 XT 16 GB", brand: "AMD", vram: 16, power: 304, length: 330, tier: 4, gaming: 4.3, cost: 7,
            note: "1440p en ultra y 4K con escalado." },
        { id: "rtx-5070ti", name: "NVIDIA GeForce RTX 5070 Ti 16 GB", brand: "NVIDIA", vram: 16, power: 300, length: 320, tier: 4, gaming: 4.4, cost: 8, cable16: true,
            note: "1440p en ultra y 4K con DLSS 4." },
        { id: "rtx-5080", name: "NVIDIA GeForce RTX 5080 16 GB", brand: "NVIDIA", vram: 16, power: 360, length: 330, tier: 5, gaming: 5, cost: 11, cable16: true,
            note: "4K en ultra con trazado de rayos." },
        { id: "rtx-5090", name: "NVIDIA GeForce RTX 5090 32 GB", brand: "NVIDIA", vram: 32, power: 575, length: 350, tier: 6, gaming: 6.5, cost: 22, cable16: true,
            note: "La tarjeta más potente: 4K sin concesiones y 32 GB para IA." },
    ],

    storage: [
        { id: "nvme-500", name: "SSD NVMe Gen4 500 GB", size: 0.5, gen: 4, tier: 1, cost: 1 },
        { id: "nvme-1", name: "SSD NVMe Gen4 1 TB", size: 1, gen: 4, tier: 2, cost: 1.8 },
        { id: "nvme-2", name: "SSD NVMe Gen4 2 TB", size: 2, gen: 4, tier: 3, cost: 3.2 },
        { id: "nvme5-2", name: "SSD NVMe Gen5 2 TB", size: 2, gen: 5, tier: 4, cost: 5,
            note: "Lectura más rápida para proyectos pesados." },
        { id: "nvme-4", name: "SSD NVMe Gen4 4 TB", size: 4, gen: 4, tier: 4, cost: 6 },
    ],

    cooler: [
        { id: "stock", name: "Disipador incluido con el procesador", kind: "stock", maxPower: 110, height: 60, radiator: 0, tier: 1, cost: 0 },
        { id: "air-120", name: "Disipador de torre 120 mm", kind: "air", maxPower: 170, height: 155, radiator: 0, tier: 2, cost: 1 },
        { id: "air-dual", name: "Disipador de doble torre", kind: "air", maxPower: 230, height: 160, radiator: 0, tier: 3, cost: 2.4 },
        { id: "aio-240", name: "Enfriamiento líquido 240 mm", kind: "aio", maxPower: 230, height: 0, radiator: 240, tier: 3, cost: 3 },
        { id: "aio-360", name: "Enfriamiento líquido 360 mm", kind: "aio", maxPower: 300, height: 0, radiator: 360, tier: 4, cost: 4.5,
            note: "Para los procesadores de más consumo y menos ruido." },
    ],

    psu: [
        { id: "psu-550", name: "Fuente 550 W 80 Plus Bronze", watts: 550, atx31: false, tier: 1, cost: 1.2 },
        { id: "psu-650", name: "Fuente 650 W 80 Plus Gold", watts: 650, atx31: false, tier: 2, cost: 2 },
        { id: "psu-750", name: "Fuente 750 W 80 Plus Gold ATX 3.1", watts: 750, atx31: true, tier: 3, cost: 2.8 },
        { id: "psu-850", name: "Fuente 850 W 80 Plus Gold ATX 3.1", watts: 850, atx31: true, tier: 4, cost: 3.4 },
        { id: "psu-1000", name: "Fuente 1000 W 80 Plus Gold ATX 3.1", watts: 1000, atx31: true, tier: 5, cost: 4.6 },
        { id: "psu-1200", name: "Fuente 1200 W 80 Plus Platinum ATX 3.1", watts: 1200, atx31: true, tier: 6, cost: 7 },
    ],

    case: [
        { id: "case-itx", name: "Gabinete Mini-ITX compacto", forms: ["ITX"], maxGpu: 320, radiator: 240, coolerHeight: 70, tier: 3, cost: 3 },
        { id: "case-matx", name: "Gabinete Micro-ATX con frente de malla", forms: ["mATX", "ITX"], maxGpu: 330, radiator: 240, coolerHeight: 160, tier: 1, cost: 1.5 },
        { id: "case-atx", name: "Gabinete ATX de flujo de aire con 3 ventiladores", forms: ["ATX", "mATX", "ITX"], maxGpu: 360, radiator: 360, coolerHeight: 165, tier: 2, cost: 2.2 },
        { id: "case-glass", name: "Gabinete ATX de vidrio panorámico", forms: ["ATX", "mATX", "ITX"], maxGpu: 400, radiator: 360, coolerHeight: 170, tier: 3, cost: 3.5,
            note: "Vidrio en dos caras para lucir los componentes." },
        { id: "case-full", name: "Gabinete Full Tower", forms: ["ATX", "mATX", "ITX"], maxGpu: 450, radiator: 420, coolerHeight: 185, tier: 5, cost: 5.5,
            note: "Espacio de sobra para tarjetas grandes y enfriamiento líquido." },
    ],
};
