<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
const props = defineProps<{ users: any[] }>();
const form = useForm({
    name: '',
    email: '',
    role: 'seller',
    password: '',
    is_active: true,
});
function update(u: any, changes: any = {}) {
    router.patch(
        `/admin/users/${u.id}`,
        {
            name: u.name,
            email: u.email,
            role: u.role,
            is_active: u.is_active,
            password: u.newPassword || null,
            ...changes,
        },
        { preserveScroll: true },
    );
}
function save() {
    form.post('/admin/users', { onSuccess: () => form.reset() });
}
</script>
<template>
    <Head title="Usuarios" />
    <section class="p-6">
        <h1 class="mb-5 text-3xl font-bold">Usuarios</h1>
        <form
            class="mb-6 grid max-w-3xl gap-3 rounded-lg border p-4 md:grid-cols-2"
            @submit.prevent="save"
        >
            <input
                v-model="form.name"
                required
                placeholder="Nombre"
                class="rounded border p-2"
            /><input
                v-model="form.email"
                required
                type="email"
                placeholder="Correo"
                class="rounded border p-2"
            /><select v-model="form.role" class="rounded border p-2">
                <option value="seller">Vendedor</option>
                <option value="owner">Dueño</option></select
            ><input
                v-model="form.password"
                required
                type="password"
                placeholder="Contraseña inicial"
                class="rounded border p-2"
            /><button class="rounded bg-[#E30613] p-2 text-white md:col-span-2">
                Crear usuario
            </button>
        </form>
        <table class="w-full rounded border text-left">
            <thead>
                <tr>
                    <th class="p-3">Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Contraseña nueva</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="u in users" class="border-t">
                    <td class="p-3">
                        <input
                            v-model="u.name"
                            class="w-36 rounded border px-2 py-1"
                        />
                    </td>
                    <td>
                        <input
                            v-model="u.email"
                            type="email"
                            class="w-48 rounded border px-2 py-1"
                        />
                    </td>
                    <td>
                        <select v-model="u.role" class="rounded border p-1">
                            <option value="seller">Vendedor</option>
                            <option value="owner">Dueño</option>
                        </select>
                    </td>
                    <td>{{ u.is_active ? 'Activo' : 'Inactivo' }}</td>
                    <td>
                        <input
                            v-model="u.newPassword"
                            type="password"
                            placeholder="Opcional"
                            class="rounded border px-2 py-1"
                        />
                    </td>
                    <td>
                        <button
                            class="mr-2 rounded border px-2 py-1"
                            @click="update(u)"
                        >
                            Guardar</button
                        ><button
                            class="rounded border px-2 py-1"
                            @click="update(u, { is_active: !u.is_active })"
                        >
                            {{ u.is_active ? 'Desactivar' : 'Activar' }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
