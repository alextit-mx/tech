import { createAutoplay } from "./carousel.js";

/* Tienda: carruseles, pestañas de equipos armados, filtros sin recargar y el buscador con sugerencias (Vue, bajo demanda). */

const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

/* ---------------- Carruseles de productos ---------------- */
function initRails(root = document) {
    root.querySelectorAll("[data-rail]").forEach((rail) => {
        if (rail.dataset.ready) return;
        rail.dataset.ready = "1";
        const track = rail.querySelector("[data-rail-track]");
        const prev = rail.querySelector("[data-rail-prev]");
        const next = rail.querySelector("[data-rail-next]");
        const available = () => track.scrollWidth > track.clientWidth + 8;
        const go = (dir) => {
            const end = track.scrollWidth - track.clientWidth;
            const atEnd = track.scrollLeft >= end - 8;
            const atStart = track.scrollLeft < 8;
            const left = dir > 0 && atEnd ? 0 : dir < 0 && atStart ? end : track.scrollLeft + dir * track.clientWidth * 0.9;
            const wrapping = dir > 0 && atEnd || dir < 0 && atStart;
            track.scrollTo({ left, behavior: reduced || wrapping ? "instant" : "smooth" });
        };
        const autoplay = createAutoplay(rail, () => go(1), { available });
        const update = () => {
            prev.disabled = next.disabled = !available();
            autoplay.refresh();
        };
        prev.addEventListener("click", () => { go(-1); autoplay.reset(); });
        next.addEventListener("click", () => { go(1); autoplay.reset(); });
        track.addEventListener("scroll", update, { passive: true });
        window.addEventListener("resize", update);
        update();
    });
}

/* ---------------- Pestañas de equipos armados ---------------- */
function initBuildTabs() {
    document.querySelectorAll("[data-build-tabs]").forEach((box) => {
        const tabs = box.querySelectorAll("[data-tab]");
        tabs.forEach((tab) =>
            tab.addEventListener("click", () => {
                tabs.forEach((t) => t.setAttribute("aria-selected", String(t === tab)));
                box.querySelectorAll(".b-card").forEach((card) => {
                    card.hidden = Boolean(tab.dataset.tab) && card.dataset.use !== tab.dataset.tab;
                });
            }),
        );
    });
}

/* ---------------- Listados (categoría y búsqueda) sin recargar ---------------- */
let controller;
async function loadListing(url, push = true) {
    const listing = document.querySelector("[data-listing]");
    if (!listing) return (location.href = url);
    controller?.abort();
    controller = new AbortController();
    listing.setAttribute("aria-busy", "true");
    try {
        const res = await fetch(url, { headers: { Accept: "text/html" }, signal: controller.signal });
        if (!res.ok) throw new Error(String(res.status));
        const doc = new DOMParser().parseFromString(await res.text(), "text/html");
        const fresh = doc.querySelector("[data-listing]");
        if (!fresh) throw new Error("sin listado");
        const open = listing.querySelector(".filter-disclosure")?.open;
        listing.innerHTML = fresh.innerHTML;
        const disclosure = listing.querySelector(".filter-disclosure");
        if (disclosure && open !== undefined) disclosure.open = open;
        document.title = doc.title;
        if (push) history.pushState({ listing: true }, "", url);
        const top = listing.querySelector(".shop-layout, .p-grid")?.getBoundingClientRect().top ?? 0;
        if (top < 0) window.scrollBy({ top: top - 100, behavior: reduced ? "auto" : "smooth" });
        initRails(listing);
        mountSuggest();
    } catch (error) {
        if (error.name !== "AbortError") location.href = url;
    } finally {
        listing.removeAttribute("aria-busy");
    }
}

function initListing() {
    const listing = document.querySelector("[data-listing]");
    if (!listing) return;
    history.replaceState({ listing: true }, "");
    listing.addEventListener("click", (event) => {
        const link = event.target.closest("a.chip, .pager a");
        if (!link || event.metaKey || event.ctrlKey || event.shiftKey || link.origin !== location.origin) return;
        event.preventDefault();
        loadListing(link.href);
    });
    const submit = (form) => {
        const params = new URLSearchParams(new FormData(form));
        [...params.keys()].forEach((key) => params.get(key) === "" && params.delete(key));
        const target = new URL(form.action || location.href);
        target.search = params.toString();
        target.hash = "";
        loadListing(target.href);
    };
    listing.addEventListener("submit", (event) => {
        const form = event.target;
        if (form.method !== "get") return;
        event.preventDefault();
        submit(form);
    });
    // Casillas y orden se aplican al instante; el precio al salir del campo o con Enter.
    listing.addEventListener("change", (event) => {
        const form = event.target.closest(".filters form, .search-filters");
        if (form) submit(form);
    });
    window.addEventListener("popstate", (event) => {
        if (event.state?.listing) loadListing(location.href, false);
    });
}

/* ---------------- Buscador con sugerencias (Vue se carga hasta que alguien enfoca un buscador) ---------------- */
let suggest;
function mountSuggest() {
    if (!suggest) return;
    suggest.then(({ mountAll }) => mountAll());
}
document.addEventListener(
    "focusin",
    function load(event) {
        if (!event.target.matches?.("[data-suggest]")) return;
        document.removeEventListener("focusin", load);
        suggest = import("./suggest.ts");
        suggest.then(({ mountAll, open }) => {
            mountAll();
            open(event.target);
        });
    },
);

initRails();
initBuildTabs();
initListing();
