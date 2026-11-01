<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const props = defineProps<{
    product: any | null;
    categories: any[];
    brands: any[];
}>();
const form = useForm({
    sku: props.product?.sku ?? '',
    name: props.product?.name ?? '',
    slug: props.product?.slug ?? '',
    category_id: props.product?.category_id ?? '',
    brand_id: props.product?.brand_id ?? '',
    short_description: props.product?.short_description ?? '',
    description: props.product?.description ?? '',
    cost: props.product?.cost ?? null,
    cost_currency: props.product?.cost_currency ?? 'MXN',
    price: props.product?.price ?? null,
    price_mode: props.product?.price_mode ?? 'show',
    stock: props.product?.stock ?? 0,
    is_published: props.product?.is_published ?? false,
    is_featured: props.product?.is_featured ?? false,
    specs: props.product?.specs ?? {},
    warranty_months: props.product?.warranty_months ?? null,
});
const slot = computed(
    () => props.categories.find((c) => c.id == form.category_id)?.builder_slot,
);
const fields: Record<string, string[]> = {
    cpu: ['socket', 'cores', 'threads', 'tdp_w', 'igpu', 'tier'],
    motherboard: ['socket', 'memory_type', 'form_factor', 'm2_slots'],
    ram: ['memory_type', 'capacity_gb', 'modules', 'speed_mhz'],
    gpu: ['tdp_w', 'vram_gb', 'length_mm', 'tier'],
    storage: ['type', 'capacity_gb'],
    psu: ['watts', 'certification'],
    cooler: ['type', 'sockets', 'max_tdp_w'],
    case: ['form_factors', 'max_gpu_length_mm'],
};
const uploads = ref<FileList | null>(null);
const customSpecs = ref<Array<{ name: string; value: string }>>(
    Array.isArray(props.product?.specs) ? props.product.specs : [],
);
function addSpec() {
    customSpecs.value.push({ name: '', value: '' });
}
function moveImage(image: any, direction: number) {
    router.patch(
        `/admin/images/${image.id}`,
        { sort: Math.max(0, image.sort + direction) },
        { preserveScroll: true },
    );
}
function save() {
    const url = props.product
        ? `/admin/products/${props.product.id}`
        : '/admin/products';
    form.transform((d) => ({
        ...d,
        specs: slot.value ? d.specs : customSpecs.value,
        _method: props.product ? 'patch' : 'post',
    })).post(url, { forceFormData: true });
}
function photos() {
    if (!uploads.value || !props.product) return;
    const fd = new FormData();
    Array.from(uploads.value).forEach((file) => fd.append('images[]', file));
    router.post(`/admin/products/${props.product.id}/images`, fd, {
        forceFormData: true,
    });
}
function removeImage(id: number) {
    router.delete(`/admin/images/${id}`, { preserveScroll: true });
}
</script>
<template>
    <Head :title="product ? 'Editar producto' : 'Nuevo producto'" />
    <section class="p-6">
        <a href="/admin/products" class="text-sm text-[#E30613]"
            >← Volver al catálogo</a
        >
        <h1 class="my-4 text-3xl font-bold">
            {{ product ? 'Editar producto' : 'Nuevo producto' }}
        </h1>
        <form
            class="grid max-w-5xl gap-4 rounded-lg border p-5 md:grid-cols-2"
            @submit.prevent="save"
        >
            <label class="grid gap-1"
                >SKU<input
                    v-model="form.sku"
                    required
                    class="rounded border p-2" /></label
            ><label class="grid gap-1"
                >Nombre<input
                    v-model="form.name"
                    required
                    class="rounded border p-2" /></label
            ><label class="grid gap-1"
                >Categoría<select
                    v-model="form.category_id"
                    required
                    class="rounded border p-2"
                >
                    <option value="">Selecciona</option>
                    <option v-for="c in categories" :value="c.id">
                        {{ c.name }}
                    </option>
                </select></label
            ><label class="grid gap-1"
                >Marca<select
                    v-model="form.brand_id"
                    class="rounded border p-2"
                >
                    <option value="">Sin marca</option>
                    <option v-for="b in brands" :value="b.id">
                        {{ b.name }}
                    </option>
                </select></label
            ><label class="grid gap-1"
                >Costo<input
                    v-model.number="form.cost"
                    type="number"
                    step=".01"
                    class="rounded border p-2" /></label
            ><label class="grid gap-1"
                >Moneda del costo<select
                    v-model="form.cost_currency"
                    class="rounded border p-2"
                >
                    <option>MXN</option>
                    <option>USD</option>
                </select></label
            ><label class="grid gap-1"
                >Precio con IVA<input
                    v-model.number="form.price"
                    type="number"
                    step=".01"
                    class="rounded border p-2" /></label
            ><label class="grid gap-1"
                >Modo de precio<select
                    v-model="form.price_mode"
                    class="rounded border p-2"
                >
                    <option value="show">Mostrar precio</option>
                    <option value="quote">Cotizar</option>
                </select></label
            ><label class="grid gap-1"
                >Existencias<input
                    v-model.number="form.stock"
                    min="0"
                    type="number"
                    class="rounded border p-2" /></label
            ><label class="grid gap-1"
                >Garantía (meses)<input
                    v-model.number="form.warranty_months"
                    min="0"
                    type="number"
                    class="rounded border p-2" /></label
            ><label class="flex items-center gap-2"
                ><input v-model="form.is_published" type="checkbox" />Publicado
                en la web</label
            ><label class="flex items-center gap-2"
                ><input
                    v-model="form.is_featured"
                    type="checkbox"
                />Destacado</label
            ><label class="grid gap-1 md:col-span-2"
                >Descripción breve<input
                    v-model="form.short_description"
                    class="rounded border p-2" /></label
            ><label class="grid gap-1 md:col-span-2"
                >Descripción<textarea
                    v-model="form.description"
                    rows="3"
                    class="rounded border p-2"
                />
            </label>
            <div v-if="slot && fields[slot]" class="grid gap-3 md:col-span-2">
                <h2 class="font-semibold">Especificaciones · {{ slot }}</h2>
                <label v-for="key in fields[slot]" class="grid gap-1"
                    >{{ key
                    }}<select
                        v-if="key === 'igpu'"
                        v-model="form.specs[key]"
                        class="rounded border p-2"
                    >
                        <option :value="true">Sí</option>
                        <option :value="false">No</option></select
                    ><select
                        v-else-if="['memory_type'].includes(key)"
                        v-model="form.specs[key]"
                        class="rounded border p-2"
                    >
                        <option>DDR4</option>
                        <option>DDR5</option></select
                    ><select
                        v-else-if="key === 'form_factor'"
                        v-model="form.specs[key]"
                        class="rounded border p-2"
                    >
                        <option>ATX</option>
                        <option>mATX</option>
                        <option>ITX</option></select
                    ><select
                        v-else-if="key === 'type' && slot === 'storage'"
                        v-model="form.specs[key]"
                        class="rounded border p-2"
                    >
                        <option>NVMe</option>
                        <option>SATA SSD</option>
                        <option>HDD</option></select
                    ><select
                        v-else-if="key === 'type' && slot === 'cooler'"
                        v-model="form.specs[key]"
                        class="rounded border p-2"
                    >
                        <option>aire</option>
                        <option>líquida</option></select
                    ><div v-else-if="key === 'sockets' || key === 'form_factors'" class="flex flex-wrap gap-3"><label v-for="option in (key === 'sockets' ? ['AM4','AM5','LGA1700','LGA1851'] : ['ATX','mATX','ITX'])" :key="option" class="flex items-center gap-1"><input v-model="form.specs[key]" type="checkbox" :value="option" />{{ option }}</label></div><input
                        v-else
                        v-model="form.specs[key]"
                        :type="
                            [
                                'cores',
                                'threads',
                                'tdp_w',
                                'tier',
                                'm2_slots',
                                'capacity_gb',
                                'modules',
                                'speed_mhz',
                                'vram_gb',
                                'length_mm',
                                'watts',
                                'max_tdp_w',
                                'max_gpu_length_mm',
                            ].includes(key)
                                ? 'number'
                                : 'text'
                        "
                        class="rounded border p-2"
                /></label>
            </div>
            <div v-else class="grid gap-3 md:col-span-2">
                <h2 class="font-semibold">Ficha técnica</h2>
                <div
                    v-for="(item, index) in customSpecs"
                    :key="index"
                    class="flex gap-2"
                >
                    <input
                        v-model="item.name"
                        placeholder="Característica"
                        class="rounded border p-2"
                    />
                    <input
                        v-model="item.value"
                        placeholder="Valor"
                        class="rounded border p-2"
                    />
                </div>
                <button
                    type="button"
                    class="rounded border p-2"
                    @click="addSpec"
                >
                    Agregar característica
                </button>
            </div>
            <button class="rounded bg-[#E30613] p-3 text-white md:col-span-2">
                Guardar producto
            </button>
        </form>
        <div v-if="product" class="mt-8 grid max-w-5xl gap-8 lg:grid-cols-2">
            <div>
                <h2 class="mb-3 text-xl font-bold">Fotos del producto</h2>
                <input
                    type="file"
                    multiple
                    accept="image/jpeg,image/png,image/webp"
                    @change="
                        uploads = ($event.target as HTMLInputElement).files
                    "
                /><button class="ml-2 rounded border px-3 py-2" @click="photos">
                    Subir fotos
                </button>
                <div class="mt-4 flex flex-wrap gap-3">
                    <figure v-for="img in product.images" :key="img.id">
                        <img
                            :src="`/uploads/${img.path}`"
                            class="h-24 w-24 rounded object-cover"
                        /><button class="text-sm" @click="moveImage(img, -1)">
                            ↑</button
                        ><button class="text-sm" @click="moveImage(img, 1)">
                            ↓</button
                        ><button
                            class="text-sm text-red-600"
                            @click="removeImage(img.id)"
                        >
                            Borrar
                        </button>
                    </figure>
                </div>
            </div>
            <div>
                <h2 class="mb-3 text-xl font-bold">Historial de precios</h2>
                <ul class="divide-y rounded border">
                    <li v-for="change in product.price_changes" class="p-3">
                        {{ change.created_at }} · ${{
                            change.old_price ?? '—'
                        }}
                        → ${{ change.new_price ?? '—' }} · costo ${{
                            change.old_cost ?? '—'
                        }}
                        → ${{ change.new_cost ?? '—' }}
                        <span v-if="change.user">· {{ change.user.name }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
