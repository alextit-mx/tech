<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
const props = defineProps<{ categories: any[] }>();
const form = useForm({
    name: '',
    slug: '',
    parent_id: null as number | null,
    builder_slot: '' as string,
    sort: 0,
    is_visible: true,
    description: '',
});
function update(c: any) {
    router.patch(
        `/admin/categories/${c.id}`,
        {
            name: c.name,
            slug: c.slug,
            parent_id: c.parent_id,
            builder_slot: c.builder_slot,
            sort: c.sort,
            is_visible: c.is_visible,
            description: c.description,
        },
        { preserveScroll: true },
    );
}
function save() {
    form.post('/admin/categories', { onSuccess: () => form.reset() });
}
</script>
<template>
    <Head title="Categorías" />
    <section class="p-6">
        <h1 class="mb-5 text-3xl font-bold">Categorías</h1>
        <form class="mb-6 flex flex-wrap gap-2" @submit.prevent="save">
            <input
                v-model="form.name"
                required
                placeholder="Nombre"
                class="rounded border p-2"
            /><select v-model="form.parent_id" class="rounded border p-2">
                <option :value="null">Categoría principal</option>
                <option v-for="c in categories" :value="c.id">
                    {{ c.name }}
                </option></select
            ><select v-model="form.builder_slot" class="rounded border p-2">
                <option value="">Sin componente</option>
                <option
                    v-for="slot in [
                        'cpu',
                        'motherboard',
                        'ram',
                        'gpu',
                        'storage',
                        'psu',
                        'cooler',
                        'case',
                    ]"
                >
                    {{ slot }}
                </option></select
            ><button class="rounded bg-[#E30613] px-4 text-white">
                Agregar
            </button>
        </form>
        <ul class="divide-y rounded border">
            <li v-for="c in categories" class="flex items-center gap-3 p-3">
                <input v-model="c.name" class="rounded border px-2 py-1" />
                <small class="text-muted-foreground">{{
                    c.builder_slot || ''
                }}</small>
                <button class="rounded border px-2 py-1" @click="update(c)">
                    Guardar
                </button>
            </li>
        </ul>
    </section>
</template>
