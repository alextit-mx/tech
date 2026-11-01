/*
 * Arma tu PC: las piezas como ranuras de una tarjeta madre (con su LED) y
 * el resumen como la pantalla de arranque. Todo se guarda en la dirección
 * para compartir la configuración o mandarla por WhatsApp.
 */
import {
    BUDGETS, CATEGORIES, PARTS, USES, buildFromQuery, byId, partIssues, performance, powerDraw,
    queryFor, quoteText, recommend, recommendFor, recommendedPsu, review, totalPrice,
} from "./armado.js";
import { announce, copyText, whatsappUrl } from "./site.js";

const icon = (name) => `url('https://unpkg.com/lucide-static@0.469.0/icons/${name}.svg')`;
const money = (value) => value.toLocaleString("es-MX", { style: "currency", currency: "MXN", maximumFractionDigits: 0 });
const FEMININE = new Set(["board", "ram", "gpu", "psu"]);

const root = document.getElementById("builder");
const usesBox = document.getElementById("uses");
const budgetsBox = document.getElementById("budgets");
const hint = document.getElementById("hint");
const applyButton = document.getElementById("apply");

const fromUrl = buildFromQuery(location.search);
const state = {
    use: fromUrl.use ?? "gaming1440",
    budget: fromUrl.budget ?? "equilibrada",
    build: fromUrl.build,
    open: null,
    last: null,
    /* true cuando la persona ya cambió piezas: un nuevo uso no le borra lo elegido. */
    custom: false,
};
/* arma-tu-pc.html#gpu abre esa ranura. */
const anchor = location.hash.slice(1);
if (CATEGORIES.some(({ id }) => id === anchor)) state.open = anchor;
const suggested = recommend(state.use, state.budget);
state.custom = Object.keys(state.build).length > 0 && CATEGORIES.some(({ id }) => state.build[id] !== suggested[id]);
if (!Object.keys(state.build).length) state.build = suggested;

/* ---------------- Uso y presupuesto ---------------- */

function chips(box, name, options, current) {
    box.innerHTML = Object.entries(options)
        .map(([id, option]) => `
            <label class="chip">
                <input type="radio" name="${name}" value="${id}" ${id === current ? "checked" : ""} />
                <span>${option.label}</span>
            </label>`)
        .join("");
}

chips(usesBox, "uso", USES, state.use);
chips(budgetsBox, "presupuesto", BUDGETS, state.budget);

function onChoice(event) {
    const input = event.target;
    if (input.name === "uso") state.use = input.value;
    if (input.name === "presupuesto") state.budget = input.value;
    if (!state.custom) state.build = recommend(state.use, state.budget);
    render();
}
usesBox.addEventListener("change", onChoice);
budgetsBox.addEventListener("change", onChoice);

applyButton.addEventListener("click", () => {
    state.build = recommend(state.use, state.budget);
    state.custom = false;
    state.open = null;
    render();
    announce("Se armó la PC recomendada.");
});

/* ---------------- Ranuras ---------------- */

function detail(category, part) {
    switch (category) {
        case "cpu": return `${part.cores}, socket ${part.socket}${part.cooler ? ", incluye disipador" : ""}`;
        case "board": return `Socket ${part.socket}, ${part.mem}, formato ${part.form}${part.gen5 ? ", M.2 Gen5" : ""}`;
        case "ram": return part.mem === "DDR4" ? "Para plataformas DDR4" : "Para plataformas DDR5";
        case "gpu": return part.id === "none" ? "Usa los gráficos del procesador" : `${part.vram} GB de memoria, consumo cerca de ${part.power} W`;
        case "storage": return part.gen === 5 ? "PCIe 5.0" : "PCIe 4.0";
        case "cooler": return part.kind === "stock" ? "Solo si el procesador lo trae" : `Para procesadores de hasta ${part.maxPower} W`;
        case "psu": return part.atx31 ? "ATX 3.1 con cable 12V-2x6 para tarjetas nuevas" : "ATX";
        case "case": return `Tarjetas de hasta ${part.maxGpu} mm, radiador de hasta ${part.radiator} mm`;
        default: return "";
    }
}

function ledFor(category) {
    const part = byId(category, state.build[category]);
    if (!part) return "";
    const issues = partIssues(category, part, state.build, state.use);
    if (issues.some((issue) => issue.level === "error")) return "err";
    if (issues.length) return "warn";
    return "ok";
}

function optionsFor(category) {
    const recommended = recommendFor(category, state.build, state.use, state.budget)?.id;
    return PARTS[category]
        .map((part) => {
            const issues = partIssues(category, part, state.build, state.use);
            return { part, issues, blocked: issues.some((issue) => issue.level === "error"), recommended: part.id === recommended };
        })
        .sort((a, b) => Number(b.recommended) - Number(a.recommended) || Number(a.blocked) - Number(b.blocked) || a.part.tier - b.part.tier);
}

function optionHtml(category, { part, issues, blocked, recommended }) {
    const selected = state.build[category] === part.id;
    const first = issues.find((issue) => issue.level === "error") ?? issues[0];
    const why = blocked
        ? `<span class="why err">${first.text}</span>`
        : first
            ? `<span class="why warn">${first.text}</span>`
            : recommended && part.note
                ? `<span class="why rec">${part.note}</span>`
                : "";
    const side = recommended
        ? `<span class="badge">Recomendada</span>`
        : typeof part.precio === "number" ? `<span class="price">${money(part.precio)}</span>` : "";

    return `
        <li>
            <button class="option" type="button" data-category="${category}" data-id="${part.id}"
                aria-pressed="${selected}" ${blocked ? 'aria-disabled="true"' : ""}>
                <span>
                    <span class="name">${part.name}</span>
                    <span class="meta">${detail(category, part)}</span>
                    ${why}
                </span>
                ${side}
            </button>
        </li>`;
}

function slotHtml({ id, label, silk }) {
    const part = byId(id, state.build[id]);
    const open = state.open === id;
    return `
        <div class="slot ${open ? "open" : ""}" data-cat="${id}">
            <button class="slot-head" type="button" aria-expanded="${open}" aria-controls="opt-${id}">
                <span class="silk"><span class="led ${ledFor(id)}"></span>${silk}</span>
                <span class="slot-name">
                    <span class="cat">${label}</span>
                    <span class="part ${part ? "" : "empty"}">${part ? part.name : "Elige una opción"}</span>
                    ${part ? `<span class="detail">${detail(id, part)}</span>` : ""}
                </span>
                <span class="change">${part ? "Cambiar" : "Elegir"}<span class="mask" style="--icon: ${icon("chevron-down")}"></span></span>
            </button>
            <ul class="options" id="opt-${id}" ${open ? "" : "hidden"}>
                ${open ? optionsFor(id).map((option) => optionHtml(id, option)).join("") : ""}
            </ul>
        </div>`;
}

/* ---------------- Pantalla de arranque ---------------- */

function summaryHtml() {
    const result = review(state.build, state.use);
    const rows = CATEGORIES.map(({ id, label }) => {
        const led = ledFor(id);
        const status = !state.build[id] ? ["dim", "falta"]
            : led === "err" ? ["err", "no compatible"]
            : led === "warn" ? ["warn", "revisar"]
            : ["ok", FEMININE.has(id) ? "lista" : "listo"];
        return `<div class="row"><span class="label">${label}</span><span class="${status[0]}">${status[1]}</span></div>`;
    }).join("");

    const verdict = result.issues.length
        ? result.issues.map((issue) => {
            const level = issue.level === "error" ? "err" : "warn";
            const fix = issue.level === "error" ? fixButton(issue) : "";
            return `<li class="${level}"><span class="mask" style="--icon: ${icon(level === "err" ? "circle-x" : "triangle-alert")}"></span>
                <span>${issue.text}${fix}</span></li>`;
        }).join("")
        : result.missing.length
            ? `<li class="warn"><span class="mask" style="--icon: ${icon("triangle-alert")}"></span><span>Falta elegir: ${result.missing.map((c) => c.label.toLowerCase()).join(", ")}.</span></li>`
            : `<li class="ok"><span class="mask" style="--icon: ${icon("check")}"></span><span>Todo es compatible. Lista para cotizar.</span></li>`;

    const load = powerDraw(state.build);
    const psu = byId("psu", state.build.psu);
    const ideal = recommendedPsu(state.build);
    const percent = psu ? Math.min(100, Math.round((load / psu.watts) * 100)) : 0;
    const total = totalPrice(state.build);

    return `
        <h2>Revisión de tu PC</h2>
        <div class="post">${rows}</div>
        <ul class="verdict">${verdict}</ul>
        <div class="meter">
            <div class="row"><span>Consumo estimado</span><strong>${load} W</strong></div>
            <div class="track" aria-hidden="true"><i style="width: ${percent}%"></i></div>
            <div class="row"><span>${psu ? `Fuente de ${psu.watts} W` : "Sin fuente"}</span><span>Recomendada: ${ideal} W</span></div>
        </div>
        <div class="perf">
            ${performance(state.build).map((line) => `<p><span class="k">${line.label}</span>${line.text}</p>`).join("")}
        </div>
        ${total ? `<div class="total"><span>Total</span><span>${money(total)}</span></div>` : ""}
        <div class="actions">
            <a class="btn btn-primary" href="${whatsappUrl(quote())}" target="_blank" rel="noopener">
                Cotizar esta PC por WhatsApp
                <span class="ic"><span class="mask" style="--icon: url('https://cdn.jsdelivr.net/npm/simple-icons@13.21.0/icons/whatsapp.svg')"></span></span>
            </a>
            <div class="tools">
                <button class="btn btn-ghost btn-plain" type="button" data-tool="copy">Copiar lista</button>
                <button class="btn btn-ghost btn-plain" type="button" data-tool="share">Compartir enlace</button>
            </div>
        </div>
        <p class="toast" aria-live="polite"></p>`;
}

/* Choque entre dos piezas: se propone cambiar la que NO se acaba de elegir. */
function fixButton(issue) {
    const target = issue.between.find((category) => category !== state.last && state.build[category]) ?? issue.between[0];
    const without = { ...state.build, [target]: null };
    const option = recommendFor(target, without, state.use, state.budget);
    if (!option) return "";
    const label = CATEGORIES.find((category) => category.id === target).label.toLowerCase();
    return ` <button class="fix" type="button" data-fix="${target}" data-id="${option.id}">Cambiar ${label} por ${option.name}</button>`;
}

function shareUrl() {
    return `${location.origin}${location.pathname}?${queryFor(state.build, state.use, state.budget)}`;
}

function quote() {
    return `${quoteText(state.build, state.use, state.budget)}\n\nLa armé en: ${shareUrl()}`;
}

/* ---------------- Dibujo y eventos ---------------- */

function render(focusCategory = null) {
    hint.textContent = `${USES[state.use].hint} ${BUDGETS[state.budget].hint}`;
    applyButton.textContent = state.custom ? "Volver a la recomendada" : "Armar la recomendada";

    root.innerHTML = `
        <div class="board"><div class="pcb">${CATEGORIES.map(slotHtml).join("")}</div></div>
        <aside class="summary panel" aria-label="Revisión de tu PC"><div class="core">${summaryHtml()}</div></aside>`;

    history.replaceState(null, "", `?${queryFor(state.build, state.use, state.budget)}`);

    if (focusCategory) {
        root.querySelector(`[data-cat="${focusCategory}"] .slot-head`)?.focus();
    }
}

function toast(message) {
    const node = root.querySelector(".toast");
    if (node) node.textContent = message;
}

root.addEventListener("click", async (event) => {
    const head = event.target.closest(".slot-head");
    if (head) {
        const category = head.closest(".slot").dataset.cat;
        state.open = state.open === category ? null : category;
        render(category);
        return;
    }

    const option = event.target.closest(".option");
    if (option) {
        const { category, id } = option.dataset;
        if (option.getAttribute("aria-disabled") === "true") {
            toast("Esa opción no es compatible con lo que ya elegiste.");
            return;
        }
        state.build[category] = id;
        state.last = category;
        state.custom = true;
        state.open = null;
        render(category);
        announce(`${byId(category, id).name} elegida.`);
        return;
    }

    const fix = event.target.closest(".fix");
    if (fix) {
        state.build[fix.dataset.fix] = fix.dataset.id;
        state.last = fix.dataset.fix;
        state.custom = true;
        render(fix.dataset.fix);
        announce(`Se cambió por ${byId(fix.dataset.fix, fix.dataset.id).name}.`);
        return;
    }

    const tool = event.target.closest("[data-tool]");
    if (tool?.dataset.tool === "copy") {
        toast((await copyText(quote())) ? "Lista copiada." : "No se pudo copiar: selecciona el texto a mano.");
    }
    if (tool?.dataset.tool === "share") {
        if (navigator.share) {
            try {
                await navigator.share({ title: "Mi PC en Tech Computer", url: shareUrl() });
            } catch {
                /* La persona cerró el menú de compartir. */
            }
        } else {
            toast((await copyText(shareUrl())) ? "Enlace copiado." : "No se pudo copiar el enlace.");
        }
    }
});

render();
