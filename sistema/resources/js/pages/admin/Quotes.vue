<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps<{
    quotes: any;
    filters: {
        search?: string;
        status?: string;
        source?: string;
        new?: string;
        mine: boolean;
    };
    statuses: Record<string, string>;
    sources: Record<string, string>;
    newCount: number;
}>();

const f = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    source: props.filters.source ?? '',
    mine: props.filters.mine,
    new: props.filters.new === '1',
});
const money = (n: string | number) =>
    '$' + Number(n).toLocaleString('es-MX', { maximumFractionDigits: 0 });
const badge: Record<string, string> = {
    draft: 'bg-zinc-500/15 text-zinc-600 dark:text-zinc-300',
    sent: 'bg-blue-500/15 text-blue-700 dark:text-blue-300',
    accepted: 'bg-green-500/15 text-green-700 dark:text-green-300',
    rejected: 'bg-red-500/15 text-red-700 dark:text-red-300',
    expired: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
};

function apply() {
    router.get(
        '/admin/quotes',
        {
            search: f.search,
            status: f.status,
            source: f.source,
            mine: f.mine ? 1 : 0,
            new: f.new ? 1 : '',
        },
        { preserveState: true, replace: true },
    );
}
function take(id: number) {
    router.post(`/admin/quotes/${id}/take`);
}
</script>

<template>
    <Head title="Cotizaciones" />
    <section class="p-6">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p
                    class="font-semibold tracking-widest text-[#E30613] uppercase"
                >
                    Ventas
                </p>
                <h1 class="text-3xl font-bold">Cotizaciones</h1>
            </div>
            <Link
                href="/admin/quotes/create"
                class="rounded bg-[#E30613] px-4 py-2 font-semibold text-white"
                >Nueva cotización</Link
            >
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <input
                v-model="f.search"
                placeholder="Folio, cliente, teléfono o build"
                class="w-72 rounded-md border px-3 py-2"
                @keyup.enter="apply"
            />
            <select
                v-model="f.status"
                class="rounded-md border px-3 py-2"
                @change="apply"
            >
                <option value="">Todos los estados</option>
                <option
                    v-for="(label, key) in statuses"
                    :key="key"
                    :value="key"
                >
                    {{ label }}
                </option>
            </select>
            <select
                v-model="f.source"
                class="rounded-md border px-3 py-2"
                @change="apply"
            >
                <option value="">Todos los orígenes</option>
                <option v-for="(label, key) in sources" :key="key" :value="key">
                    {{ label }}
                </option>
            </select>
            <label class="flex items-center gap-2 text-sm"
                ><input v-model="f.mine" type="checkbox" @change="apply" /> Solo
                las mías</label
            >
            <label
                class="flex items-center gap-2 text-sm"
                :class="{ 'font-semibold text-[#E30613]': newCount }"
                ><input v-model="f.new" type="checkbox" @change="apply" />
                Nuevas sin atender ({{ newCount }})</label
            >
            <button class="rounded border px-3 py-2" @click="apply">
                Buscar
            </button>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-3">Folio</th>
                        <th class="p-3">Cliente</th>
                        <th class="p-3">Vendedor</th>
                        <th class="p-3">Origen</th>
                        <th class="p-3 text-right">Total</th>
                        <th class="p-3">Estado</th>
                        <th class="p-3">Fecha</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="q in quotes.data" :key="q.id" class="border-t">
                        <td class="p-3 font-semibold">
                            <Link
                                :href="`/admin/quotes/${q.id}/edit`"
                                class="underline"
                                >{{ q.folio }}</Link
                            >
                            <span
                                v-if="q.build_name"
                                class="block text-xs font-normal text-muted-foreground"
                                >{{ q.build_name }}</span
                            >
                        </td>
                        <td class="p-3">
                            {{ q.customer?.name
                            }}<span
                                class="block text-xs text-muted-foreground"
                                >{{ q.customer?.phone }}</span
                            >
                        </td>
                        <td class="p-3">{{ q.user?.name ?? '—' }}</td>
                        <td class="p-3">{{ sources[q.source] }}</td>
                        <td class="p-3 text-right tabular-nums">
                            {{ money(q.total) }}
                        </td>
                        <td class="p-3">
                            <span
                                class="rounded-full px-2 py-1 text-xs font-semibold"
                                :class="badge[q.display_status]"
                                >{{ statuses[q.display_status] }}</span
                            >
                        </td>
                        <td class="p-3 whitespace-nowrap">
                            {{
                                new Date(q.created_at).toLocaleDateString(
                                    'es-MX',
                                )
                            }}
                        </td>
                        <td class="p-3 text-right">
                            <button
                                v-if="!q.user_id"
                                class="rounded bg-[#E30613] px-3 py-1 text-white"
                                @click="take(q.id)"
                            >
                                Tomar
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!quotes.data.length">
                        <td
                            colspan="8"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No hay cotizaciones con esos filtros.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="quotes.last_page > 1" class="mt-4 flex flex-wrap gap-1">
            <template v-for="link in quotes.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded border px-3 py-1 text-sm"
                    :class="{ 'bg-[#E30613] text-white': link.active }"
                    preserve-scroll
                    ><span v-html="link.label"
                /></Link>
            </template>
        </div>
    </section>
</template>
