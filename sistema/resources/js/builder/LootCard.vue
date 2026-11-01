<script setup lang="ts">
import { computed, ref } from 'vue';
import { RARITY } from './rules';

const props = defineProps<{
    name: string;
    brand: string | null;
    image: string | null;
    price: number | null;
    stock: 'ok' | 'low' | 'none';
    stockLabel: string;
    specs: string;
    rarity: number;
    equipped: boolean;
    reason: string | null;
}>();
const emit = defineEmits<{ pick: []; hover: [] }>();

const card = ref<HTMLElement | null>(null);
const tier = computed(() => RARITY[props.rarity] ?? RARITY[0]);
const money = (n: number) =>
    '$' + n.toLocaleString('es-MX', { maximumFractionDigits: 0 });

/* Inclinación 3D ligera siguiendo el puntero. */
function tilt(event: PointerEvent): void {
    const el = card.value;
    if (!el || event.pointerType !== 'mouse') return;
    const r = el.getBoundingClientRect();
    el.style.setProperty(
        '--rx',
        `${((event.clientY - r.top) / r.height - 0.5) * -10}deg`,
    );
    el.style.setProperty(
        '--ry',
        `${((event.clientX - r.left) / r.width - 0.5) * 12}deg`,
    );
    el.style.setProperty(
        '--mx',
        `${((event.clientX - r.left) / r.width) * 100}%`,
    );
}
function reset(): void {
    card.value?.style.setProperty('--rx', '0deg');
    card.value?.style.setProperty('--ry', '0deg');
}
</script>

<template>
    <button
        ref="card"
        type="button"
        class="loot"
        :class="{ equipped, off: reason }"
        :style="{ '--tier': tier.color }"
        :aria-pressed="equipped"
        :aria-description="reason ?? undefined"
        @click="emit('pick')"
        @pointerenter="emit('hover')"
        @pointermove="tilt"
        @pointerleave="reset"
    >
        <span class="loot-shine" aria-hidden="true"></span>
        <span class="loot-media">
            <img
                v-if="image"
                :src="image"
                alt=""
                loading="lazy"
                width="120"
                height="120"
            />
            <img
                v-else
                class="loot-ph"
                src="/img/ats-emblema.png"
                alt=""
                width="48"
                height="48"
            />
            <span class="loot-tier">{{ tier.name }}</span>
        </span>
        <span class="loot-body">
            <span v-if="brand" class="loot-brand">{{ brand }}</span>
            <span class="loot-name">{{ name }}</span>
            <span class="loot-specs">{{ specs }}</span>
            <span class="loot-foot">
                <span class="loot-price" :class="{ quote: price === null }">{{
                    price === null ? 'Cotizar' : money(price)
                }}</span>
                <span class="loot-stock" :data-level="stock">{{
                    stockLabel
                }}</span>
            </span>
            <span v-if="reason" class="loot-reason">{{ reason }}</span>
            <span v-else-if="equipped" class="loot-equipped">Equipado</span>
        </span>
    </button>
</template>
