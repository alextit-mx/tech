/* Un reloj por carrusel; conserva el tiempo restante durante cada pausa. */
/**
 * @param {*} root
 * @param {() => void} advance
 * @param {{available?: () => boolean, progress?: () => Element | null, button?: HTMLElement | null, started?: boolean}} options
 */
export function createAutoplay(root, advance, { available = () => true, progress = () => root.querySelector('[data-carousel-progress]'), button = root.querySelector('[data-carousel-pause]'), started = true } = {}) {
    const duration = Number(root.dataset.autoplayInterval) || 4500;
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const blocked = new Set();
    let remaining = duration;
    let since = 0;
    let timer = null;
    let animation = null;
    let manual = false;

    root.dataset.autoplayInterval = String(duration);
    const pause = () => {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
            remaining = Math.max(0, remaining - (performance.now() - since));
        }
        animation?.pause();
    };
    const sync = () => {
        if (button) {
            button.hidden = !available();
            button.disabled = motion.matches;
            button.setAttribute('aria-pressed', String(manual));
            button.setAttribute('aria-label', manual ? 'Reanudar carrusel' : 'Pausar carrusel');
            const label = button.querySelector('span');
            if (label) label.textContent = manual ? 'Reanudar' : 'Pausar';
        }
        const playing = started && available() && !motion.matches && !document.hidden && !manual && !blocked.size;
        root.dataset.autoplayState = playing ? 'playing' : 'paused';
        if (!playing) return pause();
        if (timer !== null) return;
        animation?.play();
        since = performance.now();
        timer = setTimeout(() => {
            timer = null;
            advance();
            reset();
        }, remaining);
    };
    const reset = () => {
        pause();
        remaining = duration;
        animation?.cancel();
        animation = progress()?.animate([{ transform: 'scaleX(0)' }, { transform: 'scaleX(1)' }], { duration, fill: 'forwards' }) ?? null;
        animation?.pause();
        sync();
    };
    const block = (reason, value) => {
        if (value) blocked.add(reason);
        else blocked.delete(reason);
        sync();
    };
    root.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse') block('hover', true); });
    root.addEventListener('pointerleave', () => block('hover', false));
    root.addEventListener('focusin', () => block('focus', true));
    root.addEventListener('focusout', event => { if (!root.contains(event.relatedTarget)) block('focus', false); });
    // Un toque detiene el avance hasta que la persona elija Reanudar.
    root.addEventListener('pointerdown', event => {
        if (event.pointerType === 'mouse' || button?.contains(event.target)) return;
        manual = true;
        sync();
    });
    button?.addEventListener('click', () => { manual = !manual; sync(); });
    document.addEventListener('visibilitychange', sync);
    motion.addEventListener('change', reset);
    reset();
    return { reset, block, refresh: sync, start: () => { started = true; reset(); } };
}
