<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{ customers: any; filters: { search?: string } }>();

const search = ref(props.filters.search ?? '');
const editing = ref<number | null>(null);
const form = useForm({ name: '', phone: '', email: '', rfc: '', notes: '' });

function apply() {
    router.get(
        '/admin/customers',
        { search: search.value },
        { preserveState: true, replace: true },
    );
}
function edit(c: any) {
    editing.value = c.id;
    form.defaults({
        name: c.name,
        phone: c.phone ?? '',
        email: c.email ?? '',
        rfc: c.rfc ?? '',
        notes: c.notes ?? '',
    });
    form.reset();
}
function startNew() {
    editing.value = 0;
    form.defaults({ name: '', phone: '', email: '', rfc: '', notes: '' });
    form.reset();
}
function save() {
    const done = {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    };
    if (editing.value) form.patch(`/admin/customers/${editing.value}`, done);
    else form.post('/admin/customers', done);
}
</script>

<template>
    <Head title="Clientes" />
    <section class="p-6">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p
                    class="font-semibold tracking-widest text-[#E30613] uppercase"
                >
                    Ventas
                </p>
                <h1 class="text-3xl font-bold">Clientes</h1>
            </div>
            <div class="flex gap-2">
                <input
                    v-model="search"
                    placeholder="Nombre, WhatsApp o correo"
                    class="w-64 rounded-md border px-3 py-2"
                    @keyup.enter="apply"
                />
                <button class="rounded border px-3 py-2" @click="apply">
                    Buscar
                </button>
                <button
                    class="rounded bg-[#E30613] px-4 py-2 font-semibold text-white"
                    @click="startNew"
                >
                    Nuevo cliente
                </button>
            </div>
        </div>

        <form
            v-if="editing !== null"
            class="mb-5 grid gap-3 rounded-xl border p-4 md:grid-cols-3"
            @submit.prevent="save"
        >
            <label class="grid gap-1 text-sm"
                >Nombre<input
                    v-model="form.name"
                    required
                    class="rounded-md border px-3 py-2"
                /><small class="text-red-600">{{
                    form.errors.name
                }}</small></label
            >
            <label class="grid gap-1 text-sm"
                >WhatsApp<input
                    v-model="form.phone"
                    placeholder="10 dígitos"
                    class="rounded-md border px-3 py-2"
                /><small class="text-red-600">{{
                    form.errors.phone
                }}</small></label
            >
            <label class="grid gap-1 text-sm"
                >Correo<input
                    v-model="form.email"
                    type="email"
                    class="rounded-md border px-3 py-2"
                /><small class="text-red-600">{{
                    form.errors.email
                }}</small></label
            >
            <label class="grid gap-1 text-sm"
                >RFC<input
                    v-model="form.rfc"
                    class="rounded-md border px-3 py-2 uppercase"
                /><small class="text-red-600">{{
                    form.errors.rfc
                }}</small></label
            >
            <label class="grid gap-1 text-sm md:col-span-2"
                >Notas<input
                    v-model="form.notes"
                    class="rounded-md border px-3 py-2"
            /></label>
            <div class="flex gap-2 md:col-span-3">
                <button
                    class="rounded bg-[#E30613] px-4 py-2 text-white"
                    :disabled="form.processing"
                >
                    Guardar
                </button>
                <button
                    type="button"
                    class="rounded border px-4 py-2"
                    @click="editing = null"
                >
                    Cancelar
                </button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="bg-muted">
                    <tr>
                        <th class="p-3">Nombre</th>
                        <th class="p-3">WhatsApp</th>
                        <th class="p-3">Correo</th>
                        <th class="p-3">RFC</th>
                        <th class="p-3 text-right">Cotizaciones</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="c in customers.data"
                        :key="c.id"
                        class="border-t"
                    >
                        <td class="p-3 font-semibold">{{ c.name }}</td>
                        <td class="p-3">
                            <a
                                v-if="c.phone"
                                :href="`https://wa.me/52${c.phone}`"
                                target="_blank"
                                class="underline"
                                >{{ c.phone }}</a
                            >
                        </td>
                        <td class="p-3">{{ c.email }}</td>
                        <td class="p-3">{{ c.rfc }}</td>
                        <td class="p-3 text-right tabular-nums">
                            <Link
                                :href="`/admin/quotes?search=${c.phone ?? c.name}&mine=0`"
                                class="underline"
                                >{{ c.quotes_count }}</Link
                            >
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <button class="mr-3 underline" @click="edit(c)">
                                Editar
                            </button>
                            <Link
                                :href="`/admin/quotes/create?customer_id=${c.id}`"
                                class="rounded bg-[#E30613] px-3 py-1 text-white"
                                >Cotizar</Link
                            >
                        </td>
                    </tr>
                    <tr v-if="!customers.data.length">
                        <td
                            colspan="6"
                            class="p-6 text-center text-muted-foreground"
                        >
                            Sin clientes todavía.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="customers.last_page > 1" class="mt-4 flex flex-wrap gap-1">
            <template v-for="link in customers.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="rounded border px-3 py-1 text-sm"
                    :class="{ 'bg-[#E30613] text-white': link.active }"
                    ><span v-html="link.label"
                /></Link>
            </template>
        </div>
    </section>
</template>
