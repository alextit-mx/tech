<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{ warranties: any; filters: { search?: string } }>();

const search = ref(props.filters.search ?? '');
const labels: Record<string, string> = {
    active: 'Vigente',
    expired: 'Vencida',
    void: 'Anulada',
};
const colors: Record<string, string> = {
    active: 'bg-green-500/15 text-green-700 dark:text-green-300',
    expired: 'bg-zinc-500/15',
    void: 'bg-red-500/15 text-red-700 dark:text-red-300',
};

function apply() {
    router.get(
        '/admin/warranties',
        { search: search.value },
        { preserveState: true, replace: true },
    );
}
function voidIt(id: number) {
    const reason = window.prompt('Motivo para anular la garantía');
    if (reason)
        router.post(
            `/admin/warranties/${id}/void`,
            { void_reason: reason },
            { preserveScroll: true },
        );
}
</script>

<template>
    <Head title="Garantías" />
    <section class="space-y-5 p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold">Garantías</h1>
            </div>
            <div class="flex gap-2">
                <Input
                    aria-label="Folio, serie, cliente o teléfono"
                    v-model="search"
                    placeholder="Folio, serie, cliente o teléfono"
                    class="w-64 rounded-md border px-3 py-2"
                    @keyup.enter="apply"
                />
                <Link
                    href="/admin/warranties/create"
                    class="rounded bg-primary px-4 py-2 font-semibold text-primary-foreground"
                    >Nueva garantía</Link
                >
            </div>
        </div>
        <p class="text-sm text-muted-foreground">
            Tip: desde una cotización aceptada o una orden entregada se crea con
            las piezas ya capturadas.
        </p>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-3">Folio</th>
                        <th class="p-3">Cliente</th>
                        <th class="p-3">Piezas</th>
                        <th class="p-3">Inicio</th>
                        <th class="p-3">Estado</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="w in warranties.data"
                        :key="w.id"
                        class="border-t align-top"
                    >
                        <td class="p-3 font-semibold">{{ w.folio }}</td>
                        <td class="p-3">
                            {{ w.customer?.name
                            }}<span
                                class="block text-xs text-muted-foreground"
                                >{{ w.customer?.phone }}</span
                            >
                        </td>
                        <td class="p-3">
                            <p v-for="i in w.items" :key="i.id">
                                {{ i.description }}
                                <span class="text-xs text-muted-foreground"
                                    >{{ i.serial ? `· ${i.serial}` : '' }} ·
                                    {{ i.months }} m · vence
                                    {{
                                        new Date(
                                            `${i.ends_at}T12:00`,
                                        ).toLocaleDateString('es-MX')
                                    }}</span
                                >
                            </p>
                        </td>
                        <td class="p-3 whitespace-nowrap">
                            {{
                                new Date(
                                    `${w.starts_at.slice(0, 10)}T12:00`,
                                ).toLocaleDateString('es-MX')
                            }}
                        </td>
                        <td class="p-3">
                            <span
                                class="rounded-full px-2 py-1 text-xs font-semibold"
                                :class="colors[w.status]"
                                >{{ labels[w.status] }}</span
                            >
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <a
                                :href="`/admin/warranties/${w.id}/pdf`"
                                target="_blank"
                                class="mr-3 underline"
                                >PDF</a
                            >
                            <Button
                                variant="ghost"
                                v-if="w.status !== 'void'"
                                class="text-destructive underline"
                                @click="voidIt(w.id)"
                            >
                                Anular
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!warranties.data.length">
                        <td
                            colspan="6"
                            class="p-6 text-center text-muted-foreground"
                        >
                            Sin garantías todavía.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
