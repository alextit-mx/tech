<script setup lang="ts">
import {
    ArrowLeft,
    ExternalLink,
    ImagePlus,
    Link2,
    LoaderCircle,
    Search,
    TriangleAlert,
    X,
} from '@lucide/vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AlertError from '@/components/AlertError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { firstError, http } from '@/lib/http';

type Part = {
    id: number;
    name: string;
    sku: string;
    brand: string | null;
    image: string;
    price: number | null;
    cost: number | null;
    published: boolean;
    stock: 'ok' | 'low' | 'none';
    stock_label: string;
    issue?: string | null;
};
type Check = {
    parts: Record<string, Part>;
    errors: string[];
    issues: { level: string; code: string; message: string }[];
    unverified: string[];
    missing: string[];
    hidden: string[];
    total: number | null;
    cost: number | null;
    margin?: number | null;
    watts: number;
};

const props = defineProps<{
    build: {
        id: number;
        name: string;
        slug: string;
        tagline: string | null;
        use: string;
        is_published: boolean;
        image_url: string | null;
    } | null;
    prefill: { name: string | null };
    parts: Record<string, Part>;
    slots: Record<string, string>;
    uses: Record<string, string>;
    canMargins: boolean;
}>();

const chosen = reactive<Record<string, Part | null>>(
    Object.fromEntries(
        Object.keys(props.slots).map((s) => [s, props.parts[s] ?? null]),
    ),
);
const form = useForm({
    name: props.build?.name ?? props.prefill.name ?? '',
    tagline: props.build?.tagline ?? '',
    use: props.build?.use ?? 'gaming1440',
    is_published: props.build?.is_published ?? false,
    image: null as File | null,
    remove_image: false,
});
const preview = ref<string | null>(props.build?.image_url ?? null);
const money = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0,
});
const dot = { ok: 'bg-emerald-500', low: 'bg-amber-500', none: 'bg-zinc-400' };
const ids = computed(() =>
    Object.fromEntries(
        Object.entries(chosen)
            .filter(([, p]) => p)
            .map(([s, p]) => [s, p!.id]),
    ),
);

/* Compatibilidad y totales en vivo. */
const check = ref<Check | null>(null);
const checking = ref(false);
async function refresh() {
    checking.value = true;
    const qs = new URLSearchParams(
        Object.entries(ids.value).map(([s, id]) => [`parts[${s}]`, String(id)]),
    );
    const res = await http<Check>('GET', `/admin/builds/check?${qs}`);
    if (res.ok) check.value = res.data;
    checking.value = false;
}
watch(ids, refresh, { deep: true });
onMounted(refresh);

/* Selector de pieza por slot. */
const picking = ref<string | null>(null);
const q = ref('');
const results = ref<Part[]>([]);
const searching = ref(false);
async function search() {
    if (!picking.value) return;
    searching.value = true;
    const qs = new URLSearchParams({ slot: picking.value, q: q.value });
    Object.entries(ids.value).forEach(([s, id]) =>
        qs.append(`current[${s}]`, String(id)),
    );
    const res = await http<{ items: Part[] }>(
        'GET',
        `/admin/builds/parts?${qs}`,
    );
    results.value = res.ok ? res.data.items : [];
    searching.value = false;
}
const searchLater = useDebounceFn(search, 250);
watch(q, searchLater);
function open(slot: string) {
    picking.value = picking.value === slot ? null : slot;
    q.value = '';
    results.value = [];
    if (picking.value) void search();
}
function choose(slot: string, part: Part) {
    chosen[slot] = part;
    picking.value = null;
}

/* Importar el enlace de "Compartir build" de Arma tu PC. */
const link = ref('');
async function importLink() {
    let url: URL;
    try {
        url = new URL(link.value.trim(), location.origin);
    } catch {
        toast.error('Pega el enlace completo de Arma tu PC.');
        return;
    }
    const b = (url.searchParams.get('b') ?? '').split('.').filter(Boolean);
    if (!b.length) {
        toast.error('Ese enlace no trae piezas (debe tener «?b=»).');
        return;
    }
    const res = await http<Check>(
        'GET',
        `/admin/builds/check?${new URLSearchParams(b.map((id, i) => [`parts[${i}]`, id]))}`,
    );
    if (!res.ok) {
        toast.error(firstError(res.data));
        return;
    }
    Object.keys(chosen).forEach((s) => (chosen[s] = res.data.parts[s] ?? null));
    if (!form.name && url.searchParams.get('n'))
        form.name = url.searchParams.get('n')!.slice(0, 80);
    link.value = '';
    toast.success(`${Object.keys(res.data.parts).length} piezas importadas.`);
}

function pickImage(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.image = file;
    form.remove_image = false;
    if (file) preview.value = URL.createObjectURL(file);
}
function clearImage() {
    form.image = null;
    form.remove_image = true;
    preview.value = null;
}

function save() {
    form.transform((d) => ({ ...d, parts: ids.value })).post(
        props.build ? `/admin/builds/${props.build.id}` : '/admin/builds',
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => toast.success('Equipo guardado.'),
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'Revisa los datos.'),
        },
    );
}
const field = 'h-9 w-full rounded-md border bg-background px-2 text-sm';
</script>

<template>
    <Head :title="build ? build.name : 'Nuevo equipo armado'" />
    <section class="mx-auto grid w-full max-w-6xl gap-4 p-4 md:p-6">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <Link
                    href="/admin/builds"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    ><ArrowLeft class="size-4" /> Equipos armados</Link
                >
                <h1 class="mt-1 text-2xl font-bold tracking-tight md:text-3xl">
                    {{ build ? build.name : 'Nuevo equipo armado' }}
                </h1>
            </div>
            <a
                v-if="build?.is_published"
                :href="`/equipos/${build.slug}`"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >Ver en tienda <ExternalLink class="size-3.5"
            /></a>
        </header>
        <AlertError
            v-if="Object.keys(form.errors).length"
            :errors="Object.values(form.errors)"
        />

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="grid min-w-0 gap-6">
                <section
                    class="grid gap-4 rounded-xl border bg-card p-5 md:grid-cols-[minmax(0,1fr)_200px]"
                >
                    <div class="grid gap-3">
                        <label class="grid gap-1 text-sm"
                            >Nombre<Input
                                v-model="form.name"
                                required
                                maxlength="80"
                                placeholder="Ej. Gamer 1440p"
                        /></label>
                        <label class="grid gap-1 text-sm"
                            >Frase corta<Input
                                v-model="form.tagline"
                                maxlength="140"
                                placeholder="Ej. El punto ideal: QHD con fluidez."
                        /></label>
                        <label class="grid gap-1 text-sm"
                            >Uso (pestaña en la tienda)
                            <select v-model="form.use" :class="field">
                                <option
                                    v-for="(label, key) in uses"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <div class="grid content-start gap-2 text-sm">
                        Foto (opcional)
                        <label
                            class="relative grid aspect-[4/3] cursor-pointer place-items-center overflow-hidden rounded-lg border-2 border-dashed bg-muted/40 text-center text-xs text-muted-foreground hover:border-primary/60"
                        >
                            <img
                                v-if="preview"
                                :src="preview"
                                alt=""
                                class="absolute inset-0 size-full object-cover"
                            />
                            <span
                                v-else
                                class="grid justify-items-center gap-1 p-2"
                                ><ImagePlus class="size-5" />Sin foto: la tienda
                                muestra las piezas</span
                            >
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                @change="pickImage"
                            />
                        </label>
                        <button
                            v-if="preview"
                            type="button"
                            class="justify-self-start text-xs text-muted-foreground underline"
                            @click="clearImage"
                        >
                            Quitar foto
                        </button>
                    </div>
                </section>

                <section class="grid gap-3 rounded-xl border bg-card p-5">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <h2 class="text-lg font-semibold">Piezas</h2>
                        <form
                            class="flex min-w-64 flex-1 gap-2 sm:max-w-md"
                            @submit.prevent="importLink"
                        >
                            <div class="relative flex-1">
                                <Link2
                                    class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    v-model="link"
                                    placeholder="Pega el enlace de «Compartir build»"
                                    aria-label="Enlace de Arma tu PC"
                                    class="pl-8"
                                />
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="!link"
                                >Importar</Button
                            >
                        </form>
                    </div>
                    <ul class="divide-y rounded-lg border">
                        <li
                            v-for="(label, slot) in slots"
                            :key="slot"
                            class="grid gap-2 p-3"
                        >
                            <div class="flex items-center gap-3">
                                <img
                                    :src="
                                        chosen[slot]?.image ??
                                        '/img/producto-sin-foto.svg'
                                    "
                                    alt=""
                                    class="size-12 shrink-0 rounded-md border bg-muted object-cover"
                                    :class="chosen[slot] ? '' : 'opacity-40'"
                                />
                                <div class="min-w-0 flex-1">
                                    <span
                                        class="text-xs font-semibold text-muted-foreground uppercase"
                                        >{{ label }}</span
                                    >
                                    <template v-if="chosen[slot]">
                                        <p
                                            class="line-clamp-1 text-sm font-medium"
                                        >
                                            {{ chosen[slot]!.name }}
                                        </p>
                                        <p
                                            class="flex flex-wrap items-center gap-x-3 text-xs text-muted-foreground"
                                        >
                                            <b
                                                class="text-foreground tabular-nums"
                                                >{{
                                                    chosen[slot]!.price === null
                                                        ? 'Cotizar'
                                                        : money.format(
                                                              chosen[slot]!
                                                                  .price!,
                                                          )
                                                }}</b
                                            >
                                            <span
                                                class="inline-flex items-center gap-1"
                                                ><i
                                                    class="size-1.5 rounded-full"
                                                    :class="
                                                        dot[chosen[slot]!.stock]
                                                    "
                                                />{{
                                                    chosen[slot]!.stock_label
                                                }}</span
                                            >
                                            <span
                                                v-if="!chosen[slot]!.published"
                                                class="text-amber-600 dark:text-amber-300"
                                                >Oculta en la tienda</span
                                            >
                                        </p>
                                    </template>
                                    <p
                                        v-else
                                        class="text-sm text-muted-foreground"
                                    >
                                        Sin elegir
                                    </p>
                                </div>
                                <Button
                                    size="sm"
                                    :variant="
                                        picking === slot ? 'default' : 'outline'
                                    "
                                    @click="open(slot)"
                                    >{{
                                        chosen[slot] ? 'Cambiar' : 'Elegir'
                                    }}</Button
                                >
                                <button
                                    v-if="chosen[slot]"
                                    type="button"
                                    class="rounded-md p-1.5 text-muted-foreground hover:bg-muted"
                                    :aria-label="`Quitar ${label}`"
                                    @click="chosen[slot] = null"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                            <div
                                v-if="picking === slot"
                                class="grid gap-2 rounded-lg bg-muted/40 p-2"
                            >
                                <div class="relative">
                                    <Search
                                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <Input
                                        v-model="q"
                                        :placeholder="`Buscar ${label.toLowerCase()} por nombre o SKU`"
                                        class="bg-background pl-8"
                                        autofocus
                                    />
                                    <LoaderCircle
                                        v-if="searching"
                                        class="absolute top-1/2 right-2.5 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
                                    />
                                </div>
                                <ul class="grid max-h-96 gap-1 overflow-y-auto">
                                    <li v-for="p in results" :key="p.id">
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-3 rounded-md bg-background p-2 text-left hover:ring-1 hover:ring-primary"
                                            :class="p.issue ? 'opacity-60' : ''"
                                            @click="choose(slot, p)"
                                        >
                                            <img
                                                :src="p.image"
                                                alt=""
                                                class="size-10 shrink-0 rounded border object-cover"
                                                loading="lazy"
                                            />
                                            <span class="min-w-0 flex-1">
                                                <span
                                                    class="line-clamp-1 text-sm"
                                                    >{{ p.name }}</span
                                                >
                                                <span
                                                    class="flex flex-wrap gap-x-3 text-xs text-muted-foreground"
                                                >
                                                    <span>{{ p.sku }}</span>
                                                    <span
                                                        class="inline-flex items-center gap-1"
                                                        ><i
                                                            class="size-1.5 rounded-full"
                                                            :class="
                                                                dot[p.stock]
                                                            "
                                                        />{{
                                                            p.stock_label
                                                        }}</span
                                                    >
                                                    <span
                                                        v-if="!p.published"
                                                        class="text-amber-600 dark:text-amber-300"
                                                        >Oculta</span
                                                    >
                                                    <span
                                                        v-if="p.issue"
                                                        class="text-amber-600 dark:text-amber-300"
                                                        >{{ p.issue }}</span
                                                    >
                                                </span>
                                            </span>
                                            <b
                                                class="text-sm whitespace-nowrap tabular-nums"
                                                >{{
                                                    p.price === null
                                                        ? 'Cotizar'
                                                        : money.format(p.price)
                                                }}</b
                                            >
                                        </button>
                                    </li>
                                    <li
                                        v-if="!searching && !results.length"
                                        class="p-3 text-center text-sm text-muted-foreground"
                                    >
                                        Sin resultados.
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>

            <aside
                class="grid gap-3 rounded-xl border bg-card p-5 lg:sticky lg:top-6"
            >
                <div class="flex items-baseline justify-between">
                    <span class="text-sm text-muted-foreground"
                        >Total con IVA</span
                    >
                    <LoaderCircle
                        v-if="checking"
                        class="size-4 animate-spin text-muted-foreground"
                    />
                </div>
                <b class="text-3xl tabular-nums">{{
                    check?.total == null ? 'Cotizar' : money.format(check.total)
                }}</b>
                <dl class="grid gap-1 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Costo de piezas</dt>
                        <dd class="tabular-nums">
                            {{
                                check?.cost == null
                                    ? '—'
                                    : money.format(check.cost)
                            }}
                        </dd>
                    </div>
                    <div v-if="canMargins" class="flex justify-between">
                        <dt class="text-muted-foreground">Margen</dt>
                        <dd class="tabular-nums">
                            {{
                                check?.margin == null
                                    ? '—'
                                    : `${(check.margin * 100).toFixed(1)}%`
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Consumo estimado</dt>
                        <dd class="tabular-nums">{{ check?.watts ?? 0 }} W</dd>
                    </div>
                </dl>
                <ul
                    v-if="check?.issues.length"
                    class="grid gap-1 rounded-md bg-destructive/10 p-3 text-sm text-destructive"
                >
                    <li
                        v-for="e in check.issues"
                        :key="e.code"
                        class="flex gap-1.5"
                    >
                        <TriangleAlert class="mt-0.5 size-3.5 shrink-0" />{{
                            `${e.level}: ${e.message}`
                        }}
                    </li>
                </ul>
                <p
                    v-if="check?.unverified.length"
                    class="text-sm text-amber-600 dark:text-amber-300"
                >
                    Ficha pendiente: {{ check.unverified.join(', ') }}.
                </p>
                <p
                    v-if="check?.missing.length"
                    class="rounded-md bg-amber-500/10 p-3 text-sm text-amber-700 dark:text-amber-400"
                >
                    Falta: {{ check.missing.join(', ') }}.
                </p>
                <p
                    v-if="check?.hidden.length"
                    class="rounded-md bg-muted p-3 text-xs text-muted-foreground"
                >
                    Sin precio u ocultas ({{ check.hidden.length }}): el equipo
                    se mostrará como «Cotizar».
                </p>
                <p
                    v-if="
                        check &&
                        !check.issues.length &&
                        !check.missing.length &&
                        !check.unverified.length
                    "
                    class="rounded-md bg-emerald-500/10 p-3 text-sm text-emerald-700 dark:text-emerald-400"
                >
                    Todo compatible.
                </p>
                <label class="flex items-center gap-2 text-sm"
                    ><input v-model="form.is_published" type="checkbox" />
                    Publicado en la tienda</label
                >
                <Button :disabled="form.processing" @click="save">{{
                    form.processing
                        ? 'Guardando…'
                        : build
                          ? 'Guardar'
                          : 'Crear equipo'
                }}</Button>
            </aside>
        </div>
    </section>
</template>
