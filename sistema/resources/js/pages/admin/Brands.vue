<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
const props = defineProps<{ brands: any[] }>();
const form = useForm({ name: '', slug: '' });
function update(b: any) {
    router.patch(
        `/admin/brands/${b.id}`,
        { name: b.name, slug: b.slug },
        { preserveScroll: true },
    );
}
function save() {
    form.post('/admin/brands', { onSuccess: () => form.reset() });
}
</script>
<template>
    <Head title="Marcas" />
    <section class="p-6">
        <h1 class="mb-5 text-3xl font-bold">Marcas</h1>
        <form class="mb-6 flex gap-2" @submit.prevent="save">
            <input
                v-model="form.name"
                required
                placeholder="Nombre de marca"
                class="rounded border p-2"
            /><button class="rounded bg-[#E30613] px-4 text-white">
                Agregar marca
            </button>
        </form>
        <ul class="divide-y rounded border">
            <li v-for="b in brands" class="flex justify-between p-3">
                <input
                    v-model="b.name"
                    class="rounded border px-2 py-1"
                /><button class="rounded border px-2" @click="update(b)">
                    Guardar</button
                ><span>{{ b.products_count }} productos</span>
            </li>
        </ul>
    </section>
</template>
