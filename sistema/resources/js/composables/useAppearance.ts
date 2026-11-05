import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { http } from '@/lib/http';
import { update as updatePreference } from '@/actions/App/Http/Controllers/Settings/AppearanceController';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };
const appearance = ref<Appearance>('system');
const systemDark = ref(false);
const error = ref('');
let saving = Promise.resolve();

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') return;
    systemDark.value = window.matchMedia(
        '(prefers-color-scheme: dark)',
    ).matches;
    document.documentElement.classList.toggle(
        'dark',
        document.documentElement.dataset.themeForcedDark === 'true' ||
            (value === 'system' ? systemDark.value : value === 'dark'),
    );
}

export function initializeTheme(): void {
    const saved = document.documentElement.dataset.appearance;
    appearance.value = saved === 'light' || saved === 'dark' ? saved : 'system';
    updateTheme(appearance.value);
    window
        .matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', () => updateTheme(appearance.value));
    router.on('navigate', (event) => {
        document.documentElement.dataset.themeForcedDark =
            event.detail.page.component.startsWith('auth/') ? 'true' : 'false';
        const user = event.detail.page.props.auth?.user as
            | { appearance?: Appearance }
            | undefined;
        if (user?.appearance) appearance.value = user.appearance;
        updateTheme(appearance.value);
    });
}

export function useAppearance() {
    const resolvedAppearance = computed<ResolvedAppearance>(() =>
        appearance.value === 'system'
            ? systemDark.value
                ? 'dark'
                : 'light'
            : appearance.value,
    );
    function updateAppearance(value: Appearance) {
        appearance.value = value;
        document.documentElement.dataset.appearance = value;
        document.cookie = `appearance=${value};path=/;max-age=31536000;SameSite=Lax`;
        updateTheme(value);
        // Serializa cambios rápidos para que el último también sea el último en el servidor.
        saving = saving.then(async () => {
            const result = await http('PATCH', updatePreference.url(), {
                appearance: value,
            });
            error.value = result.ok
                ? ''
                : 'No se pudo guardar la apariencia. Vuelve a elegirla para reintentar.';
        });
    }
    return { appearance, resolvedAppearance, updateAppearance, error };
}
