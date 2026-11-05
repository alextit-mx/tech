<script setup lang="ts">
import { Button } from '@/components/ui/button';
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
    service: { statuses: Record<string, string>; open: Record<string, number> };
    expiring: {
        warranties: { folio: string; customer: string; ends_at: string }[];
        plans: { folio: string; customer: string; ends_at: string }[];
    };
}>();

const shortDate = (d: string) =>
    new Date(`${d}T12:00`).toLocaleDateString('es-MX', {
        day: 'numeric',
        month: 'short',
    });

const page = usePage<any>();
const isOwner = computed(() => page.props.auth.user.role === 'owner');
const money = (n: number | string | null) =>
    '$' +
    Number(n ?? 0).toLocaleString('es-MX', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
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
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1>Inicio</h1>
                <p class="text-sm text-muted-foreground">
                    Resumen de {{ month }}
                </p>
            </div>
            <Button as-child
                ><Link href="/admin/quotes/create"
                    >Nueva cotización</Link
                ></Button
            >
        </div>

        <div class="dashboard-stats">
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
                class="rounded-xl border p-5 transition hover:border-input"
                :class="{ 'border-input bg-accent': stats.new }"
            >
                <p class="text-sm text-muted-foreground">Nuevas sin atender</p>
                <p
                    class="mt-1 text-3xl font-bold tabular-nums"
                    :class="{ 'text-[var(--red)]': stats.new }"
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

        <div class="dashboard-panels grid gap-6 xl:grid-cols-2">
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
                        <Button
                            class="rounded bg-primary px-3 py-1.5 font-semibold text-primary-foreground"
                            @click="take(q.id)"
                        >
                            Tomar
                        </Button>
                    </li>
                </ul>
            </div>
        </div>

        <div class="dashboard-panels grid gap-6 xl:grid-cols-2">
            <div class="rounded-xl border">
                <h2 class="flex justify-between border-b p-4 font-semibold">
                    Equipos en el local
                    <Link
                        href="/admin/service"
                        class="text-sm font-normal underline"
                        >Ver órdenes</Link
                    >
                </h2>
                <p
                    v-if="!Object.keys(service.open).length"
                    class="p-4 text-sm text-muted-foreground"
                >
                    No hay equipos en servicio.
                </p>
                <div class="flex flex-wrap gap-3 p-4">
                    <Link
                        v-for="(total, status) in service.open"
                        :key="status"
                        :href="`/admin/service?status=${status}`"
                        class="rounded-lg border px-4 py-3 hover:border-input"
                        :class="{
                            'border-input bg-accent': status === 'ready',
                        }"
                    >
                        <p class="text-2xl font-bold tabular-nums">
                            {{ total }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ service.statuses[status] }}
                        </p>
                    </Link>
                </div>
            </div>
            <div class="rounded-xl border">
                <h2 class="border-b p-4 font-semibold">Vencen en 30 días</h2>
                <p
                    v-if="!expiring.warranties.length && !expiring.plans.length"
                    class="p-4 text-sm text-muted-foreground"
                >
                    Nada por vencer.
                </p>
                <ul class="text-sm">
                    <li
                        v-for="w in expiring.warranties"
                        :key="w.folio"
                        class="flex justify-between border-t p-3"
                    >
                        <span>Garantía {{ w.folio }} · {{ w.customer }}</span
                        ><span class="text-muted-foreground">{{
                            shortDate(w.ends_at)
                        }}</span>
                    </li>
                    <li
                        v-for="p in expiring.plans"
                        :key="p.folio"
                        class="flex justify-between border-t p-3"
                    >
                        <span>Póliza {{ p.folio }} · {{ p.customer }}</span
                        ><span class="text-muted-foreground">{{
                            shortDate(p.ends_at)
                        }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
