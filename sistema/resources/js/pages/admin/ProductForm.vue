<script setup lang="ts">
import { ArrowLeft, ExternalLink, Plus } from '@lucide/vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import SpecInput from '@/components/SpecInput.vue';
import type { SpecSchema } from '@/lib/specs';
import AlertError from '@/components/AlertError.vue';
import ImageUploader from '@/components/ImageUploader.vue';
import type { ProductImg } from '@/components/ImageUploader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps<{
    specSchema: SpecSchema;
    product: any | null;
    categories: { id: number; name: string; builder_slot: string | null }[];
    brands: { id: number; name: string }[];
}>();
const form = useForm({
    sku: props.product?.sku ?? '',
    name: props.product?.name ?? '',
    model: props.product?.model ?? '',
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
    stock_24h: props.product?.stock_24h ?? 0,
    is_published: props.product?.is_published ?? false,
    is_featured: props.product?.is_featured ?? false,
    specs: props.product?.specs ?? {},
    confirm_specs: false,
    warranty_months: props.product?.warranty_months ?? null,
});
const images = ref<ProductImg[]>(props.product?.images ?? []);
const slot = computed(
    () => props.categories.find((c) => c.id == form.category_id)?.builder_slot,
);
const fields = Object.fromEntries(
    Object.entries(props.specSchema).map(([slot, fields]) => [
        slot,
        Object.keys(fields),
    ]),
);
const fieldLabels = Object.fromEntries(
    Object.values(props.specSchema).flatMap((fields) =>
        Object.entries(fields).map(([key, field]) => [key, field.label]),
    ),
);
const sourceLabels: Record<string, string> = {
    manual: 'Manual',
    icecat: 'Icecat',
    tabla: 'Tabla',
    nombre: 'Del nombre',
    supuesto: 'Por confirmar',
};
const specSource = (key: string) =>
    form.confirm_specs ||
    JSON.stringify(form.specs[key]) !==
        JSON.stringify(props.product?.specs?.[key])
        ? 'Manual'
        : (sourceLabels[props.product?.spec_sources?.[key]] ?? 'Por confirmar');
const customSpecs = ref<Array<{ name: string; value: string }>>(
    Array.isArray(props.product?.specs) ? props.product.specs : [],
);

const sections = [
    { id: 'general', label: 'General' },
    { id: 'precio', label: 'Precio y existencias' },
    { id: 'fotos', label: 'Fotos' },
    { id: 'specs', label: 'Especificaciones' },
    { id: 'web', label: 'Web y SEO' },
];
const money = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 2,
});
const preview = computed(() => ({
    image: images.value[0]
        ? `/uploads/${images.value[0].path}`
        : '/img/producto-sin-foto.svg',
    brand: props.brands.find((b) => b.id == form.brand_id)?.name ?? '',
    price:
        form.price_mode === 'quote' || !Number(form.price)
            ? 'Cotizar'
            : money.format(Number(form.price)),
    stock:
        form.stock > 0
            ? 'Entrega inmediata'
            : form.stock_24h > 0
              ? 'Llega mañana'
              : 'Sobre pedido',
    level:
        form.stock > 0
            ? 'bg-emerald-500'
            : form.stock_24h > 0
              ? 'bg-amber-500'
              : 'bg-zinc-400',
}));
const belowCost = computed(() => {
    const cost = Number(form.cost);
    return (
        form.cost_currency === 'MXN' &&
        cost > 0 &&
        Number(form.price) > 0 &&
        Number(form.price) / 1.16 < cost
    );
});

let submitting = false;
let leaving = false;
function save(after: 'stay' | 'shop' = 'stay') {
    submitting = true;
    leaving = after === 'shop';
    const url = props.product
        ? `/admin/products/${props.product.id}`
        : '/admin/products';
    form.transform((d) => ({
        ...d,
        specs: slot.value ? d.specs : customSpecs.value,
        after,
        _method: props.product ? 'patch' : 'post',
    })).post(url, {
        preserveScroll: true,
        onSuccess: () => {
            form.confirm_specs = false;
            form.defaults();
            if (props.product) toast.success('Producto guardado.');
        },
        onError: () => toast.error('Revisa los campos marcados.'),
        onFinish: () => (submitting = false),
    });
}

/* Aviso de cambios sin guardar al salir. */
function beforeUnload(e: BeforeUnloadEvent) {
    if (form.isDirty && !leaving) e.preventDefault();
}
let off: (() => void) | undefined;
onMounted(() => {
    window.addEventListener('beforeunload', beforeUnload);
    off = router.on(
        'before',
        () =>
            submitting ||
            !form.isDirty ||
            confirm('Tienes cambios sin guardar. ¿Salir de todos modos?'),
    );
    if (props.product && new URLSearchParams(location.search).has('fotos')) {
        toast.success('Producto creado. Ahora agrega sus fotos.');
        void nextTick(() =>
            document
                .getElementById('fotos')
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' }),
        );
    }
});
onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', beforeUnload);
    off?.();
});
const field = 'h-9 w-full rounded-md border bg-background px-2 text-sm';
</script>

<template>
    <Head :title="product ? 'Editar producto' : 'Nuevo producto'" />
    <section
        class="mx-auto grid w-full max-w-6xl gap-4 p-4 pb-0 md:p-6 md:pb-0"
    >
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <Link
                    href="/admin/products"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                    ><ArrowLeft class="size-4" /> Productos</Link
                >
                <h1 class="mt-1 text-2xl font-bold tracking-tight md:text-3xl">
                    {{ product ? product.name : 'Nuevo producto' }}
                </h1>
            </div>
            <a
                v-if="product?.is_published"
                :href="`/producto/${product.slug}`"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >Ver en tienda <ExternalLink class="size-3.5"
            /></a>
        </header>

        <div class="grid gap-6 lg:grid-cols-[180px_minmax(0,1fr)_280px]">
            <nav class="hidden lg:block" aria-label="Secciones">
                <ul class="sticky top-6 grid gap-1 text-sm">
                    <li v-for="s in sections" :key="s.id">
                        <a
                            :href="`#${s.id}`"
                            class="block rounded-md px-3 py-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                            >{{ s.label }}</a
                        >
                    </li>
                </ul>
            </nav>

            <form class="grid min-w-0 gap-6" @submit.prevent="save()">
                <AlertError
                    v-if="Object.keys(form.errors).length"
                    :errors="Object.values(form.errors)"
                />

                <section
                    id="general"
                    class="grid scroll-mt-6 gap-4 rounded-xl border bg-card p-5 md:grid-cols-2"
                >
                    <h2 class="text-lg font-semibold md:col-span-2">General</h2>
                    <label class="grid gap-1 text-sm md:col-span-2"
                        >Nombre<Input v-model="form.name" required
                    /></label>
                    <label class="grid gap-1 text-sm"
                        >Modelo<Input
                            v-model="form.model"
                            maxlength="255"
                        /><small
                            v-if="form.errors.model"
                            class="text-destructive"
                            >{{ form.errors.model }}</small
                        ></label
                    >
                    <label class="grid gap-1 text-sm"
                        >SKU<Input v-model="form.sku" required
                    /></label>
                    <label class="grid gap-1 text-sm"
                        >Garantía (meses)<Input
                            v-model.number="form.warranty_months"
                            type="number"
                            min="0"
                    /></label>
                    <label class="grid gap-1 text-sm"
                        >Categoría
                        <select
                            v-model="form.category_id"
                            required
                            :class="field"
                        >
                            <option value="">Selecciona</option>
                            <option
                                v-for="c in categories"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }}
                            </option>
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm"
                        >Marca
                        <select v-model="form.brand_id" :class="field">
                            <option value="">Sin marca</option>
                            <option
                                v-for="b in brands"
                                :key="b.id"
                                :value="b.id"
                            >
                                {{ b.name }}
                            </option>
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm md:col-span-2"
                        >Condición o nota breve<Input
                            v-model="form.short_description"
                            placeholder="Ej. Caja abierta, Remate"
                    /></label>
                </section>

                <section
                    id="precio"
                    class="grid scroll-mt-6 gap-4 rounded-xl border bg-card p-5 md:grid-cols-2"
                >
                    <h2 class="text-lg font-semibold md:col-span-2">
                        Precio y existencias
                    </h2>
                    <label class="grid gap-1 text-sm"
                        >Precio con IVA<Input
                            v-model.number="form.price"
                            type="number"
                            step=".01"
                            min="0"
                            placeholder="Vacío = Cotizar"
                    /></label>
                    <label class="grid gap-1 text-sm"
                        >En la tienda
                        <select v-model="form.price_mode" :class="field">
                            <option value="show">Mostrar precio</option>
                            <option value="quote">Cotizar</option>
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm"
                        >Costo<Input
                            v-model.number="form.cost"
                            type="number"
                            step=".01"
                            min="0"
                    /></label>
                    <label class="grid gap-1 text-sm"
                        >Moneda del costo
                        <select v-model="form.cost_currency" :class="field">
                            <option>MXN</option>
                            <option>USD</option>
                        </select>
                    </label>
                    <p
                        v-if="belowCost"
                        class="rounded-md bg-amber-500/10 px-3 py-2 text-sm text-amber-700 md:col-span-2 dark:text-amber-400"
                    >
                        El precio sin IVA queda por debajo del costo.
                    </p>
                    <label class="grid gap-1 text-sm"
                        >Entrega inmediata (en tienda)<Input
                            v-model.number="form.stock"
                            type="number"
                            min="0"
                    /></label>
                    <label class="grid gap-1 text-sm"
                        >Llega en 24 h<Input
                            v-model.number="form.stock_24h"
                            type="number"
                            min="0"
                    /></label>
                    <details
                        v-if="product?.price_changes?.length"
                        class="text-sm md:col-span-2"
                    >
                        <summary class="cursor-pointer text-muted-foreground">
                            Historial de precios ({{
                                product.price_changes.length
                            }})
                        </summary>
                        <ul class="mt-2 divide-y rounded-md border">
                            <li
                                v-for="change in product.price_changes"
                                :key="change.id"
                                class="flex flex-wrap justify-between gap-2 px-3 py-2"
                            >
                                <span class="tabular-nums"
                                    >${{ change.old_price ?? '—' }} → ${{
                                        change.new_price ?? '—'
                                    }}
                                    · costo ${{ change.old_cost ?? '—' }} → ${{
                                        change.new_cost ?? '—'
                                    }}</span
                                >
                                <span class="text-muted-foreground"
                                    >{{
                                        new Date(
                                            change.created_at,
                                        ).toLocaleString('es-MX', {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        })
                                    }}<template v-if="change.user">
                                        · {{ change.user.name }}</template
                                    ></span
                                >
                            </li>
                        </ul>
                    </details>
                </section>

                <section
                    id="fotos"
                    class="grid scroll-mt-6 gap-3 rounded-xl border bg-card p-5"
                >
                    <h2 class="text-lg font-semibold">Fotos</h2>
                    <ImageUploader
                        v-if="product"
                        :product-id="product.id"
                        :images="images"
                        @change="images = $event"
                    />
                    <p
                        v-else
                        class="rounded-md bg-muted px-3 py-4 text-sm text-muted-foreground"
                    >
                        Guarda el producto y enseguida podrás agregar sus fotos
                        aquí.
                    </p>
                </section>

                <section
                    id="specs"
                    class="grid scroll-mt-6 gap-4 rounded-xl border bg-card p-5 md:grid-cols-2"
                >
                    <h2 class="text-lg font-semibold md:col-span-2">
                        {{
                            slot
                                ? 'Especificaciones de armado'
                                : 'Ficha técnica'
                        }}
                    </h2>
                    <div
                        v-if="slot && product?.spec_conflicts?.length"
                        role="alert"
                        class="rounded-md border border-amber-500 bg-amber-50 p-3 text-sm text-amber-950 md:col-span-2 dark:bg-amber-950 dark:text-amber-100"
                    >
                        <p class="font-semibold">
                            La ficha tiene conflictos con Icecat
                        </p>
                        <p>Revisa la variante antes de confirmar la ficha.</p>
                        <ul class="mt-2 list-inside list-disc">
                            <li
                                v-for="conflict in product.spec_conflicts"
                                :key="conflict.field"
                            >
                                {{
                                    fieldLabels[conflict.field] ??
                                    conflict.field
                                }}: actual {{ conflict.actual }}; Icecat
                                {{ conflict.icecat }}.
                            </li>
                        </ul>
                    </div>
                    <Button
                        v-if="slot"
                        type="button"
                        variant="outline"
                        class="md:col-span-2"
                        @click="
                            form.confirm_specs = true;
                            save();
                        "
                        >Confirmar ficha</Button
                    >
                    <template v-if="slot && fields[slot]">
                        <label
                            v-for="key in fields[slot]"
                            :key="key"
                            class="grid gap-1 text-sm"
                            >{{ fieldLabels[key] ?? key }}
                            <small class="text-muted-foreground">{{
                                specSource(key)
                            }}</small>
                            <SpecInput
                                v-model="form.specs[key]"
                                :field="specSchema[slot][key]"
                            />
                        </label>
                    </template>
                    <template v-else>
                        <div
                            v-for="(item, index) in customSpecs"
                            :key="index"
                            class="grid grid-cols-2 gap-2 md:col-span-2"
                        >
                            <Input
                                v-model="item.name"
                                aria-label="Característica"
                                placeholder="Característica"
                            />
                            <Input
                                v-model="item.value"
                                aria-label="Valor"
                                placeholder="Valor"
                            />
                        </div>
                        <Button
                            variant="outline"
                            type="button"
                            class="justify-self-start md:col-span-2"
                            @click="customSpecs.push({ name: '', value: '' })"
                            ><Plus class="size-4" /> Agregar
                            característica</Button
                        >
                    </template>
                </section>

                <section
                    id="web"
                    class="grid scroll-mt-6 gap-4 rounded-xl border bg-card p-5"
                >
                    <h2 class="text-lg font-semibold">Web y SEO</h2>
                    <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                        <label class="flex items-center gap-2"
                            ><input
                                v-model="form.is_published"
                                type="checkbox"
                            />
                            Publicado en la tienda</label
                        >
                        <label class="flex items-center gap-2"
                            ><input
                                v-model="form.is_featured"
                                type="checkbox"
                            />
                            Destacado</label
                        >
                    </div>
                    <label class="grid gap-1 text-sm"
                        >Dirección (slug)<Input
                            v-model="form.slug"
                            placeholder="Se genera con el nombre si lo dejas vacío"
                    /></label>
                    <label class="grid gap-1 text-sm"
                        >Descripción<Textarea
                            v-model="form.description"
                            rows="5"
                    /></label>
                </section>
            </form>

            <aside class="hidden lg:block">
                <div class="sticky top-6 grid gap-2">
                    <p
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Así se verá en la tienda
                    </p>
                    <article class="overflow-hidden rounded-xl border bg-card">
                        <img
                            :src="preview.image"
                            alt=""
                            class="aspect-square w-full bg-muted object-cover"
                        />
                        <div class="grid gap-1 p-3">
                            <span
                                v-if="form.short_description"
                                class="justify-self-start rounded bg-amber-500/15 px-1.5 py-0.5 text-[11px] font-semibold text-amber-700 dark:text-amber-400"
                                >{{ form.short_description }}</span
                            >
                            <span
                                class="text-xs text-muted-foreground uppercase"
                                >{{ preview.brand }}</span
                            >
                            <b class="line-clamp-2 text-sm leading-snug">{{
                                form.name || 'Nombre del producto'
                            }}</b>
                            <span class="mt-1 text-lg font-bold">{{
                                preview.price
                            }}</span>
                            <span
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                                ><i
                                    class="size-2 rounded-full"
                                    :class="preview.level"
                                />{{ preview.stock }}</span
                            >
                            <span
                                v-if="form.warranty_months"
                                class="text-xs text-muted-foreground"
                                >Garantía {{ form.warranty_months }} meses</span
                            >
                        </div>
                    </article>
                    <p
                        v-if="!form.is_published"
                        class="text-xs text-muted-foreground"
                    >
                        Oculto: no aparece en la tienda.
                    </p>
                </div>
            </aside>
        </div>

        <div
            class="sticky bottom-0 z-40 -mx-4 border-t bg-background/95 backdrop-blur md:-mx-6"
        >
            <div class="flex flex-wrap items-center gap-2 px-4 py-3 md:px-6">
                <span class="text-sm text-muted-foreground">{{
                    form.isDirty ? 'Cambios sin guardar' : 'Todo guardado'
                }}</span>
                <span class="ml-auto flex gap-2">
                    <Button
                        v-if="product"
                        variant="outline"
                        :disabled="form.processing || !form.is_published"
                        @click="save('shop')"
                        >Guardar y ver en tienda</Button
                    >
                    <Button :disabled="form.processing" @click="save()">{{
                        form.processing
                            ? 'Guardando…'
                            : product
                              ? 'Guardar'
                              : 'Crear producto'
                    }}</Button>
                </span>
            </div>
        </div>
    </section>
</template>
