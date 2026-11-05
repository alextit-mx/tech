<script setup lang="ts">
import { useVModel } from '@vueuse/core';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    modelValue?: string | number | null;
    defaultValue?: string | number;
    class?: HTMLAttributes['class'];
}>();
const emit = defineEmits<{
    'update:modelValue': [value: string | number | null];
}>();
const value = useVModel(props, 'modelValue', emit, {
    passive: true,
    defaultValue: props.defaultValue,
});
</script>

<template>
    <textarea
        v-model="value"
        data-slot="textarea"
        :class="cn('flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm text-foreground outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30 disabled:cursor-not-allowed disabled:opacity-60', props.class)"
    />
</template>
