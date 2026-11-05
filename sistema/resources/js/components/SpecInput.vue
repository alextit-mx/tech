<script setup lang="ts">
import type { SpecField, SpecValue } from '@/lib/specs';
defineProps<{ field: SpecField; id?: string }>();
const model = defineModel<SpecValue>();
const control =
    'min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm';
function toggle(option: string, checked: boolean) {
    const values = Array.isArray(model.value) ? model.value : [];
    model.value = checked
        ? [...values, option]
        : values.filter((value) => value !== option);
}
</script>
<template>
    <div
        v-if="field.type === 'list'"
        :id="id"
        class="flex flex-wrap gap-x-4 gap-y-1"
    >
        <label
            v-for="option in field.options"
            :key="option"
            class="flex min-h-11 items-center gap-2 text-sm"
        >
            <input
                type="checkbox"
                :checked="Array.isArray(model) && model.includes(option)"
                @change="
                    toggle(option, ($event.target as HTMLInputElement).checked)
                "
            />{{ option }}
        </label>
    </div>
    <select
        v-else-if="field.type === 'boolean'"
        :id="id"
        v-model="model"
        :class="control"
    >
        <option :value="null">Selecciona</option>
        <option :value="true">Sí</option>
        <option :value="false">No</option>
    </select>
    <select
        v-else-if="field.options.length"
        :id="id"
        v-model="model"
        :class="control"
    >
        <option :value="null">Selecciona</option>
        <option v-for="option in field.options" :key="option">
            {{ option }}
        </option>
    </select>
    <input
        v-else
        :id="id"
        v-model="model"
        :type="['integer', 'tier'].includes(field.type) ? 'number' : 'text'"
        :min="field.type === 'tier' ? 1 : 0"
        :max="field.type === 'tier' ? 6 : undefined"
        :step="
            field.type === 'integer' || field.type === 'tier' ? 1 : undefined
        "
        :class="control"
    />
</template>
