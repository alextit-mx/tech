<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    stats: {
        count: number;
        amount: number;
        accepted: number;
        acceptedAmount: number;
        new: number;
    };
    sellers: {
        id: number;
        name: string;
        month_count: number;
        month_accepted: number | null;
    }[];
    margin: { amount: number; percent: number } | null;
    recent: {
        id: number;
        folio: string;
        source: string;
        total: string;
        build_name: string | null;
        created_at: string;
        customer: { name: string } | null;
    }[];
}>();

const page = usePage<any>();
const isOwner = computed(() => page.props.auth.user.role === 'owner');
const money = (n: number | string | null) =>
    '$' + Number(n ?? 0).toLocaleString('es-MX', { maximumFractionDigits: 0 });
const sources: Record<string, string> = {
    admin: 'Mostrador',
    web: 'Tienda en línea',
    builder: 'Configurador',
};
const month = new Date().toLocaleDateString('es-MX', {
    month: 'long',
    year: 'numeric',
});

function take(id: number) {
    router.post(`/admin/quotes/${id}/take`);
}
</script>

<template>
    <Head title="Inicio" />
    <section class="space-y-6 p-6">
        <div>
            <p class="font-semibold tracking-widest text-[#E30613] uppercase">
                Tech Computer
            </p>
            <h1 class="text-3xl font-bold">Resumen de {{ month }}</h1>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border p-5">
                <p class="text-sm text-muted-foreground">
                    Cotizaciones del mes
                </p>
                <p class="mt-1 text-3xl font-bold tabular-nums">
                    {{ stats.count }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ money(stats.amount) }} cotizado
                </p>
            </div>
            <div class="rounded-xl border p-5">
                <p class="text-sm text-muted-foreground">Aceptadas</p>
                <p class="mt-1 text-3xl font-bold tabular-nums">
                    {{ stats.accepted }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ money(stats.acceptedAmount) }} vendido
                </p>
            </div>
            <Link
                href="/admin/quotes?new=1"
                class="rounded-xl border p-5 transition hover:border-[#E30613]"
                :class="{ 'border-[#E30613] bg-[#E30613]/5': stats.new }"
            >
                <p class="text-sm text-muted-foreground">Nuevas sin atender</p>
                <p
                    class="mt-1 text-3xl font-bold tabular-nums"
                    :class="{ 'text-[#E30613]': stats.new }"
                >
                    {{ stats.new }}
                </p>
                <p class="text-sm text-muted-foreground">
                    De la tienda y el configurador
                </p>
            </Link>
            <div v-if="isOwner" class="rounded-xl border p-5">
                <p class="text-sm text-muted-foreground">
                    Margen estimado (aceptadas)
                </p>
                <p class="mt-1 text-3xl font-bold tabular-nums">
                    {{ margin ? `${(margin.percent * 100).toFixed(1)}%` : '—' }}
                </p>
                <p class="text-sm text-muted-foreground">
                    {{
                        margin
                            ? `${money(margin.amount)} sin IVA`
                            : 'Sin ventas aceptadas'
                    }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-xl border">
                <h2 class="border-b p-4 font-semibold">
                    {{ isOwner ? 'Por vendedor' : 'Mis números del mes' }}
                </h2>
                <table class="w-full text-sm">
                    <thead class="text-left text-muted-foreground">
                        <tr>
                            <th class="p-3">Nombre</th>
                            <th class="p-3 text-right">Cotizaciones</th>
                            <th class="p-3 text-right">Vendido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in sellers" :key="s.id" class="border-t">
                            <td class="p-3">{{ s.name }}</td>
                            <td class="p-3 text-right tabular-nums">
                                {{ s.month_count }}
                            </td>
                            <td class="p-3 text-right tabular-nums">
                                {{ money(s.month_accepted) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rounded-xl border">
                <h2 class="border-b p-4 font-semibold">Nuevas de la web</h2>
                <p
                    v-if="!recent.length"
                    class="p-4 text-sm text-muted-foreground"
                >
                    No hay cotizaciones pendientes de atender.
                </p>
                <ul>
                    <li
                        v-for="q in recent"
                        :key="q.id"
                        class="flex items-center justify-between gap-3 border-t p-3 text-sm"
                    >
                        <div>
                            <p class="font-semibold">
                                {{ q.folio }} · {{ q.customer?.name }}
                            </p>
                            <p class="text-muted-foreground">
                                {{ sources[q.source]
                                }}{{
                                    q.build_name ? ` · ${q.build_name}` : ''
                                }}
                                · {{ money(q.total) }}
                            </p>
                        </div>
                        <button
                            class="rounded bg-[#E30613] px-3 py-1.5 font-semibold text-white"
                            @click="take(q.id)"
                        >
                            Tomar
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
