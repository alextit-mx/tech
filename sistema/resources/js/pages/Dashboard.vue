<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
</script>
<template>
    <Head title="Inicio" />
    <div class="p-6">
        <p
            class="text-sm font-semibold tracking-widest text-[#E30613] uppercase"
        >
            Tech Computer
        </p>
        <h1 class="mt-2 text-3xl font-bold">Portal interno</h1>
        <p class="mt-2 text-muted-foreground">
            Administra productos, existencias y precios.
        </p>
        <a
            class="mt-6 inline-flex rounded-md bg-[#E30613] px-4 py-2 text-white"
            href="/admin/products"
            >Ir al catálogo</a
        >
    </div>
</template>
