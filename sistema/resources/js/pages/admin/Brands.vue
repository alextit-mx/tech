<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
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
            <Input
                aria-label="Nombre de marca"
                v-model="form.name"
                required
                placeholder="Nombre de marca"
                class="rounded border p-2"
            /><Button class="rounded bg-primary px-4 text-primary-foreground">
                Agregar marca
            </Button>
        </form>
        <ul class="divide-y rounded border">
            <li
                v-for="b in brands"
                class="flex items-center justify-between gap-3 p-3"
            >
                <Input
                    v-model="b.name"
                    class="rounded border px-2 py-1"
                /><Button
                    variant="outline"
                    class="rounded border px-2"
                    @click="update(b)"
                >
                    Guardar</Button
                ><span
                    class="w-24 shrink-0 text-right text-sm text-muted-foreground"
                    >{{ b.products_count }} productos</span
                >
            </li>
        </ul>
    </section>
</template>
