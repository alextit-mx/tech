<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    customers as customersRoute,
    quotes as quotesRoute,
} from '@/routes/admin';
import { store, update } from '@/routes/admin/customers';
import { create as createQuote } from '@/routes/admin/quotes';
import CustomerFields from '@/components/CustomerFields.vue';
import { emptyCustomer } from '@/lib/customers';
import type {
    CustomerData,
    PickedCustomer,
    SatCatalogs,
} from '@/lib/customers';

const props = defineProps<{
    customers: any;
    filters: { search?: string; kind?: string };
    sat: SatCatalogs;
}>();

const search = ref(props.filters.search ?? '');
const kind = ref(props.filters.kind ?? '');
const editing = ref<number | null>(null);
const form = useForm(emptyCustomer());

function apply() {
    router.get(
        customersRoute.url(),
        { search: search.value, kind: kind.value },
        { preserveState: true, replace: true },
    );
}
function edit(customer: PickedCustomer) {
    editing.value = customer.id;
    const values = Object.fromEntries(
        Object.entries(emptyCustomer()).map(([key, fallback]) => [
            key,
            customer[key as keyof PickedCustomer] ?? fallback,
        ]),
    ) as CustomerData;
    form.defaults(values);
    form.reset();
    form.clearErrors();
}
function startNew() {
    editing.value = 0;
    form.defaults(emptyCustomer());
    form.reset();
    form.clearErrors();
}
function save() {
    const done = {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    };
    if (editing.value) form.patch(update.url(editing.value), done);
    else form.post(store.url(), done);
}
</script>

<template>
    <Head title="Clientes" />
    <section class="p-6">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">Clientes</h1>
            </div>
            <div class="flex flex-wrap gap-2">
                <Input
                    aria-label="Nombre, empresa, RFC o WhatsApp"
                    v-model="search"
                    placeholder="Nombre, empresa, RFC o WhatsApp"
                    class="w-64 rounded-md border px-3 py-2"
                    @keyup.enter="apply"
                />
                <select
                    v-model="kind"
                    aria-label="Tipo de cliente"
                    class="h-10 rounded-md border bg-background px-3 text-sm"
                    @change="apply"
                >
                    <option value="">Todos los tipos</option>
                    <option value="mostrador">Mostrador</option>
                    <option value="registrado">Cliente con datos</option>
                </select>
                <Button
                    variant="outline"
                    class="rounded border px-3 py-2"
                    @click="apply"
                >
                    Buscar
                </Button>
                <Button
                    class="rounded bg-primary px-4 py-2 font-semibold text-primary-foreground"
                    @click="startNew"
                >
                    Nuevo cliente
                </Button>
            </div>
        </div>

        <form
            v-if="editing !== null"
            class="mb-6 max-w-4xl space-y-5 rounded-lg border p-5"
            @submit.prevent="save"
        >
            <h2 class="text-lg font-semibold">
                {{ editing ? 'Editar cliente' : 'Nuevo cliente' }}
            </h2>
            <CustomerFields
                :model-value="form"
                :sat="sat"
                :errors="form.errors"
            />
            <div class="flex gap-2">
                <Button :disabled="form.processing">Guardar cliente</Button>
                <Button variant="outline" type="button" @click="editing = null"
                    >Cancelar</Button
                >
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-3">Cliente</th>
                        <th class="p-3">Contacto</th>
                        <th class="p-3">RFC</th>
                        <th class="p-3">Tipo</th>
                        <th class="p-3">Teléfono</th>
                        <th class="p-3 text-right">Cotizaciones</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="c in customers.data"
                        :key="c.id"
                        class="border-t"
                    >
                        <td class="p-3 font-semibold">
                            {{ c.company || c.legal_name || c.name }}
                        </td>
                        <td class="p-3">
                            {{ c.name
                            }}<small
                                v-if="c.position"
                                class="block text-muted-foreground"
                                >{{ c.position }}</small
                            >
                        </td>
                        <td class="p-3">{{ c.rfc }}</td>
                        <td class="p-3">
                            <span class="rounded bg-muted px-2 py-1 text-xs">{{
                                c.kind === 'registrado'
                                    ? 'Cliente con datos'
                                    : 'Mostrador'
                            }}</span>
                        </td>
                        <td class="p-3">
                            <a
                                v-if="c.phone"
                                :href="`https://wa.me/52${c.phone}`"
                                target="_blank"
                                class="underline"
                                >{{ c.phone }}</a
                            >
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            <Link
                                :href="
                                    quotesRoute.url({
                                        query: {
                                            search: c.phone ?? c.name,
                                            mine: 0,
                                        },
                                    })
                                "
                                class="underline"
                                >{{ c.quotes_count }}</Link
                            >
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <Button
                                variant="ghost"
                                class="mr-3 underline"
                                @click="edit(c)"
                            >
                                Editar
                            </Button>
                            <Link
                                :href="
                                    createQuote.url({
                                        query: { customer_id: c.id },
                                    })
                                "
                                class="rounded bg-primary px-3 py-1 text-primary-foreground"
                                >Cotizar</Link
                            >
                        </td>
                    </tr>
                    <tr v-if="!customers.data.length">
                        <td
                            colspan="7"
                            class="p-6 text-center text-muted-foreground"
                        >
                            Sin clientes todavía.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="customers.last_page > 1" class="mt-4 flex flex-wrap gap-1">
            <template v-for="link in customers.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded border px-3 py-1 text-sm"
                    :class="{
                        'bg-primary text-primary-foreground': link.active,
                    }"
                    ><span v-html="link.label"
                /></Link>
            </template>
        </div>
    </section>
</template>
