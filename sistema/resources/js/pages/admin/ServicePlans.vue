<script setup lang="ts">
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import CustomerPicker from '@/components/CustomerPicker.vue';
import type { PickedCustomer } from '@/components/CustomerPicker.vue';

defineProps<{ plans: any }>();

const showForm = ref(false);
const customer = ref<PickedCustomer | null>(null);
const today = new Date().toISOString().slice(0, 10);
const nextYear = new Date(Date.now() + 365 * 864e5).toISOString().slice(0, 10);
const form = useForm({
    customer_id: null as number | null,
    name: 'Póliza anual de mantenimiento',
    visits_included: 4,
    price: null as number | null,
    starts_at: today,
    ends_at: nextYear,
    notes: '',
});
const visit = useForm({ visited_at: today, device: '', work: '' });
const visitFor = ref<number | null>(null);

function save() {
    form.customer_id = customer.value?.id ?? null;
    form.post('/admin/plans', {
        preserveScroll: true,
        onSuccess: () => (showForm.value = false),
    });
}
function saveVisit(planId: number) {
    visit.post(`/admin/plans/${planId}/visits`, {
        preserveScroll: true,
        onSuccess: () => {
            visit.reset();
            visitFor.value = null;
        },
    });
}
</script>

<template>
    <Head title="Pólizas de mantenimiento" />
    <section class="space-y-5 p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold">Pólizas de mantenimiento</h1>
            </div>
            <Button
                class="rounded bg-primary px-4 py-2 font-semibold text-primary-foreground"
                @click="showForm = !showForm"
            >
                {{ showForm ? 'Cerrar' : 'Nueva póliza' }}
            </Button>
        </div>

        <form
            v-if="showForm"
            class="grid gap-3 rounded-xl border p-4 md:grid-cols-3"
            @submit.prevent="save"
        >
            <div class="md:col-span-3">
                <CustomerPicker
                    v-model="customer"
                    :error="form.errors.customer_id"
                />
            </div>
            <label class="grid gap-1 text-sm md:col-span-2"
                >Nombre del plan<Input
                    v-model="form.name"
                    class="rounded-md border px-3 py-2"
            /></label>
            <label class="grid gap-1 text-sm"
                >Visitas incluidas<Input
                    v-model.number="form.visits_included"
                    type="number"
                    min="1"
                    class="rounded-md border px-3 py-2"
            /></label>
            <label class="grid gap-1 text-sm"
                >Precio con IVA<Input
                    v-model.number="form.price"
                    type="number"
                    min="0"
                    step="0.01"
                    class="rounded-md border px-3 py-2"
            /></label>
            <label class="grid gap-1 text-sm"
                >Inicio<Input
                    v-model="form.starts_at"
                    type="date"
                    class="rounded-md border px-3 py-2"
            /></label>
            <label class="grid gap-1 text-sm"
                >Fin<Input
                    v-model="form.ends_at"
                    type="date"
                    class="rounded-md border px-3 py-2"
                /><small class="text-destructive">{{
                    form.errors.ends_at
                }}</small></label
            >
            <label class="grid gap-1 text-sm md:col-span-3"
                >Notas (equipos cubiertos, condiciones)<Textarea
                    v-model="form.notes"
                    rows="2"
                    class="rounded-md border px-3 py-2"
                />
            </label>
            <div>
                <Button
                    class="rounded bg-primary px-4 py-2 font-semibold text-primary-foreground"
                    :disabled="form.processing"
                >
                    Crear póliza
                </Button>
            </div>
        </form>

        <div class="grid gap-4 lg:grid-cols-2">
            <article
                v-for="p in plans.data"
                :key="p.id"
                class="space-y-3 rounded-xl border p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-semibold tracking-widest text-muted-foreground"
                        >
                            {{ p.folio }}
                        </p>
                        <h2 class="text-lg font-bold">
                            {{ p.customer?.name }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ p.name }} ·
                            {{
                                new Date(
                                    `${p.starts_at.slice(0, 10)}T12:00`,
                                ).toLocaleDateString('es-MX')
                            }}
                            al
                            {{
                                new Date(
                                    `${p.ends_at.slice(0, 10)}T12:00`,
                                ).toLocaleDateString('es-MX')
                            }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-bold tabular-nums">
                            {{ p.remaining
                            }}<span class="text-sm text-muted-foreground"
                                >/{{ p.visits_included }}</span
                            >
                        </p>
                        <p
                            class="text-xs"
                            :class="
                                p.active
                                    ? 'text-green-700 dark:text-green-300'
                                    : 'text-destructive'
                            "
                        >
                            {{ p.active ? 'visitas restantes' : 'vencida' }}
                        </p>
                    </div>
                </div>
                <ul class="space-y-1 text-sm">
                    <li
                        v-for="v in p.visits"
                        :key="v.id"
                        class="border-l-2 border-input pl-2"
                    >
                        <b>{{
                            new Date(
                                `${v.visited_at.slice(0, 10)}T12:00`,
                            ).toLocaleDateString('es-MX')
                        }}</b>
                        {{ v.device ? `· ${v.device}` : '' }} — {{ v.work }}
                        <span class="text-xs text-muted-foreground"
                            >({{ v.user?.name }})</span
                        >
                    </li>
                </ul>
                <Button
                    variant="outline"
                    v-if="visitFor !== p.id && p.remaining > 0 && p.active"
                    class="rounded border px-3 py-1 text-sm"
                    @click="visitFor = p.id"
                >
                    Registrar visita
                </Button>
                <form
                    v-if="visitFor === p.id"
                    class="grid gap-2 sm:grid-cols-[150px_1fr]"
                    @submit.prevent="saveVisit(p.id)"
                >
                    <Input
                        v-model="visit.visited_at"
                        type="date"
                        class="rounded-md border px-3 py-2"
                    />
                    <Input
                        aria-label="Equipo"
                        v-model="visit.device"
                        placeholder="Equipo"
                        class="rounded-md border px-3 py-2"
                    />
                    <Textarea
                        v-model="visit.work"
                        rows="2"
                        placeholder="Trabajo realizado"
                        class="rounded-md border px-3 py-2 sm:col-span-2"
                    />
                    <p
                        v-if="visit.errors.work"
                        class="text-sm text-destructive sm:col-span-2"
                    >
                        {{ visit.errors.work }}
                    </p>
                    <div class="flex gap-2 sm:col-span-2">
                        <Button
                            class="rounded bg-primary px-3 py-1.5 text-primary-foreground"
                            :disabled="visit.processing"
                        >
                            Guardar visita
                        </Button>
                        <Button
                            variant="outline"
                            type="button"
                            class="rounded border px-3 py-1.5"
                            @click="visitFor = null"
                        >
                            Cancelar
                        </Button>
                    </div>
                </form>
            </article>
            <p v-if="!plans.data.length" class="text-muted-foreground">
                Sin pólizas todavía.
            </p>
        </div>
    </section>
</template>
