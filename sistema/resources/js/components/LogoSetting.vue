<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    type: 'site' | 'pdf' | 'icon';
    title: string;
    usage: string;
    hint: string;
    url: string;
    custom: boolean;
}>();

const form = useForm<{ image: File | null }>({ image: null });
const input = ref<HTMLInputElement | null>(null);
const removing = ref(false);

function upload(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) {
        return;
    }
    form.image = file;
    form.post(`/admin/settings/logos/${props.type}`, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            form.image = null;
            if (input.value) {
                input.value.value = '';
            }
        },
    });
}

function restore() {
    removing.value = true;
    router.delete(`/admin/settings/logos/${props.type}`, {
        preserveScroll: true,
        onFinish: () => (removing.value = false),
    });
}
</script>

<template>
    <article class="grid content-start gap-3 rounded-lg border bg-card p-4">
        <header>
            <div class="flex items-center justify-between gap-2">
                <h3 class="font-semibold">{{ title }}</h3>
                <span
                    class="rounded px-1.5 py-0.5 text-[11px] font-medium"
                    :class="
                        custom
                            ? 'bg-primary/10 text-primary'
                            : 'bg-muted text-muted-foreground'
                    "
                    >{{ custom ? 'Personalizado' : 'Predeterminado' }}</span
                >
            </div>
            <p class="mt-1 text-sm text-muted-foreground">{{ usage }}</p>
        </header>
        <div class="grid grid-cols-2 gap-2">
            <figure class="min-w-0">
                <div
                    class="flex h-24 items-center justify-center rounded-md border bg-white p-3"
                >
                    <img
                        :src="url"
                        alt=""
                        class="max-h-full max-w-full object-contain"
                    />
                </div>
                <figcaption class="mt-1 text-xs text-muted-foreground">
                    Sobre fondo claro
                </figcaption>
            </figure>
            <figure class="min-w-0">
                <div
                    class="flex h-24 items-center justify-center rounded-md bg-black p-3"
                >
                    <img
                        :src="url"
                        alt=""
                        class="max-h-full max-w-full object-contain"
                    />
                </div>
                <figcaption class="mt-1 text-xs text-muted-foreground">
                    Sobre fondo oscuro
                </figcaption>
            </figure>
        </div>
        <div
            v-if="type === 'icon'"
            class="flex items-center gap-2 rounded-md border px-2.5 py-1.5 text-xs text-muted-foreground"
        >
            <img :src="url" alt="" class="size-4 object-contain" />
            <span class="truncate">Así se ve en la pestaña del navegador</span>
        </div>
        <p class="text-xs text-muted-foreground">{{ hint }}</p>
        <p v-if="form.errors.image" class="text-sm text-destructive">
            {{ form.errors.image }}
        </p>
        <div class="flex flex-wrap gap-2">
            <input
                ref="input"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                class="sr-only"
                :aria-label="`Subir ${title.toLowerCase()}`"
                @change="upload"
            />
            <Button
                type="button"
                size="sm"
                :disabled="form.processing"
                @click="input?.click()"
            >
                {{ form.processing ? 'Subiendo…' : 'Subir imagen' }}
            </Button>
            <Button
                v-if="custom"
                type="button"
                size="sm"
                variant="outline"
                :disabled="removing"
                @click="restore"
            >
                Usar el predeterminado
            </Button>
        </div>
    </article>
</template>
