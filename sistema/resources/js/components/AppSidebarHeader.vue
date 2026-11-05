<script setup lang="ts">
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="portal-header flex h-14 shrink-0 items-center justify-between gap-4 border-b border-sidebar-border px-6"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
            <span v-else class="text-sm text-muted-foreground">Mostrador</span>
        </div>
        <div class="flex items-center gap-3">
            <AppearanceTabs compact />
            <a
                href="/"
                class="text-sm text-muted-foreground hover:text-foreground"
                >Ver sitio público <span aria-hidden="true">↗</span></a
            >
        </div>
    </header>
</template>
