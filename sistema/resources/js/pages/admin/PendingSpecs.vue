<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import PendingSpecRow from '@/components/PendingSpecRow.vue';
import type { PendingProduct } from '@/components/PendingSpecRow.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { firstError, http } from '@/lib/http';
import type { SpecField } from '@/lib/specs';
import { edit } from '@/routes/admin/builds';
import { pending, review } from '@/routes/admin/specs';
const props = defineProps<{
    products: {
        data: PendingProduct[];
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    slots: Record<string, string>;
    counts: Record<string, number>;
    schema: Record<string, SpecField>;
    filters: { slot: string; search: string; in_stock: boolean };
}>();
const search = ref(props.filters.search);
const inStock = ref(props.filters.in_stock);
function filter(slot = props.filters.slot) {
    router.get(
        pending.url(),
        { slot, search: search.value, in_stock: inStock.value ? 1 : 0 },
        { preserveScroll: true },
    );
}
type Review = {
    published: number;
    unpublished: number;
    ready: number;
    results: {
        id: number;
        name: string;
        status: 'publicado' | 'despublicado' | 'listo' | 'con_problemas';
        problems: string[];
    }[];
};
const result = ref<Review | null>(null);
const reviewing = ref(false);
async function reviewBuilds() {
    reviewing.value = true;
    const response = await http<Review>('POST', review.url());
    reviewing.value = false;
    if (response.ok) result.value = response.data;
    else toast.error(firstError(response.data));
}
</script>
<template>
    <Head title="Fichas pendientes" />
    <div class="mx-auto w-full max-w-7xl min-w-0 space-y-5 p-4 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Fichas pendientes</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Confirma los datos técnicos que necesitan los armados
                    sugeridos.
                </p>
            </div>
            <Button
                variant="outline"
                class="min-h-11"
                :disabled="reviewing"
                @click="reviewBuilds"
                >{{ reviewing ? 'Revisando…' : 'Revisar armados' }}</Button
            >
        </div>
        <div
            v-if="result"
            role="status"
            class="space-y-2 rounded-lg border p-4 text-sm"
        >
            <p class="font-medium">
                {{ result.published }} siguen publicados ·
                {{ result.unpublished }} despublicados · Listos para publicar:
                {{ result.ready }}
            </p>
            <p v-if="!result.results.length">No hay armados para revisar.</p>
            <ul class="max-h-64 space-y-2 overflow-y-auto">
                <li v-for="build in result.results" :key="build.id">
                    <strong>{{ build.name }}</strong
                    >:
                    {{
                        build.status === 'publicado'
                            ? 'Publicado; ficha completa y compatible.'
                            : build.status === 'listo'
                              ? 'Listo para publicar.'
                              : `${build.status === 'despublicado' ? 'Despublicado' : 'Con problemas'}: ${build.problems.join(' ')}`
                    }}
                    <Link
                        v-if="build.status === 'listo'"
                        :href="edit.url(build.id)"
                        class="ml-2 inline-flex min-h-11 items-center underline"
                        >Publicar</Link
                    >
                </li>
            </ul>
        </div>
        <nav
            aria-label="Tipo de pieza"
            class="flex gap-1 overflow-x-auto border-b"
        >
            <button
                v-for="(label, slot) in slots"
                :key="slot"
                type="button"
                :aria-current="slot === filters.slot ? 'page' : undefined"
                class="min-h-11 shrink-0 border-b-2 px-3 py-2 text-sm"
                :class="
                    slot === filters.slot
                        ? 'border-foreground font-medium'
                        : 'border-transparent text-muted-foreground'
                "
                @click="filter(slot)"
            >
                {{ label }}
                <span class="ml-1 tabular-nums">{{ counts[slot] }}</span>
            </button>
        </nav>
        <form
            class="flex flex-wrap items-center gap-3"
            @submit.prevent="filter()"
        >
            <Input
                v-model="search"
                aria-label="Buscar por nombre, SKU o marca"
                placeholder="Nombre, SKU o marca"
                class="min-h-11 w-full sm:max-w-sm"
                type="search"
            />
            <label class="flex min-h-11 items-center gap-2 text-sm"
                ><input
                    v-model="inStock"
                    type="checkbox"
                    @change="filter()"
                />Solo con existencias</label
            >
            <Button variant="outline" type="submit" class="min-h-11"
                >Buscar</Button
            >
            <span class="text-sm text-muted-foreground"
                >{{ products.total }} pendientes</span
            >
        </form>
        <div class="overflow-hidden rounded-lg border bg-card">
            <PendingSpecRow
                v-for="product in products.data"
                :key="`${filters.slot}-${product.id}-${JSON.stringify(product.specs)}-${product.pending.join(',')}`"
                :product="product"
                :schema="schema"
            />
            <p
                v-if="!products.data.length"
                class="p-8 text-center text-sm text-muted-foreground"
            >
                No hay fichas pendientes con estos filtros.
            </p>
        </div>
        <nav
            v-if="products.links.length > 3"
            aria-label="Paginación"
            class="flex flex-wrap gap-1"
        >
            <template v-for="(link, index) in products.links" :key="index"
                ><Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="flex min-h-11 min-w-11 items-center justify-center rounded border px-3 text-sm"
                    :class="link.active ? 'bg-muted font-medium' : ''"
                    :aria-current="link.active ? 'page' : undefined"
                    >{{
                        index === 0
                            ? 'Anterior'
                            : index === products.links.length - 1
                              ? 'Siguiente'
                              : link.label
                    }}</Link
                ><span
                    v-else
                    class="flex min-h-11 items-center px-3 text-sm text-muted-foreground"
                    >{{
                        index === 0
                            ? 'Anterior'
                            : index === products.links.length - 1
                              ? 'Siguiente'
                              : link.label
                    }}</span
                ></template
            >
        </nav>
    </div>
</template>
