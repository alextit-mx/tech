import { createApp } from 'vue';
import BuildLab from './BuildLab.vue';
import type { Extra, Part } from './rules';
import './builder.css';

const root = document.getElementById('build-lab');
const data = document.getElementById('build-catalog');

if (root && data) {
    const catalog = JSON.parse(data.textContent ?? '{}') as {
        parts: Part[];
        extras: Extra[];
    };
    createApp(BuildLab, { catalog }).mount(root);
}
