<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const logo = computed(
    () => page.props.branding?.siteLogo ?? '/img/ats-computer.png',
);
const emblem = computed(
    () => page.props.branding?.icon ?? '/img/ats-emblema.png',
);
</script>

<template>
    <!-- Siempre oscuro, con la identidad del sitio: tinta, rojo ATS, emblema y Chakra Petch. -->
    <div
        class="auth-brand dark"
        style="--primary: #e30613; --primary-foreground: #fff; --ring: #e30613"
    >
        <div
            class="relative grid min-h-svh bg-[#070608] text-white lg:grid-cols-[1.1fr_1fr]"
        >
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_22%_30%,rgba(227,6,19,0.12),transparent_55%),radial-gradient(ellipse_at_85%_95%,rgba(227,6,19,0.1),transparent_45%)]"
            />
            <aside
                class="relative hidden flex-col justify-between overflow-hidden border-r border-white/10 p-12 lg:flex"
            >
                <a href="/" class="block w-44"
                    ><img :src="logo" alt="Tech Computer, ir al sitio"
                /></a>
                <img
                    :src="emblem"
                    alt=""
                    class="mx-auto w-64 drop-shadow-[0_0_24px_rgba(227,6,19,0.18)] xl:w-80"
                />
                <div>
                    <p
                        class="font-['Chakra_Petch'] text-xs font-semibold tracking-[0.3em] text-[#E30613] uppercase"
                    >
                        Portal interno
                    </p>
                    <p
                        class="mt-3 max-w-md font-['Chakra_Petch'] text-3xl leading-tight font-bold"
                    >
                        Cotizaciones, catálogo, servicio y garantías en un solo
                        lugar.
                    </p>
                </div>
            </aside>
            <main class="relative flex items-center justify-center p-6 sm:p-10">
                <div class="w-full max-w-sm">
                    <a href="/" class="mb-10 block w-36 lg:hidden"
                        ><img :src="logo" alt="Tech Computer, ir al sitio"
                    /></a>
                    <p
                        class="font-['Chakra_Petch'] text-xs font-semibold tracking-[0.3em] text-[#E30613] uppercase"
                    >
                        Acceso para personal
                    </p>
                    <h1 class="mt-2 font-['Chakra_Petch'] text-3xl font-bold">
                        {{ title }}
                    </h1>
                    <p v-if="description" class="mt-2 text-sm text-white/60">
                        {{ description }}
                    </p>
                    <div
                        class="mt-8 bg-white/[0.035] p-6 shadow-[inset_0_0_0_1px_rgba(255,255,255,0.09)] [clip-path:polygon(0_0,calc(100%-14px)_0,100%_14px,100%_100%,14px_100%,0_calc(100%-14px))]"
                    >
                        <slot />
                    </div>
                    <a
                        href="/"
                        class="mt-8 inline-block text-sm text-white/60 underline-offset-4 hover:text-white hover:underline"
                        >← Volver al sitio</a
                    >
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
.auth-brand :deep(button),
.auth-brand a,
.auth-brand :deep(input) {
    transition:
        color 160ms,
        background-color 160ms,
        border-color 160ms,
        box-shadow 160ms;
}
.auth-brand a:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 6px;
}
@media (prefers-reduced-motion: reduce) {
    .auth-brand :deep(*) {
        transition: none;
    }
}
</style>
