<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    Copy,
    ExternalLink,
    Eye,
    EyeOff,
    Pencil,
    Plus,
    Trash2,
    TriangleAlert,
} from '@lucide/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';

type BuildRow = {
    id: number;
    name: string;
    slug: string;
    use: string;
    is_published: boolean;
    image_url: string | null;
    parts_count: number;
    thumbs: string[];
    total: number | null;
    stock: 'ok' | 'low' | 'none';
    stock_label: string;
    errors: string[];
};
defineProps<{ builds: BuildRow[] }>();

const money = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0,
});
const dot = { ok: 'bg-emerald-500', low: 'bg-amber-500', none: 'bg-zinc-400' };
const opts = {
    preserveScroll: true,
    onError: (e: Record<string, string>) =>
        toast.error(Object.values(e)[0] ?? 'No se pudo completar.'),
};

function publish(b: BuildRow) {
    router.post(
        `/admin/builds/${b.id}/publish`,
        { is_published: !b.is_published },
        {
            ...opts,
            onSuccess: () =>
                toast.success(
                    b.is_published
                        ? 'Publicado en la tienda.'
                        : 'Oculto en la tienda.',
                ),
        },
    );
}
function move(b: BuildRow, direction: number) {
    router.post(`/admin/builds/${b.id}/move`, { direction }, opts);
}
function duplicate(b: BuildRow) {
    router.post(`/admin/builds/${b.id}/duplicate`, {}, opts);
}
function remove(b: BuildRow) {
    if (confirm(`¿Borrar «${b.name}»? Los productos no se tocan.`))
        router.delete(`/admin/builds/${b.id}`, opts);
}
</script>

<template>
    <Head title="Equipos armados" />
    <section class="grid gap-4 p-4 md:p-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight md:text-3xl">
                    Equipos armados
                </h1>
                <p class="max-w-2xl text-sm text-muted-foreground">
                    Se muestran en la tienda con su precio total en vivo.
                    También puedes armarlos en Arma tu PC y usar «Guardar como
                    equipo armado».
                </p>
            </div>
            <Button as-child>
                <Link href="/admin/builds/create"
                    ><Plus class="size-4" /> Nuevo equipo</Link
                >
            </Button>
        </header>

        <div
            v-if="!builds.length"
            class="grid place-items-center gap-2 rounded-xl border border-dashed p-10 text-center"
        >
            <p class="font-medium">Aún no hay equipos armados.</p>
            <p class="text-sm text-muted-foreground">
                Crea uno aquí o genera los sugeridos con
                <code class="rounded bg-muted px-1"
                    >php artisan equipos:sugeridos</code
                >.
            </p>
        </div>

        <ul class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <li
                v-for="(b, i) in builds"
                :key="b.id"
                class="flex flex-col overflow-hidden rounded-xl border bg-card"
            >
                <Link
                    :href="`/admin/builds/${b.id}/edit`"
                    class="relative block aspect-[16/9] bg-muted"
                >
                    <img
                        v-if="b.image_url"
                        :src="b.image_url"
                        alt=""
                        class="size-full object-cover"
                    />
                    <span v-else class="grid size-full grid-cols-4 gap-1 p-3">
                        <img
                            v-for="(t, k) in b.thumbs"
                            :key="k"
                            :src="t"
                            alt=""
                            class="aspect-square w-full self-center rounded-md bg-background object-cover"
                        />
                    </span>
                    <span
                        class="absolute top-2 left-2 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :class="
                            b.is_published
                                ? 'bg-emerald-600 text-white'
                                : 'bg-background/90 text-muted-foreground'
                        "
                        >{{ b.is_published ? 'Publicado' : 'Oculto' }}</span
                    >
                </Link>
                <div class="grid flex-1 gap-1 p-4">
                    <span class="text-xs text-muted-foreground uppercase"
                        >{{ b.use }} · {{ b.parts_count }} piezas</span
                    >
                    <b class="leading-snug">{{ b.name }}</b>
                    <span class="text-lg font-bold tabular-nums">{{
                        b.total === null ? 'Cotizar' : money.format(b.total)
                    }}</span>
                    <span
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        ><i
                            class="size-2 rounded-full"
                            :class="dot[b.stock]"
                        />{{ b.stock_label }}</span
                    >
                    <p
                        v-if="b.errors.length"
                        class="mt-1 flex gap-1.5 text-xs text-amber-600 dark:text-amber-300"
                    >
                        <TriangleAlert class="size-3.5 shrink-0" />{{
                            b.errors[0]
                        }}
                    </p>
                </div>
                <div class="flex items-center gap-1 border-t p-2">
                    <Button size="sm" variant="ghost" as-child
                        ><Link :href="`/admin/builds/${b.id}/edit`"
                            ><Pencil class="size-4" /> Editar</Link
                        ></Button
                    >
                    <button
                        type="button"
                        class="rounded-md p-2 hover:bg-muted"
                        :title="b.is_published ? 'Ocultar' : 'Publicar'"
                        @click="publish(b)"
                    >
                        <EyeOff v-if="b.is_published" class="size-4" /><Eye
                            v-else
                            class="size-4"
                        />
                    </button>
                    <button
                        type="button"
                        class="rounded-md p-2 hover:bg-muted disabled:opacity-30"
                        :disabled="i === 0"
                        title="Subir"
                        @click="move(b, -1)"
                    >
                        <ArrowUp class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="rounded-md p-2 hover:bg-muted disabled:opacity-30"
                        :disabled="i === builds.length - 1"
                        title="Bajar"
                        @click="move(b, 1)"
                    >
                        <ArrowDown class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="rounded-md p-2 hover:bg-muted"
                        title="Duplicar"
                        @click="duplicate(b)"
                    >
                        <Copy class="size-4" />
                    </button>
                    <a
                        v-if="b.is_published"
                        :href="`/equipos/${b.slug}`"
                        target="_blank"
                        rel="noopener"
                        class="rounded-md p-2 hover:bg-muted"
                        title="Ver en tienda"
                        ><ExternalLink class="size-4"
                    /></a>
                    <button
                        type="button"
                        class="ml-auto rounded-md p-2 text-destructive hover:bg-destructive/10"
                        title="Borrar"
                        @click="remove(b)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>
            </li>
        </ul>
    </section>
</template>
