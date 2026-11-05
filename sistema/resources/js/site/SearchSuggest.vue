<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/** Sugerencias en vivo bajo un buscador existente (el formulario sigue funcionando sin JS). */
type Item = { name: string; brand: string | null; url: string; image: string; price: string; stock: 'ok' | 'low' | 'none'; stock_label: string };
type Category = { name: string; url: string };

const props = defineProps<{ input: HTMLInputElement; onReady: (open: () => void) => void }>();
const id = `suggest-${Math.random().toString(36).slice(2, 8)}`;
const q = ref(props.input.value.trim());
const items = ref<Item[]>([]);
const categories = ref<Category[]>([]);
const active = ref(-1);
const shown = ref(false);
const loading = ref(false);
const cache = new Map<string, { products: Item[]; categories: Category[] }>();
let timer: number | undefined;
let controller: AbortController | undefined;

const searchUrl = computed(() => `/buscar?q=${encodeURIComponent(q.value)}`);
const visible = computed(() => shown.value && q.value.length >= 2 && (loading.value || items.value.length > 0 || categories.value.length > 0 || q.value.length >= 3));

async function fetchNow() {
    const term = q.value;
    if (term.length < 2) {
        items.value = [];
        categories.value = [];
        return;
    }
    const hit = cache.get(term);
    if (hit) {
        items.value = hit.products;
        categories.value = hit.categories;
        return;
    }
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    try {
        const res = await fetch(`/buscar/sugerencias?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
        if (!res.ok) return;
        const data = (await res.json()) as { products: Item[]; categories: Category[] };
        cache.set(term, data);
        if (term === q.value) {
            items.value = data.products;
            categories.value = data.categories;
            active.value = -1;
        }
    } catch {
        /* Cancelada por una búsqueda más nueva o sin conexión: el formulario normal sigue funcionando. */
    } finally {
        loading.value = false;
    }
}

function onInput() {
    q.value = props.input.value.trim();
    shown.value = true;
    window.clearTimeout(timer);
    timer = window.setTimeout(() => void fetchNow(), 180);
}

function onKey(e: KeyboardEvent) {
    if (!visible.value) return;
    const total = items.value.length;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        active.value = total ? (active.value + (e.key === 'ArrowDown' ? 1 : -1) + total + 1) % (total + 1) : -1;
        if (active.value === total) active.value = -1;
    } else if (e.key === 'Enter' && active.value >= 0 && items.value[active.value]) {
        e.preventDefault();
        location.href = items.value[active.value].url;
    } else if (e.key === 'Escape') {
        shown.value = false;
    }
}

function open() {
    q.value = props.input.value.trim();
    shown.value = true;
    void fetchNow();
}
const onFocus = () => open();
const onBlur = () => window.setTimeout(() => (shown.value = false), 160);

onMounted(() => {
    const input = props.input;
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', id);
    input.addEventListener('input', onInput);
    input.addEventListener('keydown', onKey);
    input.addEventListener('focus', onFocus);
    input.addEventListener('blur', onBlur);
    props.onReady(open);
});
onBeforeUnmount(() => {
    const input = props.input;
    input.removeEventListener('input', onInput);
    input.removeEventListener('keydown', onKey);
    input.removeEventListener('focus', onFocus);
    input.removeEventListener('blur', onBlur);
});
</script>

<template>
    <div v-show="visible" :id="id" class="suggest" role="listbox" aria-label="Sugerencias" @mousedown.prevent>
        <p v-if="categories.length" class="suggest-cats">
            <a v-for="c in categories" :key="c.url" :href="c.url">{{ c.name }}</a>
        </p>
        <a v-for="(item, i) in items" :id="`${id}-${i}`" :key="item.url" :href="item.url" class="suggest-item" role="option" :aria-selected="i === active" @mouseenter="active = i">
            <span class="suggest-thumb showcase" :class="{ 'is-pending': item.image.includes('producto-sin-foto') }">
                <span v-if="item.image.includes('producto-sin-foto')" class="photo-pending"><img src="/img/ats-emblema.png" alt="" width="48" height="48" /><span>Foto pendiente</span></span>
                <img v-else :src="item.image" alt="" width="48" height="48" loading="lazy" />
            </span>
            <span class="suggest-name">
                <small v-if="item.brand">{{ item.brand }}</small>
                {{ item.name }}
            </span>
            <span class="suggest-price">
                {{ item.price }}
                <span class="stock" :data-level="item.stock">{{ item.stock_label }}</span>
            </span>
        </a>
        <p v-if="!loading && !items.length && !categories.length" class="suggest-empty">Sin coincidencias rápidas. Presiona Enter para buscar en todo el catálogo.</p>
        <a class="suggest-all" :href="searchUrl">{{ loading ? 'Buscando…' : `Ver todos los resultados para «${q}»` }}</a>
    </div>
</template>
