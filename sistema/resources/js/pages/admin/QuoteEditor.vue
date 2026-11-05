<script setup lang="ts">
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { products as searchQuoteProducts } from '@/actions/App/Http/Controllers/Admin/QuoteController';
import SearchHighlight from '@/components/SearchHighlight.vue';
import CustomerPicker from '@/components/CustomerPicker.vue';
import type { PickedCustomer } from '@/lib/customers';

interface Line {
    product_id: number | null;
    sku: string | null;
    description: string;
    brand: string | null;
    model: string | null;
    warranty: string | null;
    quantity: number;
    unit_price: number | string | null;
    unit_cost?: number | string | null;
    stock?: number;
    stock_24h?: number;
    image?: string;
    name?: string;
}

const props = defineProps<{
    quote: any;
    customer: PickedCustomer | null;
    defaults: { valid_until: string };
    statuses: Record<string, string>;
    sources: Record<string, string>;
    links: { public: string; whatsapp: string | null } | null;
    margin: number | null;
}>();

const page = usePage<any>();
const isOwner = computed(() => page.props.auth.user.role === 'owner');

const form = useForm({
    customer_id: props.customer?.id ?? null,
    status: props.quote?.status ?? 'draft',
    valid_until: (props.quote?.valid_until ?? props.defaults.valid_until).slice(
        0,
        10,
    ),
    discount: Number(props.quote?.discount ?? 0),
    build_name: props.quote?.build_name ?? '',
    notes: props.quote?.notes ?? '',
    internal_notes: props.quote?.internal_notes ?? '',
    items: (props.quote?.items ?? []).map((i: any) => ({
        product_id: i.product_id,
        sku: i.sku,
        brand: i.brand ?? '',
        model: i.model ?? '',
        warranty: i.warranty ?? '',
        description: i.description,
        quantity: i.quantity,
        unit_price: i.unit_price,
        unit_cost: i.unit_cost,
    })) as Line[],
});

const money = (n: number) =>
    '$' +
    n.toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
const lineTotal = (l: Line) =>
    l.unit_price === null || l.unit_price === ''
        ? null
        : Number(l.unit_price) * l.quantity;
const total = computed(() =>
    Math.max(
        0,
        form.items.reduce((s, l) => s + (lineTotal(l) ?? 0), 0) -
            Number(form.discount || 0),
    ),
);
const pending = computed(() => form.items.some((l) => lineTotal(l) === null));

/* ---------------- Cliente ---------------- */
const customer = ref<PickedCustomer | null>(props.customer);
watch(customer, (value) => {
    form.customer_id = value?.id ?? null;
});

/* ---------------- Productos ---------------- */
const productQuery = ref('');
const productResults = ref<Line[]>([]);
const searching = ref(false);
const searchError = ref(false);
const searched = ref(false);
const activeProduct = ref(-1);
let productTimer: number | undefined;
let productAbort: AbortController | undefined;

function closeProducts() {
    window.clearTimeout(productTimer);
    productAbort?.abort();
    productResults.value = [];
    searching.value = false;
    searched.value = false;
    activeProduct.value = -1;
}
function searchProducts() {
    closeProducts();
    searchError.value = false;
    const term = productQuery.value.trim();
    if (term.length < 2) return;
    searching.value = true;
    const controller = new AbortController();
    productAbort = controller;
    productTimer = window.setTimeout(async () => {
        try {
            const res = await fetch(
                searchQuoteProducts.url({ query: { q: term } }),
                {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                },
            );
            if (!res.ok) throw new Error('search');
            const results = await res.json();
            if (controller.signal.aborted) return;
            productResults.value = results;
            activeProduct.value = results.length ? 0 : -1;
            searched.value = true;
        } catch {
            if (!controller.signal.aborted) searchError.value = true;
        } finally {
            if (!controller.signal.aborted) searching.value = false;
        }
    }, 220);
}
function productKey(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        closeProducts();
        return;
    }
    if (!productResults.value.length) return;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const direction = event.key === 'ArrowDown' ? 1 : -1;
        activeProduct.value =
            (activeProduct.value + direction + productResults.value.length) %
            productResults.value.length;
        document
            .getElementById(`quote-product-${activeProduct.value}`)
            ?.scrollIntoView({ block: 'nearest' });
    } else if (event.key === 'Enter' && activeProduct.value >= 0) {
        event.preventDefault();
        addProduct(productResults.value[activeProduct.value]);
    }
}
onBeforeUnmount(closeProducts);
function addProduct(p: Line) {
    const existing = form.items.find(
        (l) => l.product_id && l.product_id === p.product_id,
    );
    if (existing) existing.quantity++;
    else form.items.push({ ...p, quantity: 1 });
    productQuery.value = '';
    closeProducts();
}
function addFree() {
    form.items.push({
        product_id: null,
        sku: null,
        brand: '',
        model: '',
        warranty: '',
        description: '',
        quantity: 1,
        unit_price: null,
    });
}
function move(i: number, dir: number) {
    const j = i + dir;
    if (j < 0 || j >= form.items.length) return;
    const [line] = form.items.splice(i, 1);
    form.items.splice(j, 0, line);
}

/* ---------------- Guardar y acciones ---------------- */
function save() {
    form.transform((d) => ({
        ...d,
        items: d.items.map((l) => ({
            ...l,
            unit_price: l.unit_price === '' ? null : l.unit_price,
        })),
    }));
    if (props.quote)
        form.put(`/admin/quotes/${props.quote.id}`, { preserveScroll: true });
    else form.post('/admin/quotes');
}
function setStatus(status: string) {
    router.patch(
        `/admin/quotes/${props.quote.id}/status`,
        { status },
        { preserveScroll: true, onSuccess: () => (form.status = status) },
    );
}
function duplicate() {
    router.post(`/admin/quotes/${props.quote.id}/duplicate`);
}
</script>

<template>
    <Head :title="quote ? quote.folio : 'Nueva cotización'" />
    <section class="space-y-6 p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <Link
                    href="/admin/quotes"
                    class="text-sm text-muted-foreground underline"
                    >← Cotizaciones</Link
                >
                <h1 class="text-3xl font-bold">
                    {{ quote ? quote.folio : 'Nueva cotización' }}
                </h1>
                <p v-if="quote" class="text-sm text-muted-foreground">
                    {{ sources[quote.source] }} ·
                    {{ quote.user?.name ?? 'Sin vendedor' }} · creada
                    {{ new Date(quote.created_at).toLocaleString('es-MX') }}
                </p>
            </div>
            <div v-if="quote" class="flex flex-wrap gap-2">
                <a
                    :href="`/admin/quotes/${quote.id}/pdf`"
                    target="_blank"
                    class="rounded border px-3 py-2"
                    >PDF</a
                >
                <a
                    v-if="links?.whatsapp"
                    :href="links.whatsapp"
                    target="_blank"
                    rel="noopener"
                    class="rounded bg-primary px-3 py-2 font-semibold text-primary-foreground"
                    @click="quote.status === 'draft' && setStatus('sent')"
                    >Enviar por WhatsApp</a
                >
                <a
                    :href="links?.public"
                    target="_blank"
                    class="rounded border px-3 py-2"
                    >Ver como cliente</a
                >
                <Button
                    variant="outline"
                    class="rounded border px-3 py-2"
                    @click="duplicate"
                >
                    Duplicar
                </Button>
                <Link
                    v-if="quote.status === 'accepted'"
                    :href="`/admin/warranties/create?quote=${quote.id}`"
                    class="rounded border px-3 py-2"
                    >Emitir garantía</Link
                >
            </div>
        </div>

        <div
            class="grid min-w-0 grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]"
        >
            <div class="space-y-6">
                <!-- Cliente -->
                <div class="rounded-xl border p-4">
                    <h2 class="mb-3 font-semibold">Cliente</h2>
                    <CustomerPicker
                        v-model="customer"
                        :error="form.errors.customer_id"
                    />
                </div>

                <!-- Partidas -->
                <div class="rounded-xl border">
                    <div class="flex flex-wrap items-center gap-2 border-b p-4">
                        <h2 class="mr-auto font-semibold">Partidas</h2>
                        <div class="relative w-full sm:w-96">
                            <Input
                                aria-label="Agregar producto"
                                role="combobox"
                                aria-autocomplete="list"
                                aria-controls="quote-product-results"
                                :aria-expanded="productResults.length > 0"
                                :aria-activedescendant="
                                    activeProduct >= 0
                                        ? `quote-product-${activeProduct}`
                                        : undefined
                                "
                                @keydown="productKey"
                                @blur="closeProducts"
                                v-model="productQuery"
                                placeholder="Nombre, SKU, modelo o marca"
                                class="w-full rounded-md border px-3 py-2"
                                @input="searchProducts"
                            />
                            <div
                                v-if="searching || searched || searchError"
                                class="absolute right-0 z-20 mt-1 w-full rounded-md border bg-popover text-popover-foreground shadow-lg sm:w-[32rem]"
                            >
                                <p
                                    v-if="searching"
                                    role="status"
                                    class="p-4 text-sm text-muted-foreground"
                                >
                                    Buscando…
                                </p>
                                <p
                                    v-else-if="searchError"
                                    role="alert"
                                    class="p-4 text-sm text-destructive"
                                >
                                    No se pudo buscar. Intenta de nuevo.
                                </p>
                                <p
                                    v-else-if="!productResults.length"
                                    role="status"
                                    class="p-4 text-sm text-muted-foreground"
                                >
                                    Sin resultados. Prueba con la marca, el
                                    modelo o parte del SKU.
                                </p>
                                <ul
                                    v-else
                                    id="quote-product-results"
                                    role="listbox"
                                    aria-label="Productos encontrados"
                                    class="max-h-96 overflow-y-auto"
                                >
                                    <li
                                        v-for="(p, index) in productResults"
                                        :id="`quote-product-${index}`"
                                        :key="p.product_id!"
                                        role="option"
                                        :aria-selected="activeProduct === index"
                                    >
                                        <button
                                            type="button"
                                            tabindex="-1"
                                            class="flex w-full items-center gap-3 px-3 py-3 text-left hover:bg-muted"
                                            :class="{
                                                'bg-muted':
                                                    activeProduct === index,
                                            }"
                                            @mousedown.prevent
                                            @click="addProduct(p)"
                                            @mouseenter="activeProduct = index"
                                        >
                                            <img
                                                :src="p.image"
                                                alt=""
                                                class="h-12 w-12 shrink-0 rounded border bg-muted object-contain"
                                            />
                                            <span class="min-w-0 flex-1">
                                                <SearchHighlight
                                                    :text="
                                                        p.name ?? p.description
                                                    "
                                                    :query="productQuery"
                                                    class="block text-sm font-medium"
                                                />
                                                <span
                                                    class="mt-1 block text-xs text-muted-foreground"
                                                    ><SearchHighlight
                                                        :text="
                                                            [
                                                                p.brand,
                                                                p.sku,
                                                                p.model,
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' · ')
                                                        "
                                                        :query="productQuery"
                                                /></span>
                                                <span
                                                    class="mt-1 block text-xs text-muted-foreground"
                                                    >Local: {{ p.stock }} · En
                                                    24 h:
                                                    {{ p.stock_24h }}</span
                                                >
                                            </span>
                                            <span
                                                class="shrink-0 text-sm font-semibold tabular-nums"
                                                >{{
                                                    p.unit_price === null
                                                        ? 'Cotizar'
                                                        : money(
                                                              Number(
                                                                  p.unit_price,
                                                              ),
                                                          )
                                                }}</span
                                            >
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <Button
                            variant="outline"
                            class="rounded border px-3 py-2"
                            @click="addFree"
                        >
                            Partida libre
                        </Button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="quote-lines w-full text-sm">
                            <thead class="bg-muted text-left">
                                <tr>
                                    <th class="p-2">Descripción</th>
                                    <th class="w-20 p-2">Cant.</th>
                                    <th class="w-32 p-2">Precio c/IVA</th>
                                    <th
                                        v-if="isOwner"
                                        class="w-24 p-2 text-right"
                                    >
                                        Costo
                                    </th>
                                    <th class="w-28 p-2 text-right">Importe</th>
                                    <th class="w-24 p-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(l, i) in form.items"
                                    :key="i"
                                    class="border-t align-top"
                                >
                                    <td class="p-2">
                                        <Input
                                            aria-label="Descripción"
                                            v-model="l.description"
                                            class="w-full rounded border px-2 py-1"
                                            placeholder="Descripción"
                                        />
                                        <div
                                            class="mt-2 grid min-w-72 gap-2 sm:grid-cols-3"
                                        >
                                            <label
                                                v-for="field in [
                                                    'brand',
                                                    'model',
                                                    'warranty',
                                                ] as const"
                                                :key="field"
                                                class="grid gap-1 text-xs text-muted-foreground"
                                            >
                                                {{
                                                    {
                                                        brand: 'Marca',
                                                        model: 'Modelo',
                                                        warranty: 'Garantía',
                                                    }[field]
                                                }}
                                                <Input
                                                    v-model="l[field]"
                                                    :aria-label="`${{ brand: 'Marca', model: 'Modelo', warranty: 'Garantía' }[field]} de la partida ${i + 1}`"
                                                    maxlength="255"
                                                    class="min-w-0"
                                                />
                                                <small
                                                    v-if="
                                                        (form.errors as any)[
                                                            `items.${i}.${field}`
                                                        ]
                                                    "
                                                    class="text-destructive"
                                                    >{{
                                                        (form.errors as any)[
                                                            `items.${i}.${field}`
                                                        ]
                                                    }}</small
                                                >
                                            </label>
                                        </div>
                                        <small
                                            v-if="l.sku"
                                            class="text-muted-foreground"
                                            >{{ l.sku }}</small
                                        >
                                        <p
                                            v-if="
                                                (form.errors as any)[
                                                    `items.${i}.description`
                                                ]
                                            "
                                            class="text-xs text-destructive"
                                        >
                                            {{
                                                (form.errors as any)[
                                                    `items.${i}.description`
                                                ]
                                            }}
                                        </p>
                                    </td>
                                    <td class="p-2">
                                        <Input
                                            aria-label="Cantidad"
                                            v-model.number="l.quantity"
                                            type="number"
                                            min="1"
                                            class="w-full rounded border px-2 py-1"
                                        />
                                    </td>
                                    <td class="p-2">
                                        <Input
                                            aria-label="Precio con IVA"
                                            v-model="l.unit_price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="Por confirmar"
                                            class="w-full rounded border px-2 py-1"
                                        />
                                    </td>
                                    <td
                                        v-if="isOwner"
                                        class="p-2 text-right text-muted-foreground tabular-nums"
                                    >
                                        {{
                                            l.unit_cost
                                                ? money(Number(l.unit_cost))
                                                : '—'
                                        }}
                                    </td>
                                    <td class="p-2 text-right tabular-nums">
                                        {{
                                            lineTotal(l) === null
                                                ? 'Por confirmar'
                                                : money(lineTotal(l)!)
                                        }}
                                    </td>
                                    <td
                                        class="p-2 text-right whitespace-nowrap"
                                    >
                                        <Button
                                            variant="ghost"
                                            class="px-1"
                                            title="Subir"
                                            @click="move(i, -1)"
                                        >
                                            ↑
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            class="px-1"
                                            title="Bajar"
                                            @click="move(i, 1)"
                                        >
                                            ↓
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            class="px-1 text-destructive"
                                            title="Quitar"
                                            @click="form.items.splice(i, 1)"
                                        >
                                            ×
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="!form.items.length">
                                    <td
                                        colspan="6"
                                        class="p-6 text-center text-muted-foreground"
                                    >
                                        Busca un producto o agrega una partida
                                        libre.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p
                        v-if="form.errors.items"
                        class="p-3 text-sm text-destructive"
                    >
                        {{ form.errors.items }}
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-1 text-sm"
                        >Notas para el cliente (salen en el PDF)<Textarea
                            v-model="form.notes"
                            rows="3"
                            class="rounded-md border px-3 py-2"
                        />
                    </label>
                    <label class="grid gap-1 text-sm"
                        >Notas internas<Textarea
                            v-model="form.internal_notes"
                            rows="3"
                            class="rounded-md border px-3 py-2"
                        />
                    </label>
                </div>
            </div>

            <!-- Resumen -->
            <aside
                class="h-fit space-y-4 rounded-xl border p-4 xl:sticky xl:top-4"
            >
                <label class="grid gap-1 text-sm"
                    >Estado
                    <select
                        v-model="form.status"
                        class="rounded-md border px-3 py-2"
                    >
                        <option
                            v-for="(label, key) in statuses"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </option>
                    </select>
                </label>
                <label class="grid gap-1 text-sm"
                    >Vigente hasta<Input
                        v-model="form.valid_until"
                        type="date"
                        class="rounded-md border px-3 py-2"
                /></label>
                <label class="grid gap-1 text-sm"
                    >Nombre de la build (opcional)<Input
                        v-model="form.build_name"
                        class="rounded-md border px-3 py-2"
                /></label>
                <label class="grid gap-1 text-sm"
                    >Descuento con IVA<Input
                        v-model.number="form.discount"
                        type="number"
                        min="0"
                        step="0.01"
                        class="rounded-md border px-3 py-2"
                /></label>
                <dl class="space-y-1 border-t pt-3 text-sm">
                    <div class="flex justify-between">
                        <dt>Subtotal</dt>
                        <dd class="tabular-nums">{{ money(total / 1.16) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>IVA 16 %</dt>
                        <dd class="tabular-nums">
                            {{ money(total - total / 1.16) }}
                        </dd>
                    </div>
                    <div class="flex justify-between text-lg font-bold">
                        <dt>{{ pending ? 'Total parcial' : 'Total' }}</dt>
                        <dd class="tabular-nums">{{ money(total) }}</dd>
                    </div>
                    <p
                        v-if="pending"
                        class="text-xs text-amber-700 dark:text-amber-300"
                    >
                        Hay partidas por confirmar.
                    </p>
                    <div
                        v-if="isOwner && margin !== null"
                        class="flex justify-between text-muted-foreground"
                    >
                        <dt>Margen guardado</dt>
                        <dd>{{ (margin * 100).toFixed(1) }}%</dd>
                    </div>
                </dl>
                <Button
                    class="w-full rounded bg-primary px-4 py-2 font-semibold text-primary-foreground disabled:opacity-50"
                    :disabled="form.processing"
                    @click="save"
                >
                    {{ form.processing ? 'Guardando…' : 'Guardar cotización' }}
                </Button>
                <p
                    v-if="Object.keys(form.errors).length"
                    class="text-sm text-destructive"
                >
                    Revisa los campos marcados.
                </p>
            </aside>
        </div>
    </section>
</template>
