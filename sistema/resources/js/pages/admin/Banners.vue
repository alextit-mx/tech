<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Banner {
    id: number;
    image_url: string;
    image_mobile_url: string | null;
    alt: string;
    link: string | null;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
}

defineProps<{ banners: Banner[] }>();

const form = useForm({
    image: null as File | null,
    image_mobile: null as File | null,
    alt: '',
    link: '',
    is_active: true,
    starts_at: '',
    ends_at: '',
});
const editing = ref<number | null>(null);
const edit = useForm({
    image: null as File | null,
    image_mobile: null as File | null,
    alt: '',
    link: '',
    is_active: true,
    starts_at: '',
    ends_at: '',
});
const date = (d: string | null) => (d ? d.slice(0, 10) : '');

function create() {
    form.post('/admin/banners', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
function startEdit(b: Banner) {
    editing.value = b.id;
    edit.defaults({
        image: null,
        image_mobile: null,
        alt: b.alt,
        link: b.link ?? '',
        is_active: b.is_active,
        starts_at: date(b.starts_at),
        ends_at: date(b.ends_at),
    });
    edit.reset();
}
function save(id: number) {
    edit.post(`/admin/banners/${id}`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
}
function toggle(b: Banner) {
    router.post(
        `/admin/banners/${b.id}`,
        {
            alt: b.alt,
            link: b.link ?? '',
            is_active: !b.is_active,
            starts_at: date(b.starts_at),
            ends_at: date(b.ends_at),
        },
        { preserveScroll: true },
    );
}
function move(id: number, direction: number) {
    router.post(
        `/admin/banners/${id}/move`,
        { direction },
        { preserveScroll: true },
    );
}
function remove(id: number) {
    if (window.confirm('¿Eliminar este banner?'))
        router.delete(`/admin/banners/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Banners" />
    <section class="space-y-6 p-6">
        <div>
            <p class="font-semibold tracking-widest text-[#E30613] uppercase">
                Tienda
            </p>
            <h1 class="text-3xl font-bold">Banners</h1>
            <p class="text-sm text-muted-foreground">
                Carrusel de imágenes arriba de la tienda. Escritorio 2400×800 px
                (3:1); celular opcional 1200×1500 px (4:5). El texto va dentro
                de la imagen.
            </p>
        </div>

        <form
            class="grid gap-3 rounded-xl border p-4 md:grid-cols-3"
            @submit.prevent="create"
        >
            <label class="grid gap-1 text-sm"
                >Imagen escritorio<input
                    type="file"
                    accept="image/*"
                    required
                    class="text-sm"
                    @input="
                        form.image =
                            ($event.target as HTMLInputElement).files?.[0] ??
                            null
                    "
                /><small class="text-red-600 dark:text-red-300">{{
                    form.errors.image
                }}</small></label
            >
            <label class="grid gap-1 text-sm"
                >Imagen celular (opcional)<input
                    type="file"
                    accept="image/*"
                    class="text-sm"
                    @input="
                        form.image_mobile =
                            ($event.target as HTMLInputElement).files?.[0] ??
                            null
                    "
            /></label>
            <label class="grid gap-1 text-sm"
                >Descripción (para lectores de pantalla)<input
                    v-model="form.alt"
                    required
                    class="rounded-md border px-3 py-2"
                /><small class="text-red-600 dark:text-red-300">{{
                    form.errors.alt
                }}</small></label
            >
            <label class="grid gap-1 text-sm"
                >Enlace (opcional)<input
                    v-model="form.link"
                    placeholder="/tienda/tarjetas-de-video"
                    class="rounded-md border px-3 py-2"
            /></label>
            <label class="grid gap-1 text-sm"
                >Desde (opcional)<input
                    v-model="form.starts_at"
                    type="date"
                    class="rounded-md border px-3 py-2"
            /></label>
            <label class="grid gap-1 text-sm"
                >Hasta (opcional)<input
                    v-model="form.ends_at"
                    type="date"
                    class="rounded-md border px-3 py-2"
                /><small class="text-red-600 dark:text-red-300">{{
                    form.errors.ends_at
                }}</small></label
            >
            <div class="md:col-span-3">
                <button
                    class="rounded bg-[#E30613] px-4 py-2 font-semibold text-white"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Subiendo…' : 'Agregar banner' }}
                </button>
            </div>
        </form>

        <p v-if="!banners.length" class="text-muted-foreground">
            Sin banners todavía: la tienda no muestra carrusel.
        </p>
        <ul class="space-y-3">
            <li
                v-for="(b, i) in banners"
                :key="b.id"
                class="grid gap-4 rounded-xl border p-3 md:grid-cols-[320px_1fr]"
                :class="{ 'opacity-60': !b.is_active }"
            >
                <img
                    :src="b.image_url"
                    :alt="b.alt"
                    class="aspect-[3/1] w-full rounded-md object-cover"
                />
                <div
                    v-if="editing !== b.id"
                    class="flex flex-wrap items-start justify-between gap-3"
                >
                    <div class="space-y-1 text-sm">
                        <p class="font-semibold">{{ b.alt }}</p>
                        <p class="text-muted-foreground">
                            {{ b.link || 'Sin enlace' }} ·
                            {{
                                b.image_mobile_url
                                    ? 'con imagen para celular'
                                    : 'misma imagen en celular'
                            }}
                        </p>
                        <p class="text-muted-foreground">
                            {{
                                b.starts_at || b.ends_at
                                    ? `Vigencia: ${date(b.starts_at) || '—'} a ${date(b.ends_at) || '—'}`
                                    : 'Sin vigencia'
                            }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <button
                            class="rounded border px-2 py-1"
                            :disabled="i === 0"
                            @click="move(b.id, -1)"
                        >
                            ↑
                        </button>
                        <button
                            class="rounded border px-2 py-1"
                            :disabled="i === banners.length - 1"
                            @click="move(b.id, 1)"
                        >
                            ↓
                        </button>
                        <button
                            class="rounded border px-3 py-1"
                            @click="toggle(b)"
                        >
                            {{ b.is_active ? 'Desactivar' : 'Activar' }}
                        </button>
                        <button
                            class="rounded border px-3 py-1"
                            @click="startEdit(b)"
                        >
                            Editar
                        </button>
                        <button
                            class="rounded border px-3 py-1 text-red-600 dark:text-red-300"
                            @click="remove(b.id)"
                        >
                            Eliminar
                        </button>
                    </div>
                </div>
                <form
                    v-else
                    class="grid gap-2 text-sm md:grid-cols-2"
                    @submit.prevent="save(b.id)"
                >
                    <label class="grid gap-1"
                        >Reemplazar imagen<input
                            type="file"
                            accept="image/*"
                            @input="
                                edit.image =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] ?? null
                            "
                    /></label>
                    <label class="grid gap-1"
                        >Reemplazar imagen celular<input
                            type="file"
                            accept="image/*"
                            @input="
                                edit.image_mobile =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] ?? null
                            "
                    /></label>
                    <label class="grid gap-1"
                        >Descripción<input
                            v-model="edit.alt"
                            required
                            class="rounded-md border px-3 py-2"
                    /></label>
                    <label class="grid gap-1"
                        >Enlace<input
                            v-model="edit.link"
                            class="rounded-md border px-3 py-2"
                    /></label>
                    <label class="grid gap-1"
                        >Desde<input
                            v-model="edit.starts_at"
                            type="date"
                            class="rounded-md border px-3 py-2"
                    /></label>
                    <label class="grid gap-1"
                        >Hasta<input
                            v-model="edit.ends_at"
                            type="date"
                            class="rounded-md border px-3 py-2"
                    /></label>
                    <label class="flex items-center gap-2"
                        ><input v-model="edit.is_active" type="checkbox" />
                        Activo</label
                    >
                    <div class="flex gap-2 md:col-span-2">
                        <button
                            class="rounded bg-[#E30613] px-3 py-1.5 text-white"
                            :disabled="edit.processing"
                        >
                            Guardar
                        </button>
                        <button
                            type="button"
                            class="rounded border px-3 py-1.5"
                            @click="editing = null"
                        >
                            Cancelar
                        </button>
                    </div>
                </form>
            </li>
        </ul>
    </section>
</template>
