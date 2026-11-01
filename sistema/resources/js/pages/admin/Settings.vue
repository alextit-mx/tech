<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
const props = defineProps<{ settings: Record<string, string> }>();
const fields = [
    ['exchange_rate', 'Tipo de cambio USD → MXN'],
    ['quote_validity_days', 'Vigencia de cotización (días)'],
    ['business_name', 'Nombre comercial'],
    ['legal_name', 'Razón social'],
    ['rfc', 'RFC'],
    ['address', 'Domicilio'],
    ['whatsapp', 'WhatsApp'],
    ['email', 'Correo'],
    ['website', 'Sitio web'],
    ['bank_details', 'Datos de transferencia'],
    ['warranty_terms', 'Condiciones de garantía'],
    ['quote_terms', 'Condiciones de cotización'],
];
const form = useForm(
    Object.fromEntries(fields.map(([k]) => [k, props.settings[k] ?? ''])),
);
function save() {
    form.put('/admin/settings');
}
</script>
<template>
    <Head title="Configuración" />
    <section class="p-6">
        <h1 class="mb-5 text-3xl font-bold">Configuración</h1>
        <form
            class="grid max-w-4xl gap-4 md:grid-cols-2"
            @submit.prevent="save"
        >
            <label v-for="[key, label] in fields" class="grid gap-1"
                >{{ label
                }}<textarea
                    v-if="
                        [
                            'bank_details',
                            'warranty_terms',
                            'quote_terms',
                            'address',
                        ].includes(key)
                    "
                    v-model="form[key]"
                    rows="3"
                    class="rounded border p-2" /><input
                    v-else
                    v-model="form[key]"
                    class="rounded border p-2" /></label
            ><button class="rounded bg-[#E30613] p-2 text-white md:col-span-2">
                Guardar configuración
            </button>
        </form>
    </section>
</template>
