<script setup lang="ts">
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import LogoSetting from '@/components/LogoSetting.vue';
import { Head, useForm } from '@inertiajs/vue3';
type Logo = { url: string; custom: boolean };
const props = defineProps<{
    settings: Record<string, string>;
    logos: Record<'site' | 'pdf' | 'icon', Logo>;
}>();
const logoCards = [
    {
        type: 'site',
        title: 'Logo del sitio',
        usage: 'Encabezado y pie de la tienda, y pantalla de inicio de sesión.',
        hint: 'PNG con fondo transparente, de 300 px de ancho o más. Máximo 2 MB.',
    },
    {
        type: 'pdf',
        title: 'Logo de cotizaciones',
        usage: 'Cotizaciones, órdenes de servicio y garantías en PDF.',
        hint: 'PNG o JPG que se lea sobre fondo blanco, de 300 px de ancho o más. Máximo 2 MB.',
    },
    {
        type: 'icon',
        title: 'Ícono',
        usage: 'Pestaña del navegador, acceso directo en celular y emblema del panel.',
        hint: 'Imagen cuadrada de 256 × 256 px o más, con fondo transparente. Máximo 2 MB.',
    },
] as const;
const fields = [
    ['exchange_rate', 'Tipo de cambio USD → MXN'],
    ['quote_validity_days', 'Vigencia de cotización (días)'],
    ['business_name', 'Nombre comercial'],
    ['location_name', 'Nombre de la sucursal / plaza'],
    ['phone', 'Teléfono'],
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
        <section class="mb-8 max-w-5xl" aria-labelledby="logos-title">
            <h2 id="logos-title" class="text-lg font-semibold">Logotipos</h2>
            <p class="mb-3 text-sm text-muted-foreground">
                Se aplican al momento en el sitio, el panel y los PDF. Formatos:
                PNG, JPG o WebP (no se aceptan SVG).
            </p>
            <div class="grid gap-3 md:grid-cols-3">
                <LogoSetting
                    v-for="card in logoCards"
                    :key="card.type"
                    v-bind="card"
                    :url="props.logos[card.type].url"
                    :custom="props.logos[card.type].custom"
                />
            </div>
        </section>
        <h2 class="mb-3 text-lg font-semibold">Datos del negocio</h2>
        <form
            class="grid max-w-4xl gap-4 md:grid-cols-2"
            @submit.prevent="save"
        >
            <label v-for="[key, label] in fields" class="grid gap-1"
                >{{ label
                }}<Textarea
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
                    class="rounded border p-2"
                /><Input
                    v-else
                    v-model="form[key]"
                    class="rounded border p-2"
                /><small
                    v-if="form.errors[key]"
                    class="text-sm text-destructive"
                    >{{ form.errors[key] }}</small
                ></label
            ><Button
                class="rounded bg-primary p-2 text-primary-foreground md:col-span-2"
            >
                Guardar configuración
            </Button>
        </form>
    </section>
</template>
