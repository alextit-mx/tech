<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <img
        :src="page.props.branding?.icon ?? '/img/ats-emblema.png'"
        alt=""
        width="32"
        height="38"
        class="size-8 shrink-0 object-contain"
    />
    <div
        class="ml-1 grid flex-1 gap-0.5 text-left text-sm group-data-[collapsible=icon]:hidden"
    >
        <span class="truncate leading-tight font-semibold">Tech Computer</span>
        <span class="text-xs text-muted-foreground">Portal del personal</span>
    </div>
</template>
