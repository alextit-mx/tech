<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    ImagePlus,
    LoaderCircle,
    RotateCw,
    Star,
    Trash2,
    X,
} from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { firstError, http } from '@/lib/http';

/** Fotos de un producto: arrastrar, elegir o pegar (Ctrl+V); se reducen en el navegador y suben sin recargar. */
export type ProductImg = { id: number; path: string; sort: number };
type Pending = {
    key: number;
    name: string;
    preview: string;
    file: File;
    progress: number;
    error: string | null;
};

const props = defineProps<{ productId: number; images: ProductImg[] }>();
const emit = defineEmits<{ change: [images: ProductImg[]] }>();

const list = ref<ProductImg[]>([...props.images]);
const pending = ref<Pending[]>([]);
const over = ref(false);
const dragId = ref<number | null>(null);
const picker = ref<HTMLInputElement | null>(null);
let seq = 0;
let running = false;

watch(
    () => props.images,
    (images) => (list.value = [...images]),
);

function set(images: ProductImg[]) {
    list.value = images;
    emit('change', images);
}

/** Reduce a 1600 px por lado y convierte a WebP (JPEG si el navegador no sabe) antes de subir. */
async function shrink(file: File): Promise<File> {
    try {
        const bitmap = await createImageBitmap(file, {
            imageOrientation: 'from-image',
        });
        const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
        canvas.height = Math.max(1, Math.round(bitmap.height * scale));
        canvas
            .getContext('2d')
            ?.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        bitmap.close();
        const blob = await new Promise<Blob | null>((done) =>
            canvas.toBlob(done, 'image/webp', 0.86),
        );
        const out =
            blob?.type === 'image/webp'
                ? blob
                : await new Promise<Blob | null>((done) =>
                      canvas.toBlob(done, 'image/jpeg', 0.86),
                  );
        if (!out || out.size >= file.size) {
            return file;
        }
        const base = file.name.replace(/\.[^.]+$/, '') || 'foto';

        return new File(
            [out],
            `${base}.${out.type === 'image/webp' ? 'webp' : 'jpg'}`,
            { type: out.type },
        );
    } catch {
        return file;
    }
}

function add(files: FileList | File[] | null) {
    const images = Array.from(files ?? []).filter((f) =>
        /^image\/(jpeg|png|webp)$/.test(f.type),
    );
    if (files && images.length < Array.from(files).length) {
        toast.error('Solo se aceptan fotos JPG, PNG o WebP.');
    }
    for (const file of images) {
        pending.value.push({
            key: ++seq,
            name: file.name,
            preview: URL.createObjectURL(file),
            file,
            progress: 0,
            error: null,
        });
    }
    void run();
}

async function run() {
    if (running) return;
    running = true;
    let item: Pending | undefined;
    while (
        (item = pending.value.find((p) => p.error === null && p.progress === 0))
    ) {
        const current = item;
        current.progress = 0.02;
        const fd = new FormData();
        fd.append('images[]', await shrink(current.file));
        const res = await http<{ images: ProductImg[] }>(
            'POST',
            `/admin/products/${props.productId}/images`,
            fd,
            (f) => (current.progress = Math.max(0.02, f * 0.95)),
        );
        if (res.ok) {
            URL.revokeObjectURL(current.preview);
            pending.value = pending.value.filter((p) => p.key !== current.key);
            set(res.data.images);
        } else {
            current.error = firstError(res.data, 'No se pudo subir la foto.');
            current.progress = 0;
        }
    }
    running = false;
    if (!pending.value.length) {
        toast.success('Fotos listas.');
    }
}

function retry(item: Pending) {
    item.error = null;
    void run();
}

function discard(item: Pending) {
    URL.revokeObjectURL(item.preview);
    pending.value = pending.value.filter((p) => p.key !== item.key);
}

async function move(img: ProductImg, to: number) {
    const from = list.value.findIndex((i) => i.id === img.id);
    if (from < 0 || to < 0 || to >= list.value.length || from === to) return;
    const next = [...list.value];
    next.splice(to, 0, ...next.splice(from, 1));
    const before = list.value;
    set(next);
    const res = await http<{ images: ProductImg[] }>(
        'PATCH',
        `/admin/images/${img.id}`,
        { sort: to },
    );
    if (res.ok) {
        set(res.data.images);
    } else {
        set(before);
        toast.error(firstError(res.data, 'No se pudo reordenar.'));
    }
}

async function remove(img: ProductImg) {
    if (!confirm('¿Borrar esta foto?')) return;
    const res = await http<{ images: ProductImg[] }>(
        'DELETE',
        `/admin/images/${img.id}`,
    );
    if (res.ok) {
        set(res.data.images);
        toast.success('Foto borrada.');
    } else {
        toast.error(firstError(res.data, 'No se pudo borrar la foto.'));
    }
}

function onDrop(e: DragEvent) {
    over.value = false;
    if (dragId.value === null) {
        add(e.dataTransfer?.files ?? null);
    }
}

function dropOn(target: ProductImg) {
    const img = list.value.find((i) => i.id === dragId.value);
    dragId.value = null;
    if (img) {
        void move(
            img,
            list.value.findIndex((i) => i.id === target.id),
        );
    }
}

/** Pegar una imagen copiada (por ejemplo, del sitio del fabricante) en cualquier parte mientras el editor está abierto. */
function onPaste(e: ClipboardEvent) {
    const files = Array.from(e.clipboardData?.files ?? []).filter((f) =>
        f.type.startsWith('image/'),
    );
    if (files.length) {
        e.preventDefault();
        add(files);
    }
}

onMounted(() => window.addEventListener('paste', onPaste));
onBeforeUnmount(() => {
    window.removeEventListener('paste', onPaste);
    pending.value.forEach((p) => URL.revokeObjectURL(p.preview));
});
</script>

<template>
    <div class="grid gap-3">
        <button
            type="button"
            class="flex min-h-28 w-full flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed px-4 py-5 text-center text-sm transition"
            :class="
                over
                    ? 'border-primary bg-primary/5'
                    : 'border-border hover:border-primary/60 hover:bg-muted/50'
            "
            @click="picker?.click()"
            @dragover.prevent="over = dragId === null"
            @dragleave="over = false"
            @drop.prevent="onDrop"
        >
            <ImagePlus class="size-6 text-muted-foreground" />
            <span class="font-medium"
                >Arrastra fotos aquí o haz clic para elegir</span
            >
            <span class="text-xs text-muted-foreground"
                >También puedes pegar una imagen copiada con Ctrl+V · JPG, PNG o
                WebP</span
            >
        </button>
        <input
            ref="picker"
            type="file"
            multiple
            accept="image/jpeg,image/png,image/webp"
            class="hidden"
            @change="
                add(($event.target as HTMLInputElement).files);
                ($event.target as HTMLInputElement).value = '';
            "
        />

        <ul
            v-if="list.length || pending.length"
            class="grid grid-cols-3 gap-2 sm:grid-cols-4"
        >
            <li
                v-for="(img, index) in list"
                :key="img.id"
                draggable="true"
                class="group relative aspect-square overflow-hidden rounded-lg border bg-muted"
                :class="dragId === img.id ? 'opacity-40' : ''"
                @dragstart="dragId = img.id"
                @dragend="dragId = null"
                @dragover.prevent
                @drop.prevent.stop="dropOn(img)"
            >
                <img
                    :src="`/uploads/${img.path}`"
                    alt=""
                    class="size-full object-cover"
                    loading="lazy"
                />
                <span
                    v-if="index === 0"
                    class="absolute top-1 left-1 rounded bg-primary px-1.5 py-0.5 text-[10px] font-semibold text-primary-foreground"
                    >Principal</span
                >
                <div
                    class="absolute inset-x-0 bottom-0 flex justify-between bg-gradient-to-t from-black/70 to-transparent p-1 opacity-100 transition sm:opacity-0 sm:group-focus-within:opacity-100 sm:group-hover:opacity-100"
                >
                    <span class="flex gap-0.5">
                        <button
                            type="button"
                            class="rounded p-1 text-white hover:bg-white/20 disabled:opacity-30"
                            :disabled="index === 0"
                            aria-label="Mover antes"
                            @click="move(img, index - 1)"
                        >
                            <ChevronLeft class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="rounded p-1 text-white hover:bg-white/20 disabled:opacity-30"
                            :disabled="index === list.length - 1"
                            aria-label="Mover después"
                            @click="move(img, index + 1)"
                        >
                            <ChevronRight class="size-4" />
                        </button>
                        <button
                            v-if="index > 0"
                            type="button"
                            class="rounded p-1 text-white hover:bg-white/20"
                            aria-label="Hacer principal"
                            title="Hacer principal"
                            @click="move(img, 0)"
                        >
                            <Star class="size-4" />
                        </button>
                    </span>
                    <button
                        type="button"
                        class="rounded p-1 text-white hover:bg-red-500/80"
                        aria-label="Borrar foto"
                        @click="remove(img)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>
            </li>
            <li
                v-for="item in pending"
                :key="item.key"
                class="relative aspect-square overflow-hidden rounded-lg border bg-muted"
            >
                <img
                    :src="item.preview"
                    alt=""
                    class="size-full object-cover"
                    :class="item.error ? 'opacity-30' : 'opacity-60'"
                />
                <div
                    v-if="!item.error"
                    class="absolute inset-x-2 bottom-2 h-1.5 overflow-hidden rounded-full bg-black/40"
                >
                    <div
                        class="h-full rounded-full bg-primary transition-[width]"
                        :style="{
                            width: `${Math.round(item.progress * 100)}%`,
                        }"
                    />
                </div>
                <LoaderCircle
                    v-if="!item.error"
                    class="absolute top-1/2 left-1/2 size-6 -translate-1/2 animate-spin text-white"
                />
                <div
                    v-else
                    class="absolute inset-0 flex flex-col items-center justify-center gap-1 p-2 text-center text-[11px] leading-tight"
                >
                    <span class="text-destructive">{{ item.error }}</span>
                    <span class="flex gap-1">
                        <button
                            type="button"
                            class="rounded bg-background/90 p-1"
                            aria-label="Reintentar"
                            @click="retry(item)"
                        >
                            <RotateCw class="size-3.5" />
                        </button>
                        <button
                            type="button"
                            class="rounded bg-background/90 p-1"
                            aria-label="Descartar"
                            @click="discard(item)"
                        >
                            <X class="size-3.5" />
                        </button>
                    </span>
                </div>
            </li>
        </ul>
        <p v-if="list.length > 1" class="text-xs text-muted-foreground">
            Arrastra para ordenar; la primera es la que se ve en la tienda.
        </p>
    </div>
</template>
