<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    Download,
    ExternalLink,
    Eye,
    EyeOff,
    Pencil,
    Plus,
    Search,
    Star,
    X,
} from '@lucide/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { toast } from 'vue-sonner';
import ImageUploader from '@/components/ImageUploader.vue';
import type { ProductImg } from '@/components/ImageUploader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { firstError, http } from '@/lib/http';

type Row = {
    id: number;
    sku: string;
    name: string;
    slug: string;
    category_id: number;
    brand_id: number | null;
    short_description: string | null;
    cost: string | number | null;
    cost_currency: 'MXN' | 'USD';
    price: string | number | null;
    price_mode: 'show' | 'quote';
    stock: number;
    stock_24h: number;
    is_published: boolean;
    is_featured: boolean;
    warranty_months: number | null;
    category: { id: number; name: string } | null;
    brand: { id: number; name: string } | null;
    images: ProductImg[];
    price_below_cost: boolean;
    margin?: number | null;
};
type Filters = {
    search: string;
    category_id: string | number;
    brand_id: string | number;
    chip: string;
    sort: string;
    dir: string;
    per_page: number;
};

const props = defineProps<{
    products: {
        data: Row[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    counts: Record<string, number>;
    filters: Filters;
    categories: { id: number; name: string }[];
    brands: { id: number; name: string }[];
    canBulk: boolean;
    canMargins: boolean;
}>();

const chips = [
    { key: '', label: 'Todos', count: 'all' },
    { key: 'pending_specs', label: 'Ficha pendiente' },
    { key: 'no_photo', label: 'Sin foto' },
    { key: 'no_price', label: 'Sin precio' },
    { key: 'no_stock', label: 'Sin existencias' },
    { key: 'hidden', label: 'Ocultos en web' },
    { key: 'remate', label: 'Remates / caja abierta' },
    { key: 'featured', label: 'Destacados' },
];

const f = reactive<Filters>({ ...props.filters });
const loading = ref(false);
const selected = ref<number[]>([]);
const bulkCategory = ref('');
const pct = ref<number | null>(null);
const searchEl = ref<{ $el: HTMLInputElement } | null>(null);
const money = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 2,
});

function load(extra: Partial<Filters> & { page?: number } = {}) {
    Object.assign(f, extra);
    const query = Object.fromEntries(
        Object.entries({ ...f, page: extra.page }).filter(
            ([, v]) => v !== '' && v !== null && v !== undefined,
        ),
    );
    router.get('/admin/products', query, {
        preserveState: true,
        preserveScroll: !extra.page,
        replace: true,
        only: ['products', 'counts', 'filters'],
    });
}
const searchLater = useDebounceFn(() => load(), 300);
// El Excel lleva los mismos filtros, chip y orden que la tabla, pero todas las páginas.
const exportUrl = computed(() => {
    const q = new URLSearchParams(
        Object.entries(f)
            .filter(([k, v]) => k !== 'per_page' && v !== '' && v != null)
            .map(([k, v]) => [k, String(v)]),
    ).toString();

    return `/admin/products/export${q ? `?${q}` : ''}`;
});
watch(() => f.search, searchLater);
watch(
    () => props.products.data,
    () =>
        (selected.value = selected.value.filter((id) =>
            props.products.data.some((p) => p.id === id),
        )),
);

function sortBy(col: string) {
    load({
        sort: col,
        dir: f.sort === col && f.dir === 'desc' ? 'asc' : 'desc',
    });
}

const allChecked = computed({
    get: () =>
        props.products.data.length > 0 &&
        props.products.data.every((p) => selected.value.includes(p.id)),
    set: (on: boolean) =>
        (selected.value = on ? props.products.data.map((p) => p.id) : []),
});

const thumb = (p: Row) =>
    p.images[0] ? `/uploads/${p.images[0].path}` : '/img/producto-sin-foto.svg';
const priceLabel = (p: Row) =>
    p.price_mode === 'quote' || !Number(p.price)
        ? 'Cotizar'
        : money.format(Number(p.price));

/** Guarda un campo sin recargar: el renglón se actualiza con lo que regresa el servidor. */
const inflight = new Map<string, unknown>();
async function patch(
    p: Row,
    data: Partial<Row>,
    quiet = false,
): Promise<boolean> {
    const res = await http<{ product: Row }>(
        'PATCH',
        `/admin/products/${p.id}/quick`,
        data,
    );
    if (!res.ok) {
        toast.error(
            firstError(res.data, 'No se pudieron guardar los cambios.'),
        );
        return false;
    }
    Object.assign(p, res.data.product);
    if (!quiet) toast.success(`Guardado: ${p.name.slice(0, 48)}`);
    router.reload({ only: ['counts'], async: true });
    return true;
}

async function commit(
    p: Row,
    field: 'price' | 'stock' | 'stock_24h',
    input: HTMLInputElement,
) {
    const raw = input.value.trim();
    const value =
        field === 'price'
            ? raw === ''
                ? null
                : Number(raw)
            : Math.max(0, Math.trunc(Number(raw) || 0));
    const current =
        field === 'price'
            ? p.price === null
                ? null
                : Number(p.price)
            : p[field];
    const key = `${p.id}:${field}`;
    if (
        value === current ||
        inflight.get(key) === value ||
        (value !== null && Number.isNaN(value))
    )
        return;
    inflight.set(key, value);
    if (!(await patch(p, { [field]: value })))
        input.value = String(p[field] ?? '');
    inflight.delete(key);
}

function cancel(
    p: Row,
    field: 'price' | 'stock' | 'stock_24h',
    input: HTMLInputElement,
) {
    input.value = String(p[field] ?? '');
    input.blur();
}

async function toggle(p: Row, field: 'is_published' | 'is_featured') {
    await patch(p, { [field]: !p[field] });
}

/* Panel lateral de edición rápida. */
const sheetOpen = ref(false);
const active = ref<Row | null>(null);
const qf = reactive({
    name: '',
    category_id: 0,
    price: null as number | null,
    price_mode: 'show',
    cost: null as number | null,
    cost_currency: 'MXN',
    stock: 0,
    stock_24h: 0,
    is_published: false,
    is_featured: false,
});
const qfSaving = ref(false);
function openSheet(p: Row) {
    active.value = p;
    Object.assign(qf, {
        name: p.name,
        category_id: p.category_id,
        price: p.price === null ? null : Number(p.price),
        price_mode: p.price_mode,
        cost: p.cost === null ? null : Number(p.cost),
        cost_currency: p.cost_currency,
        stock: p.stock,
        stock_24h: p.stock_24h,
        is_published: p.is_published,
        is_featured: p.is_featured,
    });
    sheetOpen.value = true;
}
async function saveSheet() {
    if (!active.value) return;
    qfSaving.value = true;
    if (await patch(active.value, { ...qf } as Partial<Row>))
        sheetOpen.value = false;
    qfSaving.value = false;
}
function photosChanged(images: ProductImg[]) {
    if (active.value) active.value.images = images;
    router.reload({ only: ['counts'], async: true });
}

/* Acciones masivas. */
function bulk(action: string) {
    if (action === 'category' && !bulkCategory.value) {
        toast.error('Elige la categoría.');
        return;
    }
    router.post(
        '/admin/products/bulk-update',
        {
            product_ids: selected.value,
            action,
            category_id: bulkCategory.value || null,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                toast.success(
                    `${selected.value.length} producto(s) actualizados.`,
                );
                selected.value = [];
            },
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'No se pudo aplicar.'),
        },
    );
}
function bulkPrice() {
    if (!pct.value) {
        toast.error('Escribe el porcentaje (negativo para bajar).');
        return;
    }
    if (
        !confirm(
            `¿Ajustar ${pct.value > 0 ? '+' : ''}${pct.value}% el precio de ${selected.value.length} producto(s)?`,
        )
    )
        return;
    router.post(
        '/admin/products/bulk-pricing',
        { product_ids: selected.value, percent: pct.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => toast.success('Precios ajustados.'),
            onError: (e) =>
                toast.error(Object.values(e)[0] ?? 'No se pudo ajustar.'),
        },
    );
}

/* La tecla / enfoca el buscador; indicador de carga en las visitas. */
function onKey(e: KeyboardEvent) {
    const tag = (e.target as HTMLElement).tagName;
    if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) {
        e.preventDefault();
        searchEl.value?.$el.focus();
    }
}
const offs: Array<() => void> = [];
onMounted(() => {
    window.addEventListener('keydown', onKey);
    offs.push(
        router.on('start', (e) => {
            if (
                !e.detail.visit.only.includes('counts') ||
                e.detail.visit.only.length > 1
            )
                loading.value = true;
        }),
    );
    offs.push(router.on('finish', () => (loading.value = false)));
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    offs.forEach((off) => off());
});

const pages = computed(() => {
    const { current_page: c, last_page: l } = props.products;
    const set = new Set(
        [1, l, c - 1, c, c + 1].filter((n) => n >= 1 && n <= l),
    );
    return [...set].sort((a, b) => a - b);
});
const field = 'h-9 rounded-md border bg-background px-2 text-sm';
</script>

<template>
    <Head title="Productos" />
    <section class="grid gap-4 p-4 pb-28 md:p-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight md:text-3xl">
                    Productos
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ counts.all }} en catálogo · {{ counts.no_photo }} sin
                    foto · {{ counts.no_price }} sin precio
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <a
                        :href="exportUrl"
                        download
                        title="Descarga lo que muestra el listado, con los filtros que tengas puestos"
                        ><Download class="size-4" /> Descargar Excel</a
                    >
                </Button>
                <Button as-child>
                    <Link href="/admin/products/create"
                        ><Plus class="size-4" /> Nuevo producto</Link
                    >
                </Button>
            </div>
        </header>

        <div class="grid gap-3 rounded-xl border bg-card p-3">
            <div class="flex flex-wrap gap-2">
                <div class="relative min-w-56 flex-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        ref="searchEl"
                        v-model="f.search"
                        placeholder="Buscar por nombre, SKU o marca  ( / )"
                        aria-label="Buscar productos"
                        class="pl-8"
                    />
                </div>
                <select
                    v-model="f.category_id"
                    :class="field"
                    aria-label="Categoría"
                    @change="load()"
                >
                    <option value="">Todas las categorías</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">
                        {{ c.name }}
                    </option>
                </select>
                <select
                    v-model="f.brand_id"
                    :class="field"
                    aria-label="Marca"
                    @change="load()"
                >
                    <option value="">Todas las marcas</option>
                    <option v-for="b in brands" :key="b.id" :value="b.id">
                        {{ b.name }}
                    </option>
                </select>
                <select
                    v-model.number="f.per_page"
                    :class="field"
                    aria-label="Por página"
                    @change="load()"
                >
                    <option :value="25">25 por página</option>
                    <option :value="50">50 por página</option>
                    <option :value="100">100 por página</option>
                </select>
            </div>
            <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-0.5">
                <button
                    v-for="c in chips"
                    :key="c.key"
                    type="button"
                    class="shrink-0 rounded-full border px-3 py-1 text-xs font-medium whitespace-nowrap transition"
                    :class="
                        f.chip === c.key
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'hover:bg-muted'
                    "
                    @click="load({ chip: c.key })"
                >
                    {{ c.label }}
                    <span class="ml-1 tabular-nums opacity-70">{{
                        counts[c.count ?? c.key] ?? 0
                    }}</span>
                </button>
            </div>
        </div>

        <div class="relative">
            <div
                v-if="loading"
                class="absolute inset-x-0 top-0 z-10 h-0.5 animate-pulse bg-primary"
            />

            <!-- Escritorio: tabla -->
            <div
                class="hidden overflow-x-auto rounded-xl border md:block"
                :class="loading ? 'opacity-60' : ''"
            >
                <table class="w-full text-left text-sm">
                    <thead
                        class="bg-muted/60 text-xs text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="w-10 p-3">
                                <input
                                    v-model="allChecked"
                                    type="checkbox"
                                    aria-label="Seleccionar todos"
                                />
                            </th>
                            <th class="p-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="sortBy('name')"
                                >
                                    Producto
                                    <ArrowUp
                                        v-if="
                                            f.sort === 'name' && f.dir === 'asc'
                                        "
                                        class="size-3"
                                    /><ArrowDown
                                        v-else-if="f.sort === 'name'"
                                        class="size-3"
                                    />
                                </button>
                            </th>
                            <th class="p-3">Categoría</th>
                            <th class="p-3 text-right">Costo</th>
                            <th class="p-3">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="sortBy('price')"
                                >
                                    Precio con IVA
                                    <ArrowUp
                                        v-if="
                                            f.sort === 'price' &&
                                            f.dir === 'asc'
                                        "
                                        class="size-3"
                                    /><ArrowDown
                                        v-else-if="f.sort === 'price'"
                                        class="size-3"
                                    />
                                </button>
                            </th>
                            <th v-if="canMargins" class="p-3 text-right">
                                Margen
                            </th>
                            <th
                                class="p-3"
                                title="En el local: entrega inmediata"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="sortBy('stock')"
                                >
                                    Inmediata
                                    <ArrowUp
                                        v-if="
                                            f.sort === 'stock' &&
                                            f.dir === 'asc'
                                        "
                                        class="size-3"
                                    /><ArrowDown
                                        v-else-if="f.sort === 'stock'"
                                        class="size-3"
                                    />
                                </button>
                            </th>
                            <th class="p-3" title="Llega al día siguiente">
                                24 h
                            </th>
                            <th class="p-3">Web</th>
                            <th class="w-10 p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in products.data"
                            :key="p.id"
                            class="border-t align-middle transition hover:bg-muted/40"
                            :class="
                                selected.includes(p.id) ? 'bg-primary/5' : ''
                            "
                        >
                            <td class="p-3">
                                <input
                                    v-model="selected"
                                    type="checkbox"
                                    :value="p.id"
                                    :aria-label="`Seleccionar ${p.name}`"
                                />
                            </td>
                            <td class="p-2">
                                <button
                                    type="button"
                                    class="flex items-center gap-3 text-left"
                                    @click="openSheet(p)"
                                >
                                    <img
                                        :src="thumb(p)"
                                        alt=""
                                        class="size-10 shrink-0 rounded-md border bg-muted object-cover"
                                        loading="lazy"
                                    />
                                    <span class="min-w-0">
                                        <span
                                            class="line-clamp-2 font-medium hover:underline"
                                            >{{ p.name }}</span
                                        >
                                        <span
                                            class="block text-xs text-muted-foreground"
                                            >{{ p.sku
                                            }}<template v-if="p.brand">
                                                · {{ p.brand.name }}</template
                                            ><template
                                                v-if="p.short_description"
                                            >
                                                ·
                                                <b
                                                    class="text-amber-600 dark:text-amber-300"
                                                    >{{
                                                        p.short_description
                                                    }}</b
                                                ></template
                                            ></span
                                        >
                                    </span>
                                </button>
                            </td>
                            <td class="p-3 text-muted-foreground">
                                {{ p.category?.name }}
                            </td>
                            <td
                                class="p-3 text-right whitespace-nowrap tabular-nums"
                            >
                                {{
                                    p.cost === null
                                        ? '—'
                                        : `$${p.cost} ${p.cost_currency}`
                                }}
                            </td>
                            <td class="p-2">
                                <input
                                    :value="p.price ?? ''"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Cotizar"
                                    :aria-label="`Precio con IVA de ${p.name}`"
                                    class="h-8 w-28 rounded-md border bg-background px-2 text-right tabular-nums"
                                    :class="
                                        p.price_below_cost
                                            ? 'border-amber-500'
                                            : ''
                                    "
                                    :title="
                                        p.price_below_cost
                                            ? 'El precio sin IVA queda por debajo del costo'
                                            : ''
                                    "
                                    @keydown.enter="
                                        commit(
                                            p,
                                            'price',
                                            $event.target as HTMLInputElement,
                                        )
                                    "
                                    @keydown.esc="
                                        cancel(
                                            p,
                                            'price',
                                            $event.target as HTMLInputElement,
                                        )
                                    "
                                    @blur="
                                        commit(
                                            p,
                                            'price',
                                            $event.target as HTMLInputElement,
                                        )
                                    "
                                />
                            </td>
                            <td
                                v-if="canMargins"
                                class="p-3 text-right tabular-nums"
                            >
                                {{
                                    p.margin == null
                                        ? '—'
                                        : `${(p.margin * 100).toFixed(1)}%`
                                }}
                            </td>
                            <td
                                v-for="col in ['stock', 'stock_24h'] as const"
                                :key="col"
                                class="p-2"
                            >
                                <input
                                    :value="p[col]"
                                    type="number"
                                    min="0"
                                    :aria-label="`${col === 'stock' ? 'Entrega inmediata' : 'Llega en 24 h'}: ${p.name}`"
                                    class="h-8 w-16 rounded-md border bg-background px-2 text-right tabular-nums"
                                    @keydown.enter="
                                        commit(
                                            p,
                                            col,
                                            $event.target as HTMLInputElement,
                                        )
                                    "
                                    @keydown.esc="
                                        cancel(
                                            p,
                                            col,
                                            $event.target as HTMLInputElement,
                                        )
                                    "
                                    @blur="
                                        commit(
                                            p,
                                            col,
                                            $event.target as HTMLInputElement,
                                        )
                                    "
                                />
                            </td>
                            <td class="p-2">
                                <span class="flex gap-1">
                                    <button
                                        type="button"
                                        class="rounded-md p-1.5 hover:bg-muted"
                                        :class="
                                            p.is_published
                                                ? 'text-emerald-600 dark:text-emerald-300'
                                                : 'text-muted-foreground'
                                        "
                                        :title="
                                            p.is_published
                                                ? 'Publicado (clic para ocultar)'
                                                : 'Oculto (clic para publicar)'
                                        "
                                        @click="toggle(p, 'is_published')"
                                    >
                                        <Eye
                                            v-if="p.is_published"
                                            class="size-4"
                                        /><EyeOff v-else class="size-4" />
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-md p-1.5 hover:bg-muted"
                                        :class="
                                            p.is_featured
                                                ? 'text-amber-500'
                                                : 'text-muted-foreground'
                                        "
                                        :title="
                                            p.is_featured
                                                ? 'Destacado'
                                                : 'Destacar'
                                        "
                                        @click="toggle(p, 'is_featured')"
                                    >
                                        <Star
                                            class="size-4"
                                            :fill="
                                                p.is_featured
                                                    ? 'currentColor'
                                                    : 'none'
                                            "
                                        />
                                    </button>
                                </span>
                            </td>
                            <td class="p-2">
                                <button
                                    type="button"
                                    class="rounded-md p-1.5 hover:bg-muted"
                                    aria-label="Edición rápida"
                                    @click="openSheet(p)"
                                >
                                    <Pencil class="size-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Celular: tarjetas -->
            <ul
                class="grid gap-2 md:hidden"
                :class="loading ? 'opacity-60' : ''"
            >
                <li
                    v-for="p in products.data"
                    :key="p.id"
                    class="flex gap-3 rounded-xl border bg-card p-3"
                >
                    <input
                        v-model="selected"
                        type="checkbox"
                        :value="p.id"
                        class="mt-1"
                        :aria-label="`Seleccionar ${p.name}`"
                    />
                    <button
                        type="button"
                        class="flex min-w-0 flex-1 gap-3 text-left"
                        @click="openSheet(p)"
                    >
                        <img
                            :src="thumb(p)"
                            alt=""
                            class="size-16 shrink-0 rounded-lg border bg-muted object-cover"
                            loading="lazy"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="line-clamp-2 text-sm font-medium">{{
                                p.name
                            }}</span>
                            <span class="block text-xs text-muted-foreground"
                                >{{ p.sku }} · {{ p.category?.name }}</span
                            >
                            <span
                                class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs"
                            >
                                <b class="text-sm">{{ priceLabel(p) }}</b>
                                <span
                                    >Inmediata {{ p.stock }} · 24 h
                                    {{ p.stock_24h }}</span
                                >
                                <span
                                    :class="
                                        p.is_published
                                            ? 'text-emerald-600 dark:text-emerald-300'
                                            : 'text-muted-foreground'
                                    "
                                    >{{
                                        p.is_published ? 'Publicado' : 'Oculto'
                                    }}</span
                                >
                            </span>
                        </span>
                    </button>
                </li>
            </ul>

            <div
                v-if="!products.data.length && !loading"
                class="grid place-items-center gap-2 rounded-xl border border-dashed p-10 text-center"
            >
                <p class="font-medium">No hay productos con estos filtros.</p>
                <Button
                    variant="outline"
                    size="sm"
                    @click="
                        load({
                            search: '',
                            category_id: '',
                            brand_id: '',
                            chip: '',
                        })
                    "
                    >Quitar filtros</Button
                >
            </div>
            <div v-if="loading && !products.data.length" class="grid gap-2">
                <Skeleton v-for="n in 6" :key="n" class="h-14 w-full" />
            </div>
        </div>

        <nav
            v-if="products.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-2 text-sm"
            aria-label="Páginas"
        >
            <span class="text-muted-foreground"
                >{{ products.from }}–{{ products.to }} de
                {{ products.total }}</span
            >
            <span class="flex gap-1">
                <template v-for="(n, i) in pages" :key="n">
                    <span
                        v-if="i > 0 && n - pages[i - 1] > 1"
                        class="px-1 text-muted-foreground"
                        >…</span
                    >
                    <button
                        type="button"
                        class="min-w-9 rounded-md border px-2 py-1.5 tabular-nums"
                        :class="
                            n === products.current_page
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'hover:bg-muted'
                        "
                        @click="load({ page: n })"
                    >
                        {{ n }}
                    </button>
                </template>
            </span>
        </nav>

        <!-- Barra de acciones de la selección -->
        <Transition
            enter-from-class="translate-y-full opacity-0"
            leave-to-class="translate-y-full opacity-0"
            enter-active-class="transition"
            leave-active-class="transition"
        >
            <div
                v-if="selected.length"
                class="fixed inset-x-2 bottom-2 z-40 mx-auto flex max-w-5xl flex-wrap items-center gap-2 rounded-xl border bg-popover p-2.5 shadow-2xl md:inset-x-6"
            >
                <b class="px-1 text-sm"
                    >{{ selected.length }} seleccionado(s)</b
                >
                <Button size="sm" variant="outline" @click="bulk('publish')"
                    ><Eye class="size-4" /> Publicar</Button
                >
                <Button size="sm" variant="outline" @click="bulk('hide')"
                    ><EyeOff class="size-4" /> Ocultar</Button
                >
                <Button size="sm" variant="outline" @click="bulk('feature')"
                    ><Star class="size-4" /> Destacar</Button
                >
                <Button size="sm" variant="outline" @click="bulk('unfeature')"
                    >Quitar destacado</Button
                >
                <span class="flex gap-1">
                    <select
                        v-model="bulkCategory"
                        :class="field"
                        class="h-8 max-w-44"
                        aria-label="Mover a categoría"
                    >
                        <option value="">Mover a categoría…</option>
                        <option
                            v-for="c in categories"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.name }}
                        </option>
                    </select>
                    <Button
                        size="sm"
                        variant="outline"
                        @click="bulk('category')"
                        >Mover</Button
                    >
                </span>
                <span v-if="canBulk" class="flex gap-1">
                    <input
                        v-model.number="pct"
                        type="number"
                        placeholder="± %"
                        aria-label="Porcentaje de ajuste"
                        :class="field"
                        class="h-8 w-20"
                    />
                    <Button size="sm" variant="outline" @click="bulkPrice"
                        >Ajustar precio</Button
                    >
                </span>
                <button
                    type="button"
                    class="ml-auto rounded-md p-1.5 hover:bg-muted"
                    aria-label="Quitar selección"
                    @click="selected = []"
                >
                    <X class="size-4" />
                </button>
            </div>
        </Transition>

        <!-- Edición rápida -->
        <Sheet v-model:open="sheetOpen">
            <SheetContent
                side="right"
                class="w-full gap-0 overflow-y-auto sm:max-w-lg"
            >
                <template v-if="active">
                    <SheetHeader class="border-b">
                        <SheetTitle class="pr-6 leading-snug">{{
                            active.name
                        }}</SheetTitle>
                        <SheetDescription
                            >{{ active.sku
                            }}<template v-if="active.brand">
                                · {{ active.brand.name }}</template
                            ></SheetDescription
                        >
                    </SheetHeader>
                    <form class="grid gap-5 p-4" @submit.prevent="saveSheet">
                        <section class="grid gap-2">
                            <h3 class="text-sm font-semibold">Fotos</h3>
                            <ImageUploader
                                :product-id="active.id"
                                :images="active.images"
                                @change="photosChanged"
                            />
                        </section>
                        <section class="grid gap-3">
                            <label class="grid gap-1 text-sm"
                                >Nombre<Input v-model="qf.name" required
                            /></label>
                            <label class="grid gap-1 text-sm"
                                >Categoría
                                <select
                                    v-model.number="qf.category_id"
                                    :class="field"
                                >
                                    <option
                                        v-for="c in categories"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ c.name }}
                                    </option>
                                </select>
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="grid gap-1 text-sm"
                                    >Precio con IVA<Input
                                        v-model.number="qf.price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="Cotizar"
                                /></label>
                                <label class="grid gap-1 text-sm"
                                    >En la tienda
                                    <select
                                        v-model="qf.price_mode"
                                        :class="field"
                                    >
                                        <option value="show">
                                            Mostrar precio
                                        </option>
                                        <option value="quote">Cotizar</option>
                                    </select>
                                </label>
                                <label class="grid gap-1 text-sm"
                                    >Costo<Input
                                        v-model.number="qf.cost"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                /></label>
                                <label class="grid gap-1 text-sm"
                                    >Moneda
                                    <select
                                        v-model="qf.cost_currency"
                                        :class="field"
                                    >
                                        <option>MXN</option>
                                        <option>USD</option>
                                    </select>
                                </label>
                                <label class="grid gap-1 text-sm"
                                    >Entrega inmediata<Input
                                        v-model.number="qf.stock"
                                        type="number"
                                        min="0"
                                /></label>
                                <label class="grid gap-1 text-sm"
                                    >Llega en 24 h<Input
                                        v-model.number="qf.stock_24h"
                                        type="number"
                                        min="0"
                                /></label>
                            </div>
                            <label class="flex items-center gap-2 text-sm"
                                ><input
                                    v-model="qf.is_published"
                                    type="checkbox"
                                />
                                Publicado en la tienda</label
                            >
                            <label class="flex items-center gap-2 text-sm"
                                ><input
                                    v-model="qf.is_featured"
                                    type="checkbox"
                                />
                                Destacado</label
                            >
                        </section>
                        <div
                            class="sticky bottom-0 -mx-4 flex flex-wrap items-center gap-2 border-t bg-background p-4"
                        >
                            <Button type="submit" :disabled="qfSaving">{{
                                qfSaving ? 'Guardando…' : 'Guardar'
                            }}</Button>
                            <Button variant="outline" as-child
                                ><Link
                                    :href="`/admin/products/${active.id}/edit`"
                                    >Abrir completo</Link
                                ></Button
                            >
                            <a
                                v-if="active.is_published"
                                :href="`/producto/${active.slug}`"
                                target="_blank"
                                rel="noopener"
                                class="ml-auto inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                                >Ver en tienda <ExternalLink class="size-3.5"
                            /></a>
                        </div>
                    </form>
                </template>
            </SheetContent>
        </Sheet>
    </section>
</template>
