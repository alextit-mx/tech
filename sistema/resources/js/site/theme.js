/*
 * Modos del sitio (ATS, RGB, Frost, Amber). El que elige la persona se guarda y el <head> del layout lo aplica
 * antes de pintar; el hero del inicio los recorre sin guardarlos.
 */
export const MODES = ["ats", "rgb", "frost", "amber"];
const KEY = "tc-mode";
const listeners = new Set();

export function currentMode() {
    return document.documentElement.dataset.theme || "ats";
}

export function onModeChange(fn) {
    listeners.add(fn);
}

export function setMode(mode, { persist = false } = {}) {
    const next = MODES.includes(mode) ? mode : "ats";
    const root = document.documentElement;
    if (currentMode() !== next) {
        root.dataset.theme = next;
        sweep();
        listeners.forEach((fn) => fn(next));
    }
    if (persist) {
        try {
            localStorage.setItem(KEY, next);
        } catch {
            /* Sin almacenamiento: el modo dura solo esta página. */
        }
    }
}

/* Línea de luz que baja por la pantalla al cambiar de modo. */
function sweep() {
    let line = document.querySelector(".mode-sweep");
    if (!line) {
        line = document.createElement("div");
        line.className = "mode-sweep";
        line.setAttribute("aria-hidden", "true");
        document.body.append(line);
    }
    line.classList.remove("run");
    void line.offsetWidth;
    line.classList.add("run");
}
