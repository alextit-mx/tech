<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';

defineProps<{ compact?: boolean }>();
const { appearance, updateAppearance, error } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Claro' },
    { value: 'dark', Icon: Moon, label: 'Oscuro' },
    { value: 'system', Icon: Monitor, label: 'Sistema' },
] as const;
</script>

<template>
    <div>
        <div
            aria-label="Apariencia"
            class="inline-flex gap-1 rounded-lg bg-muted p-1"
        >
            <button
                v-for="{ value, Icon, label } in tabs"
                :key="value"
                type="button"
                :aria-label="label"
                :aria-pressed="appearance === value"
                :title="label"
                @click="updateAppearance(value)"
                :class="[
                    'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                    appearance === value
                        ? 'bg-card text-foreground'
                        : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                ]"
            >
                <component :is="Icon" class="-ml-1 h-4 w-4" />
                <span
                    :class="[
                        'ml-1.5 text-sm',
                        compact ? 'hidden lg:inline' : '',
                    ]"
                    >{{ label }}</span
                >
            </button>
        </div>
        <p v-if="error" role="alert" class="text-xs text-destructive">
            {{ error }}
        </p>
    </div>
</template>
