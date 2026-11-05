<script setup lang="ts">
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    order: any;
    statuses: Record<string, string>;
    devices: Record<string, string>;
    links: { tracking: string; whatsapp: string | null };
    warranty: number | null;
}>();

const details = useForm({
    device_type: props.order.device_type,
    brand: props.order.brand ?? '',
    model: props.order.model ?? '',
    serial: props.order.serial ?? '',
    accessories: props.order.accessories ?? '',
    reported_issue: props.order.reported_issue,
    diagnosis: props.order.diagnosis ?? '',
    work_done: props.order.work_done ?? '',
    estimate: props.order.estimate,
    final_cost: props.order.final_cost,
    promised_at: props.order.promised_at?.slice(0, 10) ?? '',
});
const event = useForm({ status: '', note: '', is_public: true });

function saveDetails() {
    details.put(`/admin/service/${props.order.id}`, { preserveScroll: true });
}
function addEvent() {
    event
        .transform((d) => ({ ...d, status: d.status || null }))
        .post(`/admin/service/${props.order.id}/events`, {
            preserveScroll: true,
            onSuccess: () => event.reset(),
        });
}
</script>

<template>
    <Head :title="order.folio" />
    <section class="space-y-6 p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <Link
                    href="/admin/service"
                    class="text-sm text-muted-foreground underline"
                    >← Servicio técnico</Link
                >
                <h1 class="text-3xl font-bold">
                    {{ order.folio }} · {{ statuses[order.status] }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ order.customer.name }} · {{ order.customer.phone }} ·
                    recibió {{ order.user?.name ?? '—' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a
                    :href="`/admin/service/${order.id}/pdf`"
                    target="_blank"
                    class="rounded border px-3 py-2"
                    >Orden de recepción (PDF)</a
                >
                <a
                    v-if="links.whatsapp"
                    :href="links.whatsapp"
                    target="_blank"
                    rel="noopener"
                    class="rounded bg-primary px-3 py-2 font-semibold text-primary-foreground"
                    >Avisar por WhatsApp</a
                >
                <Link
                    v-if="order.status === 'delivered' && !warranty"
                    :href="`/admin/warranties/create?order=${order.id}`"
                    class="rounded border px-3 py-2"
                    >Emitir garantía</Link
                >
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <form
                class="grid gap-3 rounded-xl border p-4 md:grid-cols-3"
                @submit.prevent="saveDetails"
            >
                <label class="grid gap-1 text-sm"
                    >Tipo
                    <select
                        v-model="details.device_type"
                        class="rounded-md border px-3 py-2"
                    >
                        <option
                            v-for="(label, key) in devices"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </option>
                    </select>
                </label>
                <label class="grid gap-1 text-sm"
                    >Marca<Input
                        v-model="details.brand"
                        class="rounded-md border px-3 py-2"
                /></label>
                <label class="grid gap-1 text-sm"
                    >Modelo<Input
                        v-model="details.model"
                        class="rounded-md border px-3 py-2"
                /></label>
                <label class="grid gap-1 text-sm"
                    >Serie<Input
                        v-model="details.serial"
                        class="rounded-md border px-3 py-2"
                /></label>
                <label class="grid gap-1 text-sm md:col-span-2"
                    >Accesorios<Input
                        v-model="details.accessories"
                        class="rounded-md border px-3 py-2"
                /></label>
                <label class="grid gap-1 text-sm md:col-span-3"
                    >Falla reportada<Textarea
                        v-model="details.reported_issue"
                        rows="2"
                        class="rounded-md border px-3 py-2"
                    />
                </label>
                <label class="grid gap-1 text-sm md:col-span-3"
                    >Diagnóstico<Textarea
                        v-model="details.diagnosis"
                        rows="3"
                        class="rounded-md border px-3 py-2"
                    />
                </label>
                <label class="grid gap-1 text-sm md:col-span-3"
                    >Trabajo realizado<Textarea
                        v-model="details.work_done"
                        rows="3"
                        class="rounded-md border px-3 py-2"
                    />
                </label>
                <label class="grid gap-1 text-sm"
                    >Presupuesto<Input
                        v-model.number="details.estimate"
                        type="number"
                        min="0"
                        step="0.01"
                        class="rounded-md border px-3 py-2"
                /></label>
                <label class="grid gap-1 text-sm"
                    >Costo final<Input
                        v-model.number="details.final_cost"
                        type="number"
                        min="0"
                        step="0.01"
                        class="rounded-md border px-3 py-2"
                /></label>
                <label class="grid gap-1 text-sm"
                    >Fecha estimada<Input
                        v-model="details.promised_at"
                        type="date"
                        class="rounded-md border px-3 py-2"
                /></label>
                <div class="md:col-span-3">
                    <Button
                        class="rounded bg-primary px-4 py-2 font-semibold text-primary-foreground"
                        :disabled="details.processing"
                    >
                        Guardar datos
                    </Button>
                </div>
            </form>

            <div class="space-y-4">
                <form
                    class="space-y-2 rounded-xl border p-4"
                    @submit.prevent="addEvent"
                >
                    <h2 class="font-semibold">Actualizar</h2>
                    <select
                        v-model="event.status"
                        class="w-full rounded-md border px-3 py-2"
                    >
                        <option value="">Solo agregar nota</option>
                        <option
                            v-for="(label, key) in statuses"
                            :key="key"
                            :value="key"
                        >
                            Cambiar a: {{ label }}
                        </option>
                    </select>
                    <Textarea
                        v-model="event.note"
                        rows="2"
                        placeholder="Nota"
                        class="w-full rounded-md border px-3 py-2"
                    />
                    <label class="flex items-center gap-2 text-sm"
                        ><input v-model="event.is_public" type="checkbox" /> El
                        cliente la ve en /estado</label
                    >
                    <p
                        v-if="event.errors.note"
                        class="text-sm text-destructive"
                    >
                        {{ event.errors.note }}
                    </p>
                    <Button
                        class="w-full rounded bg-primary px-4 py-2 font-semibold text-primary-foreground"
                        :disabled="event.processing"
                    >
                        Guardar
                    </Button>
                </form>

                <ol class="space-y-3 rounded-xl border p-4">
                    <li
                        v-for="e in order.events"
                        :key="e.id"
                        class="border-l-2 pl-3"
                        :class="
                            e.is_public
                                ? 'border-input'
                                : 'border-dashed border-muted-foreground'
                        "
                    >
                        <p class="text-xs text-muted-foreground">
                            {{ new Date(e.created_at).toLocaleString('es-MX') }}
                            · {{ e.user?.name ?? 'Sistema' }} ·
                            {{ e.is_public ? 'pública' : 'interna' }}
                        </p>
                        <p v-if="e.status" class="font-semibold">
                            {{ statuses[e.status] }}
                        </p>
                        <p v-if="e.note" class="text-sm">{{ e.note }}</p>
                    </li>
                </ol>
            </div>
        </div>
    </section>
</template>
