<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Head, router, useForm } from '@inertiajs/vue3';
const props = defineProps<{ categories: any[] }>();
const slotLabels: Record<string, string> = {
    cpu: 'Procesador',
    motherboard: 'Tarjeta madre',
    ram: 'Memoria RAM',
    gpu: 'Tarjeta de video',
    storage: 'Almacenamiento',
    psu: 'Fuente',
    cooler: 'Enfriamiento',
    case: 'Gabinete',
};
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
            <Input
                aria-label="Nombre"
                v-model="form.name"
                required
                placeholder="Nombre"
                class="rounded border p-2"
            /><select
                aria-label="Categoría principal"
                v-model="form.parent_id"
                class="rounded border p-2"
            >
                <option :value="null">Categoría principal</option>
                <option v-for="c in categories" :value="c.id">
                    {{ c.name }}
                </option></select
            ><select
                aria-label="Componente del configurador"
                v-model="form.builder_slot"
                class="rounded border p-2"
            >
                <option value="">Sin componente</option>
                <option v-for="(label, slot) in slotLabels" :value="slot">
                    {{ label }}
                </option></select
            ><Button class="rounded bg-primary px-4 text-primary-foreground">
                Agregar
            </Button>
        </form>
        <ul class="divide-y rounded border">
            <li
                v-for="c in categories"
                class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1 p-3 sm:flex sm:gap-3"
            >
                <Input
                    aria-label="Nombre de la categoría"
                    v-model="c.name"
                    class="rounded border px-2 py-1"
                />
                <small class="col-start-1 row-start-2 text-muted-foreground">{{
                    slotLabels[c.builder_slot] || ''
                }}</small>
                <Button
                    variant="outline"
                    class="col-start-2 row-span-2 row-start-1 rounded border px-2 py-1"
                    @click="update(c)"
                >
                    Guardar
                </Button>
            </li>
        </ul>
    </section>
</template>
