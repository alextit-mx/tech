<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Line {
    product_id: number | null;
    sku: string | null;
    description: string;
    quantity: number;
    unit_price: number | string | null;
    unit_cost?: number | string | null;
    stock?: number;
}
interface Customer {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    rfc: string | null;
}

const props = defineProps<{
    quote: any;
    customer: Customer | null;
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
const customer = ref<Customer | null>(props.customer);
const customerQuery = ref('');
const customerResults = ref<Customer[]>([]);
const newCustomer = ref<{ name: string; phone: string; email: string } | null>(
    null,
);
const customerError = ref('');
let customerTimer: number | undefined;

function searchCustomers() {
    window.clearTimeout(customerTimer);
    customerTimer = window.setTimeout(async () => {
        if (customerQuery.value.trim().length < 2)
            return (customerResults.value = []);
        const res = await fetch(
            `/admin/customers/lookup?q=${encodeURIComponent(customerQuery.value)}`,
            { headers: { Accept: 'application/json' } },
        );
        customerResults.value = await res.json();
    }, 220);
}
function chooseCustomer(c: Customer) {
    customer.value = c;
    form.customer_id = c.id;
    customerResults.value = [];
    customerQuery.value = '';
}
async function createCustomer() {
    if (!newCustomer.value) return;
    customerError.value = '';
    const token = decodeURIComponent(
        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
    );
    const res = await fetch('/admin/customers', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': token,
        },
        body: JSON.stringify(newCustomer.value),
    });
    const data = await res.json();
    if (!res.ok)
        return (customerError.value = Object.values(
            data.errors ?? { e: [data.message] },
        )
            .flat()
            .join(' '));
    chooseCustomer(data);
    newCustomer.value = null;
}

/* ---------------- Productos ---------------- */
const productQuery = ref('');
const productResults = ref<Line[]>([]);
let productTimer: number | undefined;

function searchProducts() {
    window.clearTimeout(productTimer);
    productTimer = window.setTimeout(async () => {
        if (productQuery.value.trim().length < 2)
            return (productResults.value = []);
        const res = await fetch(
            `/admin/quotes/products?q=${encodeURIComponent(productQuery.value)}`,
            { headers: { Accept: 'application/json' } },
        );
        productResults.value = await res.json();
    }, 220);
}
function addProduct(p: Line) {
    const existing = form.items.find(
        (l) => l.product_id && l.product_id === p.product_id,
    );
    if (existing) existing.quantity++;
    else form.items.push({ ...p, quantity: 1 });
    productQuery.value = '';
    productResults.value = [];
}
function addFree() {
    form.items.push({
        product_id: null,
        sku: null,
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
                    class="rounded bg-[#25D366] px-3 py-2 font-semibold text-white"
                    @click="quote.status === 'draft' && setStatus('sent')"
                    >Enviar por WhatsApp</a
                >
                <a
                    :href="links?.public"
                    target="_blank"
                    class="rounded border px-3 py-2"
                    >Ver como cliente</a
                >
                <button class="rounded border px-3 py-2" @click="duplicate">
                    Duplicar
                </button>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="space-y-6">
                <!-- Cliente -->
                <div class="rounded-xl border p-4">
                    <h2 class="mb-3 font-semibold">Cliente</h2>
                    <div
                        v-if="customer"
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div>
                            <p class="font-semibold">{{ customer.name }}</p>
                            <p class="text-sm text-muted-foreground">
                                {{ customer.phone ?? 'Sin teléfono' }}
                                {{
                                    customer.email ? `· ${customer.email}` : ''
                                }}
                                {{
                                    customer.rfc ? `· RFC ${customer.rfc}` : ''
                                }}
                            </p>
                        </div>
                        <button
                            class="rounded border px-3 py-1 text-sm"
                            @click="
                                customer = null;
                                form.customer_id = null;
                            "
                        >
                            Cambiar
                        </button>
                    </div>
                    <div v-else class="space-y-2">
                        <div class="relative">
                            <input
                                v-model="customerQuery"
                                placeholder="Buscar por nombre o WhatsApp"
                                class="w-full rounded-md border px-3 py-2"
                                @input="searchCustomers"
                            />
                            <ul
                                v-if="customerResults.length"
                                class="absolute z-10 mt-1 w-full rounded-md border bg-background shadow"
                            >
                                <li v-for="c in customerResults" :key="c.id">
                                    <button
                                        class="w-full px-3 py-2 text-left hover:bg-muted"
                                        @click="chooseCustomer(c)"
                                    >
                                        {{ c.name }}
                                        <span class="text-muted-foreground">{{
                                            c.phone
                                        }}</span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <button
                            v-if="!newCustomer"
                            class="text-sm underline"
                            @click="
                                newCustomer = {
                                    name: customerQuery,
                                    phone: '',
                                    email: '',
                                }
                            "
                        >
                            + Cliente nuevo
                        </button>
                        <div
                            v-else
                            class="grid gap-2 rounded-md bg-muted/50 p-3 sm:grid-cols-3"
                        >
                            <input
                                v-model="newCustomer.name"
                                placeholder="Nombre"
                                class="rounded-md border px-3 py-2"
                            />
                            <input
                                v-model="newCustomer.phone"
                                placeholder="WhatsApp (10 dígitos)"
                                class="rounded-md border px-3 py-2"
                            />
                            <input
                                v-model="newCustomer.email"
                                placeholder="Correo (opcional)"
                                class="rounded-md border px-3 py-2"
                            />
                            <p
                                v-if="customerError"
                                class="text-sm text-red-600 sm:col-span-3"
                            >
                                {{ customerError }}
                            </p>
                            <div class="flex gap-2 sm:col-span-3">
                                <button
                                    class="rounded bg-[#E30613] px-3 py-1.5 text-white"
                                    @click="createCustomer"
                                >
                                    Guardar cliente
                                </button>
                                <button
                                    class="rounded border px-3 py-1.5"
                                    @click="newCustomer = null"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </div>
                        <p
                            v-if="form.errors.customer_id"
                            class="text-sm text-red-600"
                        >
                            {{ form.errors.customer_id }}
                        </p>
                    </div>
                </div>

                <!-- Partidas -->
                <div class="rounded-xl border">
                    <div class="flex flex-wrap items-center gap-2 border-b p-4">
                        <h2 class="mr-auto font-semibold">Partidas</h2>
                        <div class="relative w-full sm:w-96">
                            <input
                                v-model="productQuery"
                                placeholder="Agregar producto (nombre o SKU)"
                                class="w-full rounded-md border px-3 py-2"
                                @input="searchProducts"
                            />
                            <ul
                                v-if="productResults.length"
                                class="absolute right-0 z-10 mt-1 max-h-80 w-full overflow-y-auto rounded-md border bg-background shadow"
                            >
                                <li
                                    v-for="p in productResults"
                                    :key="p.product_id!"
                                >
                                    <button
                                        class="flex w-full justify-between gap-3 px-3 py-2 text-left hover:bg-muted"
                                        @click="addProduct(p)"
                                    >
                                        <span
                                            >{{ p.description }}
                                            <small class="text-muted-foreground"
                                                >{{ p.sku }} ·
                                                {{ p.stock }} pzas</small
                                            ></span
                                        >
                                        <span
                                            class="whitespace-nowrap tabular-nums"
                                            >{{
                                                p.unit_price === null
                                                    ? 'Cotizar'
                                                    : money(
                                                          Number(p.unit_price),
                                                      )
                                            }}</span
                                        >
                                    </button>
                                </li>
                            </ul>
                        </div>
                        <button
                            class="rounded border px-3 py-2"
                            @click="addFree"
                        >
                            Partida libre
                        </button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
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
                                        <input
                                            v-model="l.description"
                                            class="w-full rounded border px-2 py-1"
                                            placeholder="Descripción"
                                        />
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
                                            class="text-xs text-red-600"
                                        >
                                            {{
                                                (form.errors as any)[
                                                    `items.${i}.description`
                                                ]
                                            }}
                                        </p>
                                    </td>
                                    <td class="p-2">
                                        <input
                                            v-model.number="l.quantity"
                                            type="number"
                                            min="1"
                                            class="w-full rounded border px-2 py-1"
                                        />
                                    </td>
                                    <td class="p-2">
                                        <input
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
                                        <button
                                            class="px-1"
                                            title="Subir"
                                            @click="move(i, -1)"
                                        >
                                            ↑
                                        </button>
                                        <button
                                            class="px-1"
                                            title="Bajar"
                                            @click="move(i, 1)"
                                        >
                                            ↓
                                        </button>
                                        <button
                                            class="px-1 text-red-600"
                                            title="Quitar"
                                            @click="form.items.splice(i, 1)"
                                        >
                                            ✕
                                        </button>
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
                        class="p-3 text-sm text-red-600"
                    >
                        {{ form.errors.items }}
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-1 text-sm"
                        >Notas para el cliente (salen en el PDF)<textarea
                            v-model="form.notes"
                            rows="3"
                            class="rounded-md border px-3 py-2"
                        />
                    </label>
                    <label class="grid gap-1 text-sm"
                        >Notas internas<textarea
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
                    >Vigente hasta<input
                        v-model="form.valid_until"
                        type="date"
                        class="rounded-md border px-3 py-2"
                /></label>
                <label class="grid gap-1 text-sm"
                    >Nombre de la build (opcional)<input
                        v-model="form.build_name"
                        class="rounded-md border px-3 py-2"
                /></label>
                <label class="grid gap-1 text-sm"
                    >Descuento con IVA<input
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
                    <p v-if="pending" class="text-xs text-amber-700">
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
                <button
                    class="w-full rounded bg-[#E30613] px-4 py-2 font-semibold text-white disabled:opacity-50"
                    :disabled="form.processing"
                    @click="save"
                >
                    {{ form.processing ? 'Guardando…' : 'Guardar cotización' }}
                </button>
                <p
                    v-if="Object.keys(form.errors).length"
                    class="text-sm text-red-600"
                >
                    Revisa los campos marcados.
                </p>
            </aside>
        </div>
    </section>
</template>
