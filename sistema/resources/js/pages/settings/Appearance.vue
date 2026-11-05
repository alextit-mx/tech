<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/admin/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Apariencia',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Apariencia" />

    <h1 class="mb-6">Apariencia</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Apariencia"
            description="Elige cómo se ve el portal en este dispositivo"
        />
        <AppearanceTabs />
    </div>
</template>
