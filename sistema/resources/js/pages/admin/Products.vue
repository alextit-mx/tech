<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps<{
    products: any;
    categories: any[];
    brands: any[];
    canBulk: boolean;
}>();
const selected = ref<number[]>([]);
const pct = ref(0);
const search = ref('');
const categoryFilter = ref('');
const brandFilter = ref('');
const priceMode = ref('');
const published = ref('');
const outOfStock = ref(false);
const bulkCategory = ref('');
const status = ref('');
const busy = ref<number | null>(null);
function applyFilters() {
    router.get(
        '/admin/products',
        {
            search: search.value,
            category_id: categoryFilter.value,
            brand_id: brandFilter.value,
            price_mode: priceMode.value,
            published: published.value,
            out_of_stock: outOfStock.value,
        },
        { preserveState: true },
    );
}
function save(row: any) {
    if (busy.value === row.id) return;
    busy.value = row.id;
    status.value = 'Guardando…';
    router.patch(
        `/admin/products/${row.id}/quick`,
        { price: row.price, stock: row.stock },
        {
            preserveScroll: true,
            onSuccess: () => (status.value = 'Cambios guardados.'),
            onError: () =>
                (status.value = 'No se pudieron guardar los cambios.'),
            onFinish: () => (busy.value = null),
        },
    );
}
function visibility(is_published: boolean) {
    router.post(
        '/admin/products/visibility',
        { product_ids: selected.value, is_published },
        { preserveScroll: true },
    );
}
function bulk() {
    router.post(
        '/admin/products/bulk-pricing',
        {
            product_ids: selected.value,
            category_id: bulkCategory.value || null,
            percent: pct.value,
        },
        { preserveScroll: true },
    );
}
</script>
<template>
    <Head title="Productos" />
    <section class="p-6">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p
                    class="font-semibold tracking-widest text-[#E30613] uppercase"
                >
                    Catálogo
                </p>
                <h1 class="text-3xl font-bold">Productos</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <input
                    v-model="search"
                    placeholder="Buscar nombre o SKU"
                    class="rounded-md border px-3 py-2"
                    @keyup.enter="applyFilters"
                /><select
                    v-model="categoryFilter"
                    class="rounded-md border px-3 py-2"
                >
                    <option value="">Todas las categorías</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">
                        {{ c.name }}
                    </option></select
                ><select
                    v-model="brandFilter"
                    class="rounded-md border px-3 py-2"
                >
                    <option value="">Todas las marcas</option>
                    <option v-for="b in brands" :key="b.id" :value="b.id">
                        {{ b.name }}
                    </option></select
                ><select
                    v-model="priceMode"
                    class="rounded-md border px-3 py-2"
                >
                    <option value="">Precio: cualquiera</option>
                    <option value="show">Precio visible</option>
                    <option value="quote">Cotizar</option></select
                ><select
                    v-model="published"
                    class="rounded-md border px-3 py-2"
                >
                    <option value="">Web: cualquiera</option>
                    <option value="1">Publicado</option>
                    <option value="0">Oculto</option></select
                ><label class="flex items-center gap-1"
                    ><input v-model="outOfStock" type="checkbox" />Sin
                    existencias</label
                ><button class="rounded border px-3 py-2" @click="applyFilters">
                    Filtrar</button
                ><a
                    href="/admin/products/create"
                    class="rounded bg-[#E30613] px-4 py-2 text-white"
                    >Nuevo producto</a
                >
            </div>
        </div>
        <div v-if="canBulk" class="mb-4 flex flex-wrap gap-2">
            <select v-model="bulkCategory" class="rounded border px-3 py-2">
                <option value="">Solo seleccionados</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">
                    Categoría: {{ c.name }}
                </option></select
            ><input
                v-model.number="pct"
                type="number"
                class="w-28 rounded border px-3 py-2"
                placeholder="± %"
            /><button
                class="rounded bg-[#E30613] px-3 py-2 text-white"
                @click="bulk"
            >
                Ajustar precio</button
            ><span v-if="status" class="self-center text-sm">{{ status }}</span>
        </div>
        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-3">Sel.</th>
                        <th class="p-3">SKU / producto</th>
                        <th class="p-3">Categoría</th>
                        <th class="p-3">Marca</th>
                        <th class="p-3">Costo</th>
                        <th class="p-3">Precio con IVA</th>
                        <th
                            v-if="$page.props.auth.user.role === 'owner'"
                            class="p-3"
                        >
                            Margen
                        </th>
                        <th class="p-3">Existencias</th>
                        <th class="p-3">Web</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in products.data" :key="p.id" class="border-t">
                        <td class="p-3">
                            <input
                                v-model="selected"
                                type="checkbox"
                                :value="p.id"
                            />
                        </td>
                        <td class="p-3">
                            <b>{{ p.sku }}</b
                            ><br /><a
                                :href="`/admin/products/${p.id}/edit`"
                                class="underline"
                                >{{ p.name }}</a
                            >
                        </td>
                        <td class="p-3">{{ p.category?.name }}</td>
                        <td class="p-3">{{ p.brand?.name || '—' }}</td>
                        <td class="p-3">
                            {{
                                p.cost === null
                                    ? '—'
                                    : `$${p.cost} ${p.cost_currency}`
                            }}
                        </td>
                        <td class="p-2">
                            <input
                                v-model.number="p.price"
                                class="w-28 rounded border px-2 py-1"
                                @keyup.enter="save(p)"
                                @blur="save(p)"
                            />
                        </td>
                        <td
                            v-if="$page.props.auth.user.role === 'owner'"
                            class="p-3"
                        >
                            {{
                                p.margin === null
                                    ? '—'
                                    : `${(p.margin * 100).toFixed(1)}%`
                            }}
                        </td>
                        <td class="p-2">
                            <input
                                v-model.number="p.stock"
                                type="number"
                                min="0"
                                class="w-20 rounded border px-2 py-1"
                                @keyup.enter="save(p)"
                                @blur="save(p)"
                            />
                        </td>
                        <td class="p-3">
                            {{ p.is_published ? 'Publicado' : 'Oculto' }}
                            <small
                                v-if="p.price_below_cost"
                                class="block text-amber-700"
                                >El precio sin IVA queda por debajo del
                                costo</small
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="mt-4 text-sm text-muted-foreground">
            Guardar precio o existencias: presiona Enter o sal del campo.
            {{ status }}
        </p>
    </section>
</template>
