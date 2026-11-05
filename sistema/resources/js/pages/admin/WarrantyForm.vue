<script setup lang="ts">
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import CustomerPicker from '@/components/CustomerPicker.vue';
import type { PickedCustomer } from '@/components/CustomerPicker.vue';

const props = defineProps<{
    customer: PickedCustomer | null;
    items: { description: string; serial: string; months: number }[];
    source: { quote_id?: number; service_order_id?: number; label?: string };
    terms: string;
}>();

const customer = ref<PickedCustomer | null>(props.customer);
const form = useForm({
    customer_id: props.customer?.id ?? null,
    quote_id: props.source.quote_id ?? null,
    service_order_id: props.source.service_order_id ?? null,
    starts_at: new Date().toISOString().slice(0, 10),
    terms: props.terms,
    items: props.items.map((i) => ({ ...i })),
});

function save() {
    form.customer_id = customer.value?.id ?? null;
    form.post('/admin/warranties');
}
</script>

<template>
    <Head title="Nueva garantía" />
    <section class="max-w-4xl space-y-5 p-6">
        <div>
            <Link
                href="/admin/warranties"
                class="text-sm text-muted-foreground underline"
                >← Garantías</Link
            >
            <h1 class="text-3xl font-bold">Nueva garantía</h1>
            <p v-if="source.label" class="text-sm text-muted-foreground">
                Desde {{ source.label }}
            </p>
        </div>
        <form class="space-y-4" @submit.prevent="save">
            <div class="rounded-xl border p-4">
                <h2 class="mb-2 font-semibold">Cliente</h2>
                <CustomerPicker
                    v-model="customer"
                    :error="form.errors.customer_id"
                />
            </div>
            <div class="rounded-xl border">
                <div class="flex items-center justify-between border-b p-4">
                    <h2 class="font-semibold">Piezas cubiertas</h2>
                    <Button
                        variant="outline"
                        type="button"
                        class="rounded border px-3 py-1"
                        @click="
                            form.items.push({
                                description: '',
                                serial: '',
                                months: 12,
                            })
                        "
                    >
                        Agregar
                    </Button>
                </div>
                <div
                    v-for="(item, i) in form.items"
                    :key="i"
                    class="grid gap-2 border-b p-3 sm:grid-cols-[1fr_200px_90px_32px]"
                >
                    <Input
                        aria-label="Pieza o servicio"
                        v-model="item.description"
                        placeholder="Pieza o servicio"
                        class="rounded-md border px-3 py-2"
                    />
                    <Input
                        aria-label="Número de serie"
                        v-model="item.serial"
                        placeholder="Número de serie"
                        class="rounded-md border px-3 py-2"
                    />
                    <Input
                        v-model.number="item.months"
                        type="number"
                        min="1"
                        max="60"
                        title="Meses"
                        class="rounded-md border px-3 py-2"
                    />
                    <Button
                        variant="ghost"
                        type="button"
                        class="text-destructive"
                        title="Quitar"
                        @click="form.items.splice(i, 1)"
                    >
                        ×
                    </Button>
                </div>
                <p
                    v-if="form.errors.items"
                    class="p-3 text-sm text-destructive"
                >
                    {{ form.errors.items }}
                </p>
            </div>
            <div class="grid gap-4 md:grid-cols-[200px_1fr]">
                <label class="grid gap-1 text-sm"
                    >Inicio<Input
                        v-model="form.starts_at"
                        type="date"
                        class="rounded-md border px-3 py-2"
                /></label>
                <label class="grid gap-1 text-sm"
                    >Condiciones (salen en el PDF)<Textarea
                        v-model="form.terms"
                        rows="4"
                        class="rounded-md border px-3 py-2"
                    />
                </label>
            </div>
            <p
                v-if="Object.keys(form.errors).length"
                class="text-sm text-destructive"
            >
                Revisa los campos marcados.
            </p>
            <Button
                class="rounded bg-primary px-4 py-2 font-semibold text-primary-foreground"
                :disabled="form.processing"
            >
                Emitir garantía
            </Button>
        </form>
    </section>
</template>
