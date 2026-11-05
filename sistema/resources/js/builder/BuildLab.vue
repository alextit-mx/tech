<script setup lang="ts">
import gsap from 'gsap';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    shallowRef,
    watch,
} from 'vue';
import LootCard from './LootCard.vue';
import { suggested } from '@/actions/App/Http/Controllers/Site/BuilderController';
import {
    BUDGETS,
    consumption,
    incompatibility,
    keySpecs,
    missingSlots,
    powerLevel,
    rarity,
    issues,
    recommendedPsu,
    resolutions,
    SLOT_INFO,
    SLOTS,
    USES,
} from './rules';
import type { Budget, Build, Extra, Part, Slot, Use } from './rules';
import { PcScene, webglAvailable } from './scene';
import { setSound, sfx, soundEnabled } from './sfx';

const props = defineProps<{
    catalog: { parts: Part[]; extras: Extra[] };
    /** Personal con sesión iniciada: puede guardar la build como equipo armado de la tienda. */
    staff?: boolean;
}>();

type Phase = 'boot' | 'start' | 'build' | 'setup' | 'finale';
const STORE = 'tc-build';
const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const lowPower =
    (navigator.hardwareConcurrency ?? 8) <= 4 ||
    window.matchMedia('(max-width: 640px)').matches;

const phase = ref<Phase>('boot');
const build = reactive<Build>({});
const extras = ref<number[]>([]);
const active = ref<Slot>('cpu');
const buildName = ref('Mi PC');
const query = ref('');
const sort = ref<'power' | 'price' | 'price-desc'>('price');
const onlyCompatible = ref(true);
const sound = ref(soundEnabled());
const notice = ref('');
const presetLoading = ref(false);
const presetMessage = ref('');
let presetVersion = 0;
let presetAbort: AbortController | null = null;
const shared = ref(false);
const webgl = webglAvailable();
const canvas = ref<HTMLCanvasElement | null>(null);
const cardsEl = ref<HTMLElement | null>(null);
const scene = shallowRef<PcScene | null>(null);
const startUse = ref<Use>('gaming1440');
const startBudget = ref<Budget>('equilibrada');
const showPresets = ref(false);
const bootLines = ref<string[]>([]);
const hasSaved = ref(false);

const form = reactive({
    name: '',
    phone: '',
    email: '',
    notes: '',
    website: '',
});
const sending = ref(false);
const formError = ref('');
const result = ref<{
    folio: string;
    url: string;
    pdf: string;
    whatsapp: string;
    total: number;
    pending: boolean;
} | null>(null);

const parts = props.catalog.parts;
const byId = new Map(parts.map((p) => [p.id, p]));
const extraById = new Map(props.catalog.extras.map((e) => [e.id, e]));

/* ---------------- Derivados ---------------- */

const chosen = computed(() =>
    SLOTS.map((slot) => build[slot]).filter((p): p is Part => Boolean(p)),
);
const chosenExtras = computed(() =>
    extras.value
        .map((id) => extraById.get(id))
        .filter((e): e is Extra => Boolean(e)),
);
const total = computed(() =>
    [...chosen.value, ...chosenExtras.value].reduce(
        (sum, p) => sum + (p.price ?? 0),
        0,
    ),
);
const pending = computed(
    () =>
        [...chosen.value, ...chosenExtras.value].filter((p) => p.price === null)
            .length,
);
const watts = computed(() => consumption(build));
const psuWatts = computed(() => Number(build.psu?.specs.watts ?? 0));
const level = computed(() => powerLevel(build));
const res = computed(() => resolutions(build));
const warns = computed(() =>
    issues(build).map((issue) => ({
        ...issue,
        slot: issue.slots[0],
        blocking: issue.level === 'incompatible',
    })),
);
const blocking = computed(() => warns.value.some((w) => w.blocking));
const missing = computed(() => missingSlots(build));
// Se puede cotizar un kit de actualización o una PC con gráficos integrados;
// solo bloquean las incompatibilidades. Lo que falte se anota en la cotización.
const canQuote = computed(() => chosen.value.length > 0 && !blocking.value);
const powerState = computed(() =>
    !build.psu
        ? 'none'
        : psuWatts.value < watts.value
          ? 'bad'
          : psuWatts.value < recommendedPsu(build)
            ? 'tight'
            : 'ok',
);

const list = computed(() => {
    const term = query.value.trim().toLowerCase();
    return parts
        .filter((p) => p.slot === active.value)
        .filter(
            (p) =>
                !term ||
                `${p.brand ?? ''} ${p.name}`.toLowerCase().includes(term),
        )
        .map((p) => ({
            part: p,
            reason:
                build[active.value]?.id === p.id
                    ? null
                    : incompatibility(p, {
                          ...build,
                          [active.value]: undefined,
                      }),
        }))
        .filter((row) => !onlyCompatible.value || !row.reason)
        .sort((a, b) => {
            if (sort.value === 'power')
                return rarity(b.part, parts) - rarity(a.part, parts);
            const pa = a.part.price ?? 1e9;
            const pb = b.part.price ?? 1e9;
            return sort.value === 'price' ? pa - pb : pb - pa;
        });
});

const optionalHint = computed(() => {
    if (active.value === 'cooler')
        return 'Opcional: muchos procesadores traen disipador. Si buscas silencio o overclock, elige uno.';
    if (active.value === 'gpu' && build.cpu?.specs.igpu === true)
        return 'Opcional: tu procesador tiene gráficos integrados para tareas ligeras.';
    return '';
});

/* Total con conteo animado. */
const shownTotal = ref(0);
const counter = { v: 0 };
watch(
    total,
    (value) =>
        gsap.to(counter, {
            v: value,
            duration: reduced ? 0 : 0.7,
            ease: 'power3.out',
            onUpdate: () => {
                shownTotal.value = Math.round(counter.v);
            },
        }),
    { immediate: true },
);

/* Energía y nivel de poder: cifras que cuentan hacia su valor y escala común para las dos barras. */
function tweened(source: () => number, duration = 0.8) {
    const shown = ref(0);
    const state = { v: 0 };
    watch(
        source,
        (value) =>
            gsap.to(state, {
                v: value,
                duration: reduced ? 0 : duration,
                ease: 'power3.out',
                overwrite: true,
                onUpdate: () => {
                    shown.value = Math.round(state.v);
                },
            }),
        { immediate: true },
    );
    return shown;
}
const recommended = computed(() => recommendedPsu(build));
const shownWatts = tweened(() => watts.value);
const shownPsu = tweened(() => psuWatts.value);
const shownLevel = tweened(() => level.value, 1.1);
const powerScale = computed(
    () =>
        Math.ceil(
            (Math.max(watts.value, psuWatts.value, recommended.value, 300) *
                1.12) /
                100,
        ) * 100,
);
const pct = (w: number) =>
    `${Math.min(100, Math.max(0, (w / powerScale.value) * 100))}%`;
const powerNote = computed(() => {
    if (powerState.value === 'none')
        return `Elige una fuente · sugerida ${recommended.value} W`;
    if (powerState.value === 'bad')
        return `A la fuente le faltan ${watts.value - psuWatts.value} W`;
    if (powerState.value === 'tight')
        return `Justa · recomendada ${recommended.value} W`;
    return `Margen de ${psuWatts.value - watts.value} W`;
});
const levelTier = computed(() =>
    level.value === 0 ? 0 : level.value < 40 ? 1 : level.value < 70 ? 2 : 3,
);
/* Marcas del velocímetro: 11 rayas de 0 a 100, las de 0, 50 y 100 más largas. */
const ticks = Array.from({ length: 11 }, (_, i) => {
    const a = Math.PI * (1 - i / 10);
    const major = i % 5 === 0;
    const [r1, r2] = major ? [36, 43] : [39, 43];
    return {
        i,
        major,
        x1: 60 + Math.cos(a) * r1,
        y1: 62 - Math.sin(a) * r1,
        x2: 60 + Math.cos(a) * r2,
        y2: 62 - Math.sin(a) * r2,
    };
});

const money = (n: number) =>
    '$' + n.toLocaleString('es-MX', { maximumFractionDigits: 0 });

/* ---------------- Acciones ---------------- */

function say(message: string): void {
    notice.value = message;
    window.setTimeout(
        () => notice.value === message && (notice.value = ''),
        3800,
    );
}

function select(slot: Slot): void {
    const changed = active.value !== slot;
    active.value = slot;
    query.value = '';
    sfx.select();
    scene.value?.focus(slot);
    // La lista de la nueva pieza empieza desde arriba (en celular, también la página vuelve al selector).
    if (changed) {
        void nextTick(() => {
            cardsEl.value?.scrollTo({ top: 0 });
            const picker = cardsEl.value?.closest('.picker');
            if (picker && picker.getBoundingClientRect().top < 0)
                picker.scrollIntoView({ block: 'start' });
        });
    }
}

function equip(part: Part, animate = true): void {
    build[part.slot] = part;
    scene.value?.setPart(part.slot, part, animate);
    // Lo que deja de ser compatible se quita y se avisa.
    const removed: string[] = [];
    for (const slot of SLOTS) {
        const other = build[slot];
        if (other && slot !== part.slot && incompatibility(other, build)) {
            delete build[slot];
            scene.value?.setPart(slot, null);
            removed.push(SLOT_INFO[slot].label.toLowerCase());
        }
    }
    if (!animate) return;
    sfx.equip();
    if (removed.length)
        say(`Quitamos ${removed.join(' y ')} porque ya no era compatible.`);
    const next = SLOTS.find((slot) => !build[slot] && slot !== 'cooler');
    if (next && next !== active.value)
        // Espera a que termine la llegada de la pieza (caída + impacto) antes de mover la cámara.
        later(() => select(next), reduced ? 0 : 1900);
}

/* Llegadas de una base recomendada y avances automáticos pendientes: "Desde cero" los cancela. */
const timers: number[] = [];
function later(fn: () => void, ms: number): void {
    timers.push(window.setTimeout(fn, ms));
}

/** Quita de la dirección la build compartida o la base (?b, ?uso…): ya está cargada y guardada en el navegador. */
function cleanUrl(): void {
    const url = new URL(window.location.href);
    ['b', 'n', 'uso', 'presupuesto'].forEach((key) =>
        url.searchParams.delete(key),
    );
    window.history.replaceState(window.history.state, '', url);
}

/** "Desde cero" y "Vaciar": sin piezas ni extras, y la escena 3D vuelve a empezar (sin emblema ni giro del final). */
function startFresh(): void {
    presetVersion++;
    presetAbort?.abort();
    presetLoading.value = false;
    presetMessage.value = '';
    timers.splice(0).forEach((id) => window.clearTimeout(id));
    for (const slot of SLOTS) delete build[slot];
    extras.value = [];
    result.value = null;
    buildName.value = 'Mi PC';
    cleanUrl();
    scene.value?.reset();
    sfx.remove();
    phase.value = 'build';
    // Sin mover la cámara: que se vea la entrada completa; el selector abre en procesador.
    active.value = 'cpu';
    query.value = '';
    void nextTick(() => cardsEl.value?.scrollTo({ top: 0 }));
}

function unequip(slot: Slot): void {
    delete build[slot];
    scene.value?.setPart(slot, null);
    sfx.remove();
}

function pick(part: Part): void {
    if (build[part.slot]?.id === part.id) return unequip(part.slot);
    equip(part);
}

async function applyPreset(use: Use, budget: Budget): Promise<void> {
    const version = ++presetVersion;
    presetAbort?.abort();
    presetAbort = new AbortController();
    timers.splice(0).forEach((id) => window.clearTimeout(id));
    presetLoading.value = true;
    presetMessage.value = '';
    phase.value = 'build';
    let recommended: Build = {};
    try {
        const response = await fetch(
            suggested.url({ query: { uso: use, presupuesto: budget } }),
            {
                signal: presetAbort.signal,
                headers: { Accept: 'application/json' },
            },
        );
        if (!response.ok)
            throw new Error(
                'No pudimos consultar las existencias. Intenta de nuevo.',
            );
        const data = (await response.json()) as {
            parts: Partial<Record<Slot, number>>;
            unavailable: boolean;
            message: string | null;
        };
        if (version !== presetVersion) return;
        presetMessage.value = data.message ?? '';
        if (data.unavailable) {
            for (const slot of SLOTS) delete build[slot];
            extras.value = [];
            result.value = null;
            scene.value?.reset();
            return;
        }
        for (const slot of SLOTS) {
            const part = byId.get(data.parts[slot] ?? 0);
            if (data.parts[slot] && !part)
                throw new Error(
                    'El catálogo cambió. Recarga la página para ver las existencias de hoy.',
                );
            if (part) recommended[slot] = part;
        }
    } catch (error) {
        if (version === presetVersion)
            presetMessage.value =
                error instanceof Error
                    ? error.message
                    : 'No pudimos consultar las existencias.';
        return;
    } finally {
        if (version === presetVersion) presetLoading.value = false;
    }

    timers.splice(0).forEach((id) => window.clearTimeout(id));
    for (const slot of SLOTS) delete build[slot];
    extras.value = [];
    result.value = null;
    scene.value?.reset();
    SLOTS.forEach((slot, i) => {
        const part = recommended[slot];
        if (!part) return;
        later(
            () => {
                build[slot] = part;
                scene.value?.setPart(slot, part);
                sfx.equip();
            },
            reduced ? 0 : i * 260,
        );
    });
    buildName.value = `${USES[use]} · ${BUDGETS[budget]}`;
    phase.value = 'build';
    later(
        () => select(SLOTS.find((slot) => !recommended[slot]) ?? 'gpu'),
        reduced ? 0 : SLOTS.length * 260,
    );
}

function shareUrl(): string {
    const url = new URL(window.location.href);
    url.search = '';
    url.searchParams.set('b', chosen.value.map((p) => p.id).join('.'));
    if (buildName.value) url.searchParams.set('n', buildName.value);
    return url.toString();
}

function saveAsBuild(): void {
    const qs = new URLSearchParams({
        b: chosen.value.map((p) => p.id).join('.'),
        n: buildName.value,
    });
    window.open(`/admin/builds/create?${qs}`, '_blank', 'noopener');
}

async function share(): Promise<void> {
    const url = shareUrl();
    try {
        if (navigator.share && window.matchMedia('(pointer: coarse)').matches)
            await navigator.share({ title: buildName.value, url });
        else await navigator.clipboard.writeText(url);
        shared.value = true;
        window.setTimeout(() => (shared.value = false), 2200);
    } catch {
        say(url);
    }
}

function toggleSound(): void {
    sound.value = !sound.value;
    setSound(sound.value);
}

function toggleExtra(id: number): void {
    extras.value = extras.value.includes(id)
        ? extras.value.filter((x) => x !== id)
        : [...extras.value, id];
    sfx.select();
}

async function finale(): Promise<void> {
    if (!canQuote.value) return;
    phase.value = 'finale';
    if (window.matchMedia('(max-width: 1023px)').matches)
        document.querySelector('.build-main')?.scrollIntoView({
            behavior: reduced ? 'instant' : 'smooth',
            block: 'start',
        });
    sfx.complete();
    await scene.value?.celebrate();
}

async function submit(): Promise<void> {
    formError.value = '';
    sending.value = true;
    try {
        const token =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content ?? '';
        const response = await fetch('/arma-tu-pc/cotizar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({
                ...form,
                build_name: buildName.value,
                parts: chosen.value.map((p) => p.id),
                extras: extras.value,
            }),
        });
        const data = await response.json();
        if (!response.ok) {
            formError.value =
                data.message ??
                'No pudimos generar tu cotización. Intenta de nuevo.';
            sfx.error();
            return;
        }
        result.value = data;
        sfx.complete();
    } catch {
        formError.value =
            'Sin conexión. Revisa tu internet e intenta de nuevo.';
    } finally {
        sending.value = false;
    }
}

/* ---------------- Arranque y persistencia ---------------- */

function restore(): boolean {
    const params = new URLSearchParams(window.location.search);
    let ids: number[] = [];
    let saved: { name?: string; ids?: number[]; extras?: number[] } = {};
    try {
        saved = JSON.parse(localStorage.getItem(STORE) ?? '{}');
    } catch {
        saved = {};
    }
    if (params.get('b')) {
        ids = params.get('b')!.split('.').map(Number);
        buildName.value = params.get('n') ?? 'Mi PC';
    } else if (saved.ids?.length) {
        ids = saved.ids;
        buildName.value = saved.name ?? 'Mi PC';
        extras.value = (saved.extras ?? []).filter((id) => extraById.has(id));
    }
    ids.map((id) => byId.get(id)).forEach((part) => part && equip(part, false));
    return chosen.value.length > 0;
}

watch(
    () =>
        [chosen.value.map((p) => p.id), extras.value, buildName.value] as const,
    ([ids, extraIds, name]) => {
        try {
            localStorage.setItem(
                STORE,
                JSON.stringify({ ids, extras: extraIds, name }),
            );
        } catch {
            /* Sin almacenamiento disponible. */
        }
    },
);

async function boot(): Promise<void> {
    let seen = false;
    try {
        // Quien abre una build compartida viene a verla: sin arranque.
        seen =
            sessionStorage.getItem('tc-boot') === '1' ||
            new URLSearchParams(window.location.search).has('b') ||
            // El loader con el emblema ya hizo de arranque: no se repite la secuencia BIOS.
            document.querySelector('[data-lab-loader]') !== null;
        sessionStorage.setItem('tc-boot', '1');
    } catch {
        seen = false;
    }
    const lines = [
        'TECH COMPUTER · MODO ARMADO v26',
        'Inicializando sistema…',
        `Detectando componentes: ${parts.length} piezas en existencia… OK`,
        'Calibrando RGB… OK',
        'Listo para armar.',
    ];
    if (!seen && !reduced) {
        for (const line of lines) {
            bootLines.value.push(line);
            await new Promise((r) => window.setTimeout(r, 380));
            if (phase.value !== 'boot') return;
        }
        await new Promise((r) => window.setTimeout(r, 300));
    }
    endBoot();
}

function endBoot(): void {
    if (phase.value !== 'boot') return;
    const params = new URLSearchParams(window.location.search);
    const use = params.get('uso') as Use | null;
    const budget = params.get('presupuesto') as Budget | null;
    if (use && use in USES && budget && budget in BUDGETS)
        applyPreset(use, budget);
    else phase.value = params.get('b') ? 'build' : 'start';
    // Al recargar manda lo guardado en el navegador, no el enlace con el que se llegó.
    cleanUrl();
}

onMounted(async () => {
    await nextTick();
    if (webgl && canvas.value)
        scene.value = new PcScene(canvas.value, {
            reduced,
            lowPower,
            // Clic en una pieza de la escena = abrir su slot en el selector.
            onPick: (slot) => phase.value === 'build' && select(slot),
        });
    hasSaved.value = restore();
    void boot();
    window.addEventListener('keydown', onKey);
    // Dos cuadros después la escena ya pintó (y compiló sus shaders): el loader de la página puede irse.
    requestAnimationFrame(() =>
        requestAnimationFrame(() =>
            window.dispatchEvent(new Event('build-lab:ready')),
        ),
    );
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    presetAbort?.abort();
    timers.splice(0).forEach((id) => window.clearTimeout(id));
    scene.value?.destroy();
});

function onKey(event: KeyboardEvent): void {
    if (phase.value === 'boot') endBoot();
    else if (
        event.key === 'Escape' &&
        (phase.value === 'setup' || phase.value === 'finale') &&
        !result.value
    )
        phase.value = 'build';
}
</script>

<template>
    <div class="lab" :class="`is-${phase}`" :aria-busy="presetLoading">
        <p
            v-if="presetLoading || presetMessage"
            role="status"
            class="fixed bottom-6 left-1/2 z-50 max-w-lg -translate-x-1/2 rounded-lg bg-zinc-900 p-4 text-white"
        >
            {{
                presetLoading ? 'Buscando un armado compatible…' : presetMessage
            }}
        </p>
        <div class="stage" aria-hidden="true">
            <canvas v-if="webgl" ref="canvas"></canvas>
            <svg v-else class="schematic" viewBox="0 0 440 480">
                <rect
                    x="10"
                    y="10"
                    width="420"
                    height="460"
                    rx="8"
                    class="sch-case"
                    :class="{ on: build.case }"
                />
                <rect
                    x="40"
                    y="40"
                    width="250"
                    height="290"
                    rx="4"
                    class="sch"
                    :class="{ on: build.motherboard }"
                />
                <rect
                    x="120"
                    y="80"
                    width="60"
                    height="60"
                    class="sch"
                    :class="{ on: build.cpu }"
                />
                <rect
                    x="210"
                    y="70"
                    width="40"
                    height="120"
                    class="sch"
                    :class="{ on: build.ram }"
                />
                <rect
                    x="60"
                    y="230"
                    width="300"
                    height="50"
                    class="sch"
                    :class="{ on: build.gpu }"
                />
                <rect
                    x="110"
                    y="170"
                    width="90"
                    height="22"
                    class="sch"
                    :class="{ on: build.storage }"
                />
                <rect
                    x="40"
                    y="380"
                    width="160"
                    height="70"
                    class="sch"
                    :class="{ on: build.psu }"
                />
                <circle
                    cx="150"
                    cy="110"
                    r="52"
                    class="sch"
                    :class="{ on: build.cooler }"
                />
            </svg>
            <div class="scanlines"></div>
        </div>

        <!-- HUD superior -->
        <header class="hud">
            <label class="hud-cell hud-name">
                <span class="silk">BUILD</span>
                <input
                    v-model="buildName"
                    maxlength="40"
                    aria-label="Nombre de tu PC"
                />
            </label>
            <div class="hud-cell hud-total">
                <span class="silk">TOTAL ESTIMADO CON IVA</span>
                <strong aria-live="polite">{{ money(shownTotal) }}</strong>
                <small v-if="pending">+ {{ pending }} por confirmar</small>
            </div>
            <div
                class="hud-cell hud-power"
                :data-state="powerState"
                role="group"
                :aria-label="`Energía: requiere ${watts} W${psuWatts ? `, fuente de ${psuWatts} W` : ''}. ${powerNote}`"
            >
                <span class="silk">ENERGÍA</span>
                <div class="pbar">
                    <span class="pbar-label">Requiere</span>
                    <span class="pbar-track">
                        <i
                            class="pbar-fill need"
                            :style="{ width: pct(watts) }"
                        ></i>
                        <b
                            v-if="watts"
                            class="pbar-mark"
                            :style="{ left: pct(recommended) }"
                            :title="`Fuente recomendada: ${recommended} W`"
                        ></b>
                    </span>
                    <span class="pbar-value">{{ shownWatts }} W</span>
                </div>
                <div class="pbar">
                    <span class="pbar-label">Fuente</span>
                    <span class="pbar-track">
                        <i
                            class="pbar-fill supply"
                            :style="{ width: pct(psuWatts) }"
                        ></i>
                    </span>
                    <span class="pbar-value">{{
                        psuWatts ? `${shownPsu} W` : '—'
                    }}</span>
                </div>
                <small>{{ powerNote }}</small>
            </div>
            <div class="hud-cell hud-level" :data-tier="levelTier">
                <svg viewBox="0 0 120 68" class="speedo" aria-hidden="true">
                    <defs>
                        <linearGradient
                            id="speedo-grad"
                            x1="17"
                            y1="0"
                            x2="103"
                            y2="0"
                            gradientUnits="userSpaceOnUse"
                        >
                            <stop offset="0" stop-color="#3be07a" />
                            <stop offset="0.55" stop-color="#ffb020" />
                            <stop offset="1" stop-color="#ff2238" />
                        </linearGradient>
                    </defs>
                    <path class="speedo-track" d="M12 62 A48 48 0 0 1 108 62" />
                    <path
                        class="speedo-fill"
                        d="M12 62 A48 48 0 0 1 108 62"
                        pathLength="100"
                        :style="{ strokeDashoffset: 100 - level }"
                    />
                    <line
                        v-for="t in ticks"
                        :key="t.i"
                        class="speedo-tick"
                        :class="{ major: t.major }"
                        :x1="t.x1"
                        :y1="t.y1"
                        :x2="t.x2"
                        :y2="t.y2"
                    />
                    <g
                        class="speedo-needle"
                        :style="{ transform: `rotate(${level * 1.8 - 90}deg)` }"
                    >
                        <g class="speedo-wobble">
                            <path
                                d="M58.3 62 L59.5 22 L60 18 L60.5 22 L61.7 62 Z"
                            />
                        </g>
                    </g>
                    <circle cx="60" cy="62" r="5" class="speedo-hub" />
                </svg>
                <div>
                    <span class="silk">NIVEL DE PODER</span>
                    <strong aria-live="polite">{{ shownLevel }}</strong>
                    <span class="res">
                        <span
                            v-for="r in res"
                            :key="r.label"
                            :class="{ on: r.on }"
                            >{{ r.label }}</span
                        >
                    </span>
                </div>
            </div>
            <div
                class="hud-cell hud-status"
                :class="{ bad: blocking, good: canQuote }"
                aria-live="polite"
            >
                {{
                    blocking
                        ? `${warns.filter((w) => w.blocking).length} avisos`
                        : canQuote
                          ? 'Todo compatible'
                          : `Faltan ${missing.length}`
                }}
            </div>
            <button
                class="hud-sound"
                type="button"
                :aria-pressed="sound"
                :aria-label="sound ? 'Apagar sonido' : 'Encender sonido'"
                @click="toggleSound"
            >
                <span
                    class="mask"
                    :style="{
                        '--icon': `url('https://unpkg.com/lucide-static@0.469.0/icons/${sound ? 'volume-2' : 'volume-x'}.svg')`,
                    }"
                ></span>
            </button>
        </header>

        <!-- Riel de slots -->
        <nav class="rail" aria-label="Piezas de tu PC">
            <button
                v-for="slot in SLOTS"
                :key="slot"
                type="button"
                class="slot"
                :class="{
                    active: active === slot,
                    equipped: build[slot],
                    warn: warns.some((w) => w.slot === slot && w.blocking),
                }"
                :aria-current="active === slot"
                @click="select(slot)"
            >
                <span class="slot-silk">{{ SLOT_INFO[slot].silk }}</span>
                <span class="slot-label">{{ SLOT_INFO[slot].label }}</span>
                <span v-if="build[slot]" class="slot-part">
                    <span class="slot-name">{{ build[slot]!.name }}</span>
                    <span class="slot-price">{{
                        build[slot]!.price === null
                            ? 'Cotizar'
                            : money(build[slot]!.price!)
                    }}</span>
                </span>
                <span v-else class="slot-plus" aria-hidden="true">+</span>
            </button>
        </nav>

        <!-- Selector -->
        <section
            class="picker"
            :aria-label="`Elegir ${SLOT_INFO[active].label}`"
        >
            <header class="picker-head">
                <div>
                    <span class="silk">{{ SLOT_INFO[active].silk }}</span>
                    <h2>{{ SLOT_INFO[active].label }}</h2>
                </div>
                <button
                    v-if="build[active]"
                    type="button"
                    class="chip-btn"
                    @click="unequip(active)"
                >
                    Quitar
                </button>
            </header>
            <div class="picker-tools">
                <input
                    v-model="query"
                    type="search"
                    placeholder="Buscar modelo"
                    aria-label="Buscar modelo"
                />
                <select v-model="sort" aria-label="Ordenar">
                    <option value="price">Menor precio</option>
                    <option value="price-desc">Mayor precio</option>
                    <option value="power">Más poder</option>
                </select>
                <label class="toggle"
                    ><input v-model="onlyCompatible" type="checkbox" /> Solo
                    compatibles</label
                >
            </div>
            <p v-if="optionalHint" class="picker-hint">{{ optionalHint }}</p>
            <div ref="cardsEl" class="cards">
                <LootCard
                    v-for="row in list"
                    :key="row.part.id"
                    :name="row.part.name"
                    :brand="row.part.brand"
                    :image="row.part.image"
                    :price="row.part.price"
                    :stock="row.part.stock"
                    :stock-label="row.part.stockLabel"
                    :specs="keySpecs(row.part)"
                    :rarity="rarity(row.part, parts)"
                    :equipped="build[active]?.id === row.part.id"
                    :reason="row.reason"
                    @pick="pick(row.part)"
                    @hover="sfx.hover()"
                />
            </div>
            <p v-if="!list.length" class="picker-empty">
                No hay piezas {{ onlyCompatible ? 'compatibles ' : '' }}con esa
                búsqueda.
                <a
                    :href="`https://wa.me/525549114435?text=${encodeURIComponent(`Hola, busco ${SLOT_INFO[active].label} para mi PC`)}`"
                    target="_blank"
                    rel="noopener"
                    >Pregúntanos por WhatsApp</a
                >
            </p>
        </section>

        <!-- Barra inferior -->
        <footer class="dock">
            <span class="dock-progress"
                ><b>{{ chosen.length }}</b
                >/8 piezas</span
            >
            <ul v-if="warns.length" class="dock-warns" aria-live="polite">
                <li
                    v-for="w in warns"
                    :key="w.message"
                    :class="{ blocking: w.blocking }"
                >
                    <button type="button" @click="select(w.slot)">
                        {{ w.message }}
                    </button>
                </li>
            </ul>
            <span v-else-if="missing.length" class="dock-missing"
                >Falta:
                {{ missing.map((s) => SLOT_INFO[s].short).join(', ') }}</span
            >
            <span class="dock-actions">
                <button
                    type="button"
                    class="ghost"
                    :disabled="!chosen.length"
                    @click="startFresh"
                >
                    Vaciar
                </button>
                <button
                    type="button"
                    class="ghost"
                    :disabled="!chosen.length"
                    @click="share"
                >
                    {{ shared ? 'Enlace copiado' : 'Compartir build' }}
                </button>
                <button
                    v-if="staff"
                    type="button"
                    class="ghost"
                    :disabled="!chosen.length"
                    title="Solo personal: crea un equipo armado para la tienda con estas piezas"
                    @click="saveAsBuild"
                >
                    Guardar como equipo armado
                </button>
                <button
                    type="button"
                    class="primary"
                    :disabled="!canQuote"
                    @click="phase = 'setup'"
                >
                    Generar cotización
                </button>
            </span>
        </footer>

        <p class="lab-notice" role="status" :class="{ show: notice }">
            {{ notice }}
        </p>

        <!-- Arranque tipo BIOS -->
        <div v-if="phase === 'boot'" class="overlay boot" @click="endBoot">
            <div class="boot-lines">
                <p v-for="(line, i) in bootLines" :key="i">{{ line }}</p>
                <span class="cursor"></span>
            </div>
            <button type="button" class="skip" @click.stop="endBoot">
                Saltar
            </button>
        </div>

        <!-- Cómo empezar -->
        <div
            v-if="phase === 'start'"
            class="overlay start"
            role="dialog"
            aria-modal="true"
            aria-labelledby="start-title"
        >
            <div class="panel-cut">
                <p class="silk">MODO ARMADO</p>
                <h2 id="start-title">¿Cómo quieres empezar?</h2>
                <div class="start-options">
                    <button
                        type="button"
                        class="start-card"
                        @click="startFresh"
                    >
                        <span class="silk">01</span>
                        <strong>Desde cero</strong>
                        <span
                            >Elige cada pieza a tu gusto. Te avisamos si algo no
                            es compatible.</span
                        >
                    </button>
                    <button
                        type="button"
                        class="start-card"
                        :class="{ open: showPresets }"
                        @click="showPresets = true"
                    >
                        <span class="silk">02</span>
                        <strong>Base recomendada</strong>
                        <span
                            >Dinos para qué la quieres y cargamos una base que
                            puedes cambiar.</span
                        >
                    </button>
                    <button
                        v-if="hasSaved"
                        type="button"
                        class="start-card"
                        @click="phase = 'build'"
                    >
                        <span class="silk">03</span>
                        <strong>Continuar mi build</strong>
                        <span
                            >{{ buildName }} · {{ chosen.length }} piezas</span
                        >
                    </button>
                </div>
                <div v-if="showPresets" class="presets">
                    <div class="chips-row">
                        <button
                            v-for="(label, key) in USES"
                            :key="key"
                            type="button"
                            :class="{ on: startUse === key }"
                            @click="startUse = key"
                        >
                            {{ label }}
                        </button>
                    </div>
                    <div class="chips-row">
                        <button
                            v-for="(label, key) in BUDGETS"
                            :key="key"
                            type="button"
                            :class="{ on: startBudget === key }"
                            @click="startBudget = key"
                        >
                            {{ label }}
                        </button>
                    </div>
                    <button
                        type="button"
                        class="primary"
                        @click="applyPreset(startUse, startBudget)"
                    >
                        Cargar base
                    </button>
                </div>
            </div>
        </div>

        <!-- Completa tu setup -->
        <div
            v-if="phase === 'setup'"
            class="overlay setup"
            role="dialog"
            aria-modal="true"
            aria-labelledby="setup-title"
        >
            <div class="panel-cut wide">
                <p class="silk">PASO EXTRA · OPCIONAL</p>
                <h2 id="setup-title">Completa tu setup</h2>
                <p class="muted">
                    Agrega monitor, teclado, mouse o audífonos a la misma
                    cotización.
                </p>
                <div v-if="catalog.extras.length" class="extras">
                    <button
                        v-for="extra in catalog.extras"
                        :key="extra.id"
                        type="button"
                        class="extra"
                        :class="{ on: extras.includes(extra.id) }"
                        :aria-pressed="extras.includes(extra.id)"
                        @click="toggleExtra(extra.id)"
                    >
                        <img
                            v-if="extra.image"
                            :src="extra.image"
                            alt=""
                            width="64"
                            height="64"
                            loading="lazy"
                        />
                        <span class="silk">{{ extra.group }}</span>
                        <span class="extra-name"
                            >{{ extra.brand }} {{ extra.name }}</span
                        >
                        <span class="extra-price">{{
                            extra.price === null
                                ? 'Cotizar'
                                : money(extra.price)
                        }}</span>
                    </button>
                </div>
                <div class="overlay-actions">
                    <button
                        type="button"
                        class="ghost"
                        @click="phase = 'build'"
                    >
                        Volver
                    </button>
                    <button type="button" class="primary" @click="finale">
                        {{
                            extras.length
                                ? `Continuar con ${extras.length} extras`
                                : 'Continuar sin extras'
                        }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Final -->
        <aside
            v-if="phase === 'finale'"
            class="finale"
            role="dialog"
            aria-modal="true"
            aria-labelledby="finale-title"
        >
            <div class="panel-cut">
                <template v-if="!result">
                    <p class="silk glitch" data-text="BUILD COMPLETO">
                        BUILD COMPLETO
                    </p>
                    <h2 id="finale-title">{{ buildName || 'Mi PC' }}</h2>
                    <div class="finale-stats">
                        <span
                            ><b>{{ level }}</b> nivel de poder</span
                        >
                        <span
                            ><b>{{ watts }} W</b> consumo</span
                        >
                        <span
                            ><b>{{
                                res
                                    .filter((r) => r.on)
                                    .map((r) => r.label)
                                    .join(' · ') || '—'
                            }}</b>
                            rinde</span
                        >
                    </div>
                    <ul class="finale-parts">
                        <li v-for="p in chosen" :key="p.id">
                            <span class="silk">{{
                                SLOT_INFO[p.slot].silk
                            }}</span
                            >{{ p.name
                            }}<b>{{
                                p.price === null ? 'Cotizar' : money(p.price)
                            }}</b>
                        </li>
                        <li v-for="e in chosenExtras" :key="`e${e.id}`">
                            <span class="silk">{{ e.group }}</span
                            >{{ e.name
                            }}<b>{{
                                e.price === null ? 'Cotizar' : money(e.price)
                            }}</b>
                        </li>
                    </ul>
                    <p class="finale-total">
                        {{ money(total) }}
                        <small
                            >con IVA{{
                                pending ? ` · ${pending} por confirmar` : ''
                            }}</small
                        >
                    </p>
                    <p v-if="missing.length" class="muted">
                        Sin
                        {{
                            missing
                                .map((s) => SLOT_INFO[s].label.toLowerCase())
                                .join(', ')
                        }}: lo anotamos en tu cotización y te recomendamos
                        opciones.
                    </p>
                    <form class="finale-form" @submit.prevent="submit">
                        <label
                            >Tu nombre
                            <input
                                v-model="form.name"
                                required
                                maxlength="120"
                                autocomplete="name"
                        /></label>
                        <label
                            >WhatsApp
                            <input
                                v-model="form.phone"
                                required
                                inputmode="tel"
                                autocomplete="tel"
                                placeholder="55 1234 5678"
                        /></label>
                        <label
                            >Correo <small>(opcional)</small>
                            <input
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                        /></label>
                        <label
                            >¿Algún comentario? <small>(opcional)</small>
                            <textarea
                                v-model="form.notes"
                                rows="2"
                                maxlength="1000"
                            ></textarea>
                        </label>
                        <label class="hp" aria-hidden="true"
                            >Sitio
                            <input
                                v-model="form.website"
                                tabindex="-1"
                                autocomplete="off"
                        /></label>
                        <p v-if="formError" class="form-error" role="alert">
                            {{ formError }}
                        </p>
                        <div class="overlay-actions">
                            <button
                                type="button"
                                class="ghost"
                                @click="phase = 'build'"
                            >
                                Seguir ajustando
                            </button>
                            <button
                                type="submit"
                                class="primary"
                                :disabled="sending"
                            >
                                {{
                                    sending
                                        ? 'Generando…'
                                        : 'Generar mi cotización'
                                }}
                            </button>
                        </div>
                    </form>
                </template>
                <template v-else>
                    <p class="silk">COTIZACIÓN GENERADA</p>
                    <h2 id="finale-title" class="folio">{{ result.folio }}</h2>
                    <p class="muted">
                        Ya la tenemos. Mándanos el folio por WhatsApp y te
                        confirmamos existencias y precio del día.
                    </p>
                    <p class="finale-total">
                        {{ money(result.total) }}
                        <small
                            >con IVA{{
                                result.pending
                                    ? ' · hay piezas por confirmar'
                                    : ''
                            }}</small
                        >
                    </p>
                    <div class="result-actions">
                        <a
                            class="primary"
                            :href="result.whatsapp"
                            target="_blank"
                            rel="noopener"
                            >Enviar a Tech Computer por WhatsApp</a
                        >
                        <a
                            class="ghost"
                            :href="result.pdf"
                            target="_blank"
                            rel="noopener"
                            >Descargar PDF</a
                        >
                        <button type="button" class="ghost" @click="share">
                            {{
                                shared ? 'Enlace copiado' : 'Compartir mi build'
                            }}
                        </button>
                        <button
                            type="button"
                            class="ghost"
                            @click="phase = 'build'"
                        >
                            Volver a mi build
                        </button>
                    </div>
                </template>
            </div>
        </aside>
    </div>
</template>
