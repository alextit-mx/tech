/* Monta las sugerencias (Vue) bajo cada buscador con data-suggest; se importa bajo demanda desde shop.js. */
import { createApp } from 'vue';
import SearchSuggest from './SearchSuggest.vue';

const openers = new WeakMap<Element, () => void>();

export function mountAll(): void {
    document.querySelectorAll<HTMLInputElement>('input[data-suggest]').forEach((input) => {
        if (openers.has(input)) return;
        openers.set(input, () => undefined);
        const host = document.createElement('div');
        host.className = 'suggest-host';
        input.after(host);
        createApp(SearchSuggest, { input, onReady: (open: () => void) => openers.set(input, open) }).mount(host);
    });
}

export function open(input: Element): void {
    openers.get(input)?.();
}
