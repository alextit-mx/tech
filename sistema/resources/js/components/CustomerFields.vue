<script setup lang="ts">
import { computed, useId } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { CustomerData, SatCatalogs } from '@/lib/customers';

const model = defineModel<CustomerData>({ required: true });
defineProps<{
    sat: SatCatalogs;
    errors?: Partial<Record<keyof CustomerData, string>>;
}>();
const id = useId();
const registered = computed(() => model.value.kind === 'registrado');
const person = computed(() => {
    const length = model.value.rfc.trim().length;
    return length === 12 ? 'moral' : length === 13 ? 'physical' : null;
});
type Field = {
    key: keyof CustomerData;
    label: string;
    type?: string;
    required?: boolean;
    max?: number;
};
const contact: Field[] = [
    { key: 'name', label: 'Nombre del contacto', required: true, max: 120 },
    { key: 'company', label: 'Empresa / nombre comercial', max: 255 },
    { key: 'position', label: 'Puesto', max: 255 },
    { key: 'phone', label: 'WhatsApp (10 dígitos)', type: 'tel', max: 30 },
    { key: 'email', label: 'Correo', type: 'email', max: 120 },
];
const fiscal: Field[] = [
    { key: 'rfc', label: 'RFC', required: true, max: 13 },
    { key: 'legal_name', label: 'Razón social', required: true, max: 255 },
    { key: 'tax_regime', label: 'Régimen fiscal', required: true },
    { key: 'cfdi_use', label: 'Uso de CFDI' },
    { key: 'tax_zip', label: 'CP fiscal', required: true, max: 5 },
];
const address: Field[] = [
    { key: 'street', label: 'Calle' },
    { key: 'ext_number', label: 'Número exterior' },
    { key: 'int_number', label: 'Número interior' },
    { key: 'neighborhood', label: 'Colonia' },
    { key: 'city', label: 'Municipio / alcaldía' },
    { key: 'state', label: 'Estado' },
    { key: 'zip', label: 'Código postal', max: 5 },
    { key: 'address_notes', label: 'Referencias de la dirección' },
];
const sections = computed(() => [
    {
        title: 'Contacto',
        fields: contact.filter(
            (field) =>
                registered.value ||
                !['company', 'position'].includes(field.key),
        ),
    },
    ...(registered.value
        ? [
              { title: 'Datos fiscales', fields: fiscal },
              { title: 'Dirección', fields: address },
          ]
        : []),
]);
</script>

<template>
    <div class="space-y-6">
        <div class="grid max-w-sm gap-1 text-sm">
            <label :for="`${id}-kind`">Tipo de cliente</label>
            <select
                :id="`${id}-kind`"
                v-model="model.kind"
                class="h-10 rounded-md border bg-background px-3"
            >
                <option value="mostrador">Mostrador</option>
                <option value="registrado">Cliente con datos</option>
            </select>
            <small v-if="errors?.kind" class="text-destructive">{{
                errors.kind
            }}</small>
        </div>
        <fieldset
            v-for="section in sections"
            :key="section.title"
            class="min-w-0 space-y-3"
        >
            <legend class="mb-3 font-semibold">{{ section.title }}</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div
                    v-for="field in section.fields"
                    :key="field.key"
                    class="min-w-0 space-y-1"
                >
                    <label :for="`${id}-${field.key}`" class="text-sm"
                        >{{ field.label
                        }}{{ field.required ? ' *' : '' }}</label
                    >
                    <select
                        v-if="field.key === 'tax_regime'"
                        :id="`${id}-${field.key}`"
                        v-model="model.tax_regime"
                        required
                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                        :aria-invalid="!!errors?.tax_regime"
                        :aria-describedby="
                            errors?.tax_regime
                                ? `${id}-${field.key}-error`
                                : undefined
                        "
                    >
                        <option value="">Selecciona un régimen</option>
                        <template
                            v-for="(regime, key) in sat.regimes"
                            :key="key"
                        >
                            <option
                                v-if="
                                    !person || regime.persons.includes(person)
                                "
                                :value="String(key)"
                            >
                                {{ key }} · {{ regime.name }}
                            </option>
                        </template>
                    </select>
                    <select
                        v-else-if="field.key === 'cfdi_use'"
                        :id="`${id}-${field.key}`"
                        v-model="model.cfdi_use"
                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                        :aria-invalid="!!errors?.cfdi_use"
                    >
                        <option value="">Sin especificar</option>
                        <option
                            v-for="(label, key) in sat.cfdiUses"
                            :key="key"
                            :value="key"
                        >
                            {{ key }} · {{ label }}
                        </option>
                    </select>
                    <select
                        v-else-if="field.key === 'state'"
                        :id="`${id}-${field.key}`"
                        v-model="model.state"
                        class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                        :aria-invalid="!!errors?.state"
                    >
                        <option value="">Selecciona un estado</option>
                        <option v-for="state in sat.states" :key="state">
                            {{ state }}
                        </option>
                    </select>
                    <Input
                        v-else
                        :id="`${id}-${field.key}`"
                        v-model="model[field.key]"
                        :type="field.type ?? 'text'"
                        :required="field.required"
                        :maxlength="field.max ?? 255"
                        :inputmode="
                            ['zip', 'tax_zip'].includes(field.key)
                                ? 'numeric'
                                : undefined
                        "
                        :class="{ uppercase: field.key === 'rfc' }"
                        :aria-invalid="!!errors?.[field.key]"
                        :aria-describedby="
                            errors?.[field.key]
                                ? `${id}-${field.key}-error`
                                : undefined
                        "
                    />
                    <small
                        v-if="errors?.[field.key]"
                        :id="`${id}-${field.key}-error`"
                        class="block text-destructive"
                        >{{ errors[field.key] }}</small
                    >
                    <Button
                        v-if="field.key === 'zip'"
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="!model.tax_zip"
                        @click="model.zip = model.tax_zip"
                        >Copiar CP fiscal</Button
                    >
                </div>
            </div>
        </fieldset>
        <label :for="`${id}-notes`" class="grid gap-1 text-sm"
            >Notas
            <Textarea
                :id="`${id}-notes`"
                v-model="model.notes"
                :maxlength="2000"
                rows="2"
            />
            <small v-if="errors?.notes" class="text-destructive">{{
                errors.notes
            }}</small>
        </label>
    </div>
</template>
