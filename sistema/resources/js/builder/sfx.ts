/* Efectos de sonido sintetizados con WebAudio (sin archivos). Apagados por defecto; la preferencia se recuerda. */

const KEY = 'tc-build-sound';
let ctx: AudioContext | null = null;
let enabled = false;

try {
    enabled = localStorage.getItem(KEY) === '1';
} catch {
    enabled = false;
}

export function soundEnabled(): boolean {
    return enabled;
}

export function setSound(on: boolean): void {
    enabled = on;
    try {
        localStorage.setItem(KEY, on ? '1' : '0');
    } catch {
        /* Sin almacenamiento: vale solo para esta visita. */
    }
    if (on) tone(660, 0.08, 'triangle', 0.05);
}

function tone(
    freq: number,
    duration: number,
    type: OscillatorType,
    gain: number,
    delay = 0,
    slideTo?: number,
): void {
    if (!enabled) return;
    ctx ??= new AudioContext();
    const t = ctx.currentTime + delay;
    const osc = ctx.createOscillator();
    const amp = ctx.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(freq, t);
    if (slideTo)
        osc.frequency.exponentialRampToValueAtTime(slideTo, t + duration);
    amp.gain.setValueAtTime(0.0001, t);
    amp.gain.exponentialRampToValueAtTime(gain, t + 0.01);
    amp.gain.exponentialRampToValueAtTime(0.0001, t + duration);
    osc.connect(amp).connect(ctx.destination);
    osc.start(t);
    osc.stop(t + duration + 0.02);
}

export const sfx = {
    hover: () => tone(1800, 0.03, 'sine', 0.015),
    select: () => tone(520, 0.06, 'square', 0.025),
    equip: () => {
        tone(140, 0.18, 'sawtooth', 0.05, 0, 60);
        tone(880, 0.12, 'triangle', 0.04, 0.12, 1320);
    },
    remove: () => tone(400, 0.14, 'triangle', 0.035, 0, 160),
    error: () => {
        tone(220, 0.1, 'square', 0.03);
        tone(180, 0.14, 'square', 0.03, 0.1);
    },
    complete: () =>
        [523, 659, 784, 1047].forEach((f, i) =>
            tone(f, 0.5, 'triangle', 0.045, i * 0.11),
        ),
};
