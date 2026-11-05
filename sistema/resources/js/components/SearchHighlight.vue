<script setup lang="ts">
import { computed } from 'vue';
const props = defineProps<{ text: string; query: string }>();
const parts = computed(() => {
    const normalize = (text: string) =>
        text
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
    const positions: number[] = [];
    let compact = '';
    Array.from(props.text).forEach((character, index) => {
        const value = normalize(character).replace(/[\s.-]/g, '');
        for (const letter of value) {
            compact += letter;
            positions.push(index);
        }
    });
    const marked = new Set<number>();
    for (const word of normalize(props.query).split(/\s+/)) {
        const token = word.replace(/[.-]/g, '');
        if (!token) continue;
        let start = compact.indexOf(token);
        while (start !== -1) {
            for (
                let i = positions[start];
                i <= positions[start + token.length - 1];
                i++
            )
                marked.add(i);
            start = compact.indexOf(token, start + token.length);
        }
    }
    const result: { text: string; match: boolean }[] = [];
    Array.from(props.text).forEach((character, index) => {
        const match = marked.has(index);
        if (result.length && result[result.length - 1].match === match)
            result[result.length - 1].text += character;
        else result.push({ text: character, match });
    });
    return result;
});
</script>
<template>
    <span
        ><template v-for="(part, index) in parts" :key="index"
            ><mark
                v-if="part.match"
                class="rounded-sm bg-amber-200 text-inherit dark:bg-amber-500/30"
                >{{ part.text }}</mark
            ><template v-else>{{ part.text }}</template></template
        ></span
    >
</template>
