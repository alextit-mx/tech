import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createAutoplay } from './carousel.js';

class CarouselRoot extends EventTarget {
    dataset: Record<string, string> = {};
    querySelector() { return null; }
    contains(target: EventTarget | null) { return target === this; }
}

describe('reloj de carruseles', () => {
    let root: CarouselRoot;
    let doc: EventTarget & { hidden: boolean };
    let motion: EventTarget & { matches: boolean };
    const pointer = (type: string, pointerType: string) => Object.assign(new Event(type), { pointerType });

    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'performance'] });
        root = new CarouselRoot();
        doc = Object.assign(new EventTarget(), { hidden: false });
        motion = Object.assign(new EventTarget(), { matches: false });
        vi.stubGlobal('document', doc);
        vi.stubGlobal('window', { matchMedia: () => motion });
    });
    afterEach(() => { vi.useRealTimers(); vi.unstubAllGlobals(); });

    it('avanza cada 4.5 segundos y conserva el tiempo restante al pasar el mouse', () => {
        const next = vi.fn();
        createAutoplay(root, next);
        vi.advanceTimersByTime(4500);
        expect(next).toHaveBeenCalledTimes(1);
        vi.advanceTimersByTime(1500);
        root.dispatchEvent(pointer('pointerenter', 'mouse'));
        vi.advanceTimersByTime(10000);
        expect(next).toHaveBeenCalledTimes(1);
        root.dispatchEvent(pointer('pointerleave', 'mouse'));
        vi.advanceTimersByTime(2999);
        expect(next).toHaveBeenCalledTimes(1);
        vi.advanceTimersByTime(1);
        expect(next).toHaveBeenCalledTimes(2);
    });

    it('no reanuda con el mouse fuera mientras el teclado conserva el foco', () => {
        const next = vi.fn();
        createAutoplay(root, next);
        root.dispatchEvent(new Event('focusin'));
        root.dispatchEvent(pointer('pointerenter', 'mouse'));
        root.dispatchEvent(pointer('pointerleave', 'mouse'));
        vi.advanceTimersByTime(9000);
        expect(next).not.toHaveBeenCalled();
        root.dispatchEvent(Object.assign(new Event('focusout'), { relatedTarget: null }));
        vi.advanceTimersByTime(4500);
        expect(next).toHaveBeenCalledTimes(1);
    });

    it('detiene el avance al tocar en celular', () => {
        const next = vi.fn();
        createAutoplay(root, next);
        root.dispatchEvent(pointer('pointerdown', 'touch'));
        root.dispatchEvent(pointer('pointerup', 'touch'));
        vi.advanceTimersByTime(20000);
        expect(next).not.toHaveBeenCalled();
        expect(root.dataset.autoplayState).toBe('paused');
    });

    it('pausa la pestaña oculta sin recuperar avances acumulados al volver', () => {
        const next = vi.fn();
        createAutoplay(root, next);
        vi.advanceTimersByTime(1000);
        doc.hidden = true;
        doc.dispatchEvent(new Event('visibilitychange'));
        vi.advanceTimersByTime(20000);
        expect(next).not.toHaveBeenCalled();
        doc.hidden = false;
        doc.dispatchEvent(new Event('visibilitychange'));
        vi.advanceTimersByTime(3500);
        expect(next).toHaveBeenCalledTimes(1);
    });

    it('respeta reducir movimiento al iniciar y al cambiar la preferencia', () => {
        const next = vi.fn();
        motion.matches = true;
        createAutoplay(root, next);
        vi.advanceTimersByTime(10000);
        expect(next).not.toHaveBeenCalled();
        motion.matches = false;
        motion.dispatchEvent(new Event('change'));
        vi.advanceTimersByTime(4500);
        expect(next).toHaveBeenCalledTimes(1);
        motion.matches = true;
        motion.dispatchEvent(new Event('change'));
        vi.advanceTimersByTime(10000);
        expect(next).toHaveBeenCalledTimes(1);
    });

    it('no avanza cuando todas las tarjetas ya caben en el riel', () => {
        const next = vi.fn();
        createAutoplay(root, next, { available: () => false });
        vi.advanceTimersByTime(10000);
        expect(next).not.toHaveBeenCalled();
    });
});
