<script setup lang="ts">
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import CustomerPicker from '@/components/CustomerPicker.vue';
import type { PickedCustomer } from '@/components/CustomerPicker.vue';

const props = defineProps<{
    orders: any;
    filters: { status: string; search?: string };
    statuses: Record<string, string>;
    devices: Record<string, string>;
    counts: Record<string, number>;
}>();

const search = ref(props.filters.search ?? '');
const showForm = ref(false);
const customer = ref<PickedCustomer | null>(null);
const newCustomer = ref(false);
const form = useForm({
    customer_id: null as number | null,
    customer_name: '',
    customer_phone: '',
    device_type: 'desktop',
    brand: '',
    model: '',
    serial: '',
    accessories: '',
    reported_issue: '',
    estimate: null as number | null,
    promised_at: '',
});

function filter(status: string) {
    router.get(
        '/admin/service',
        { status, search: search.value },
        { preserveState: true, replace: true },
    );
}
function save() {
    form.customer_id = newCustomer.value ? null : (customer.value?.id ?? null);
    form.post('/admin/service');
}
</script>

<template>
    <Head title="Servicio técnico" />
    <section class="space-y-5 p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold">Órdenes de servicio</h1>
            </div>
            <Button
                class="rounded bg-primary px-4 py-2 font-semibold text-primary-foreground"
                @click="showForm = !showForm"
            >
                {{ showForm ? 'Cerrar' : 'Recibir equipo' }}
            </Button>
        </div>

        <form
            v-if="showForm"
            class="grid gap-3 rounded-xl border p-4 md:grid-cols-3"
            @submit.prevent="save"
        >
            <div class="md:col-span-3">
                <label class="mb-1 flex items-center gap-2 text-sm"
                    ><input v-model="newCustomer" type="checkbox" /> Cliente
                    nuevo (solo nombre y WhatsApp)</label
                >
                <div v-if="newCustomer" class="grid gap-2 sm:grid-cols-2">
                    <Input
                        aria-label="Nombre del cliente"
                        v-model="form.customer_name"
                        placeholder="Nombre del cliente"
                        class="rounded-md border px-3 py-2"
                    />
                    <Input
                        aria-label="WhatsApp (10 dígitos)"
                        v-model="form.customer_phone"
                        placeholder="WhatsApp (10 dígitos)"
                        class="rounded-md border px-3 py-2"
                    />
                </div>
                <CustomerPicker
                    v-else
                    v-model="customer"
                    :error="form.errors.customer_id"
                />
                <p
                    v-if="
                        form.errors.customer_name || form.errors.customer_phone
                    "
                    class="text-sm text-destructive"
                >
                    {{
                        form.errors.customer_name || form.errors.customer_phone
                    }}
                </p>
            </div>
            <label class="grid gap-1 text-sm"
                >Tipo de equipo
                <select
                    v-model="form.device_type"
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
                    v-model="form.brand"
                    class="rounded-md border px-3 py-2"
            /></label>
            <label class="grid gap-1 text-sm"
                >Modelo<Input
                    v-model="form.model"
                    class="rounded-md border px-3 py-2"
            /></label>
            <label class="grid gap-1 text-sm"
                >Número de serie<Input
                    v-model="form.serial"
                    class="rounded-md border px-3 py-2"
            /></label>
            <label class="grid gap-1 text-sm md:col-span-2"
                >Accesorios que deja<Input
                    aria-label="Cargador, funda, USB…"
                    v-model="form.accessories"
                    placeholder="Cargador, funda, USB…"
                    class="rounded-md border px-3 py-2"
            /></label>
            <label class="grid gap-1 text-sm md:col-span-3"
                >Falla reportada<Textarea
                    v-model="form.reported_issue"
                    rows="2"
                    class="rounded-md border px-3 py-2"
                /><small class="text-destructive">{{
                    form.errors.reported_issue
                }}</small></label
            >
            <label class="grid gap-1 text-sm"
                >Presupuesto (opcional)<Input
                    v-model.number="form.estimate"
                    type="number"
                    min="0"
                    step="0.01"
                    class="rounded-md border px-3 py-2"
            /></label>
            <label class="grid gap-1 text-sm"
                >Fecha estimada<Input
                    v-model="form.promised_at"
                    type="date"
                    class="rounded-md border px-3 py-2"
            /></label>
            <div class="flex items-end">
                <Button
                    class="w-full rounded bg-primary px-4 py-2 font-semibold text-primary-foreground"
                    :disabled="form.processing"
                >
                    Registrar e imprimir
                </Button>
            </div>
        </form>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                variant="outline"
                class="rounded-full border px-3 py-1 text-sm"
                :class="{
                    'border-input bg-accent': filters.status === 'open',
                }"
                @click="filter('open')"
            >
                En el local
            </Button>
            <Button
                variant="outline"
                v-for="(label, key) in statuses"
                :key="key"
                class="rounded-full border px-3 py-1 text-sm"
                :class="{
                    'border-input bg-accent': filters.status === key,
                }"
                @click="filter(key)"
            >
                {{ label }} <b v-if="counts[key]">{{ counts[key] }}</b>
            </Button>
            <Button
                variant="outline"
                class="rounded-full border px-3 py-1 text-sm"
                :class="{
                    'border-input bg-accent': filters.status === 'all',
                }"
                @click="filter('all')"
            >
                Todas
            </Button>
            <Input
                aria-label="Folio, serie, cliente o teléfono"
                v-model="search"
                placeholder="Folio, serie, cliente o teléfono"
                class="ml-auto w-64 rounded-md border px-3 py-2"
                @keyup.enter="filter(filters.status)"
            />
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-3">Folio</th>
                        <th class="p-3">Cliente</th>
                        <th class="p-3">Equipo</th>
                        <th class="p-3">Estado</th>
                        <th class="p-3">Recibido</th>
                        <th class="p-3">Promesa</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="o in orders.data" :key="o.id" class="border-t">
                        <td class="p-3 font-semibold">
                            <Link
                                :href="`/admin/service/${o.id}`"
                                class="underline"
                                >{{ o.folio }}</Link
                            >
                        </td>
                        <td class="p-3">
                            {{ o.customer?.name
                            }}<span
                                class="block text-xs text-muted-foreground"
                                >{{ o.customer?.phone }}</span
                            >
                        </td>
                        <td class="p-3">
                            {{ devices[o.device_type] }} {{ o.brand }}
                            {{ o.model }}
                        </td>
                        <td class="p-3">
                            <span
                                class="rounded-full bg-muted px-2 py-1 text-xs font-semibold"
                                >{{ statuses[o.status] }}</span
                            >
                        </td>
                        <td class="p-3 whitespace-nowrap">
                            {{
                                new Date(o.received_at).toLocaleDateString(
                                    'es-MX',
                                )
                            }}
                        </td>
                        <td class="p-3 whitespace-nowrap">
                            {{
                                o.promised_at
                                    ? new Date(
                                          `${o.promised_at.slice(0, 10)}T12:00`,
                                      ).toLocaleDateString('es-MX')
                                    : '—'
                            }}
                        </td>
                    </tr>
                    <tr v-if="!orders.data.length">
                        <td
                            colspan="6"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No hay órdenes con ese filtro.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
