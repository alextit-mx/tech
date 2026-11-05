<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import SpecInput from '@/components/SpecInput.vue';
import { Button } from '@/components/ui/button';
import { http, firstError } from '@/lib/http';
import { sourceLabels } from '@/lib/specs';
import type { SpecField, SpecValue } from '@/lib/specs';
import { update } from '@/routes/admin/specs';
import { edit } from '@/routes/admin/products';
export type PendingProduct = {
    id: number;
    name: string;
    sku: string;
    brand: string | null;
    image: string;
    stock: number;
    stock_24h: number;
    is_published: boolean;
    pending: string[];
    specs: Record<string, SpecValue>;
    sources: Record<string, string>;
    conflicts: { field: string; actual: SpecValue; icecat: SpecValue }[];
};
const props = defineProps<{
    product: PendingProduct;
    schema: Record<string, SpecField>;
}>();
const row = ref(props.product);
const values = ref<Record<string, SpecValue>>(
    Object.fromEntries(
        row.value.pending.map((key) => [key, row.value.specs[key] ?? null]),
    ),
);
const errors = ref<Record<string, string[]>>({});
const saving = ref(false);
const completed = ref(false);
const display = (value: SpecValue) =>
    Array.isArray(value)
        ? value.join(', ')
        : typeof value === 'boolean'
          ? value
              ? 'Sí'
              : 'No'
          : String(value);
async function save() {
    saving.value = true;
    errors.value = {};
    const specs = Object.fromEntries(
        Object.entries(values.value).filter(
            ([key, value]) =>
                row.value.pending.includes(key) &&
                value !== null &&
                value !== '' &&
                (!Array.isArray(value) || value.length > 0),
        ),
    );
    const result = await http<{
        product: PendingProduct;
        complete: boolean;
        errors?: Record<string, string[]>;
    }>('PATCH', update.url(row.value.id), { specs });
    saving.value = false;
    if (!result.ok) {
        errors.value = result.data?.errors ?? {};
        toast.error(firstError(result.data));
        return;
    }
    row.value = result.data.product;
    completed.value = result.data.complete;
    values.value = Object.fromEntries(
        row.value.pending.map((key) => [key, row.value.specs[key] ?? null]),
    );
    toast.success(
        completed.value
            ? 'Ficha confirmada. Producto retirado de pendientes.'
            : 'Cambios guardados. Aún hay campos pendientes.',
    );
    router.reload({ only: ['products', 'counts', 'pendingSpecsCount'] });
}
</script>
<template>
    <form
        v-if="!completed"
        class="space-y-4 border-b p-4 last:border-b-0 sm:p-5"
        @submit.prevent="save"
    >
        <div class="flex items-start gap-3">
            <img
                :src="row.image"
                alt=""
                class="h-14 w-14 shrink-0 rounded object-contain"
            />
            <div class="min-w-0 flex-1">
                <h2 class="text-base font-medium break-words">
                    {{ row.name }}
                </h2>
                <p class="text-sm break-words text-muted-foreground">
                    {{ row.sku }} · {{ row.brand || 'Sin marca' }} ·
                    {{ row.stock + row.stock_24h }} en existencia ({{
                        row.stock
                    }}
                    local · {{ row.stock_24h }} en 24 h) ·
                    {{ row.is_published ? 'Publicado' : 'Oculto' }}
                </p>
                <div
                    class="mt-1 flex flex-wrap gap-x-4 text-sm underline underline-offset-4"
                >
                    <a
                        :href="`https://www.google.com/search?q=${encodeURIComponent([row.brand, row.name, row.sku, 'especificaciones'].filter(Boolean).join(' '))}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        >Buscar ficha<span class="sr-only">
                            (nueva pestaña)</span
                        ></a
                    >
                    <Link :href="edit(row.id)">Editor completo</Link>
                </div>
            </div>
        </div>
        <div class="grid items-start gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="key in row.pending"
                :key="key"
                class="min-w-0 space-y-1"
            >
                <label
                    :for="`spec-${row.id}-${key}`"
                    class="text-sm font-medium"
                    >{{ schema[key].label }}</label
                >
                <span
                    class="ml-2 inline-block rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                    >{{ sourceLabels[row.sources[key]] || 'Estimado' }}</span
                >
                <SpecInput
                    :id="`spec-${row.id}-${key}`"
                    v-model="values[key]"
                    :field="schema[key]"
                />
                <div
                    v-for="conflict in row.conflicts.filter(
                        (c) => c.field === key,
                    )"
                    :key="conflict.field"
                    class="flex flex-wrap gap-2 text-sm"
                >
                    <button
                        type="button"
                        class="min-h-11 rounded border px-2 text-left"
                        @click="values[key] = conflict.icecat"
                    >
                        Icecat: {{ display(conflict.icecat) }}
                    </button>
                    <button
                        type="button"
                        class="min-h-11 rounded border px-2 text-left"
                        @click="values[key] = conflict.actual"
                    >
                        {{ sourceLabels[row.sources[key]] || 'Actual' }}:
                        {{ display(conflict.actual) }}
                    </button>
                </div>
                <p
                    v-for="(error, index) in Object.entries(errors)
                        .filter(
                            ([path]) =>
                                path === `specs.${key}` ||
                                path.startsWith(`specs.${key}.`),
                        )
                        .flatMap(([, messages]) => messages)"
                    :key="index"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ error }}
                </p>
            </div>
        </div>
        <p v-if="errors.specs" role="alert" class="text-sm text-destructive">
            {{ errors.specs[0] }}
        </p>
        <Button type="submit" :disabled="saving" class="min-h-11">{{
            saving ? 'Guardando…' : 'Guardar'
        }}</Button>
    </form>
</template>
