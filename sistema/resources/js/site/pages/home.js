import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import Lenis from "lenis";
import { createAutoplay } from "../carousel.js";
import { mountEmblem } from "../emblem.js";
import { currentMode, onModeChange, setMode } from "../theme.js";

gsap.registerPlugin(ScrollTrigger);
const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const mobile = window.matchMedia("(max-width: 860px)").matches;

/* Scroll suave. */
if (!reduced) {
    const lenis = new Lenis({ lerp: 0.09, prevent: () => false });
    lenis.on("scroll", ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);
}

let emblem = null;
mountEmblem({ canvas: document.getElementById("scene"), src: "/img/ats-emblema.png", scatterTrigger: "#que-hacemos", reduced, mobile }).then((api) => {
    emblem = api ?? null;
    emblem?.setPalette(currentMode());
});

/* ---------------- Hero con modos ---------------- */
const hero = document.querySelector(".hero");
const slides = [...document.querySelectorAll(".hero-slide")];
const tabs = [...document.querySelectorAll(".hero-mode")];
let index = 0;
const autoplay = createAutoplay(hero, () => show((index + 1) % slides.length), {
    progress: () => tabs[index].querySelector('.bar'),
    button: document.querySelector('[data-hero-pause]'),
    started: false,
});

/* Solo la diapositiva visible ocupa el título principal del documento. */
function titleLevel(slide, active) {
    const heading = slide.querySelector('.hero-h');
    const tag = active ? 'H1' : 'H2';
    if (heading.tagName === tag) return;
    const replacement = document.createElement(tag);
    replacement.className = heading.className;
    replacement.append(...heading.childNodes);
    heading.replaceWith(replacement);
}

const parts = (slide) => ({
    rows: slide.querySelectorAll(".post .row"),
    lines: slide.querySelectorAll(".line span"),
    rest: slide.querySelectorAll(".lede, .actions"),
});

/* Si ya hay un modo guardado (o ?modo= en el enlace), el hero arranca directo en él. */
index = Math.max(0, slides.findIndex((s) => s.dataset.mode === currentMode()));
slides.forEach((slide, i) => {
    titleLevel(slide, i === index);
    slide.classList.toggle("is-active", i === index);
    if (i === index) slide.removeAttribute("aria-hidden");
    else slide.setAttribute("aria-hidden", "true");
    slide.querySelectorAll("a").forEach((a) => (i === index ? a.removeAttribute("tabindex") : (a.tabIndex = -1)));
});
tabs.forEach((tab, i) => {
    tab.setAttribute("aria-selected", String(i === index));
    tab.tabIndex = i === index ? 0 : -1;
});

/* Arranque con la secuencia BIOS (la página en construcción iba en 72 %; aquí llega a 100 %). */
const first = parts(slides[index]);
const bar = slides[index].querySelector(".post .bar i");
const pct = slides[index].querySelector(".pct");
const progress = { value: 0 };
const intro = gsap.timeline({ defaults: { ease: "power3.out" }, onComplete: run })
    .to(first.rows, { opacity: 1, y: 0, duration: 0.45, stagger: reduced ? 0 : 0.24 }, reduced ? 0 : 0.4);
if (bar && pct) {
    intro.to(bar, { width: "100%", duration: reduced ? 0 : 1.5, ease: "power2.inOut" }, reduced ? 0 : 1.3)
        .to(progress, { value: 100, duration: reduced ? 0 : 1.5, ease: "power2.inOut", onUpdate: () => (pct.textContent = `${Math.round(progress.value)}%`) }, "<");
}
intro.to(first.lines, { y: "0%", duration: reduced ? 0 : 1.1, stagger: 0.12, ease: "expo.out" }, reduced ? 0 : bar ? 1.5 : 0.8)
    .to(first.rest, { opacity: 1, duration: 0.8, stagger: 0.1 }, "-=0.6")
    .to(".hero-modes, .hero-pause", { opacity: 1, duration: 0.6 }, "-=0.4");

/* Avance automático con la barra de progreso de la pestaña activa. */
function run() {
    autoplay.start();
}

function show(next, { user = false } = {}) {
    if (next === index) {
        if (user) setMode(slides[next].dataset.mode, { persist: true });
        return;
    }
    const from = slides[index];
    const to = slides[next];
    index = next;
    autoplay.reset();
    tabs.forEach((tab, i) => {
        tab.setAttribute("aria-selected", String(i === next));
        tab.tabIndex = i === next ? 0 : -1;
    });
    from.setAttribute("aria-hidden", "true");
    to.removeAttribute("aria-hidden");
    from.querySelectorAll("a").forEach((a) => (a.tabIndex = -1));
    to.querySelectorAll("a").forEach((a) => a.removeAttribute("tabindex"));

    // El sitio entero cambia de modo; solo se guarda si la persona lo eligió.
    setMode(to.dataset.mode, { persist: user });
    emblem?.setPalette(to.dataset.mode);

    const a = parts(from);
    const b = parts(to);
    const d = reduced ? 0 : 1;
    gsap.killTweensOf([...a.lines, ...a.rows, ...a.rest, ...b.lines, ...b.rows, ...b.rest]);
    gsap.timeline()
        .to(a.lines, { y: "-105%", duration: 0.45 * d, stagger: 0.05 * d, ease: "power3.in" })
        .to([...a.rows, ...a.rest], { opacity: 0, duration: 0.3 * d }, 0)
        .add(() => {
            titleLevel(from, false);
            titleLevel(to, true);
            from.classList.remove("is-active");
            to.classList.add("is-active");
            gsap.set(b.lines, { y: "105%" });
            gsap.set([...b.rows, ...b.rest], { opacity: 0, y: 10 });
            // La barra "Tu próxima PC" del modo ATS se vuelve a llenar cada vez que se entra al modo.
            const bar = to.querySelector(".post .bar i");
            const pct = to.querySelector(".pct");
            if (bar && pct) {
                const fill = { v: 0 };
                gsap.set(bar, { width: "0%" });
                gsap.to(fill, { v: 100, duration: 1.2 * d, delay: 0.3 * d, ease: "power2.inOut", onUpdate: () => {
                    bar.style.width = `${fill.v}%`;
                    pct.textContent = `${Math.round(fill.v)}%`;
                } });
            }
        })
        .to(b.rows, { opacity: 1, y: 0, duration: 0.35 * d, stagger: 0.09 * d })
        .to(b.lines, { y: "0%", duration: 0.9 * d, stagger: 0.1 * d, ease: "expo.out" }, d ? "-=0.25" : ">")
        .to(b.rest, { opacity: 1, y: 0, duration: 0.6 * d, stagger: 0.08 * d }, d ? "-=0.6" : ">");
}

tabs.forEach((tab, i) => {
    tab.addEventListener("click", () => show(i, { user: true }));
    tab.addEventListener("keydown", (event) => {
        const step = event.key === "ArrowRight" ? 1 : event.key === "ArrowLeft" ? -1 : 0;
        if (!step) return;
        event.preventDefault();
        const next = (i + step + tabs.length) % tabs.length;
        tabs[next].focus();
        show(next, { user: true });
    });
});

/* Deslizar en celular. */
let startX = null;
hero.addEventListener("pointerdown", (e) => (startX = e.pointerType === "mouse" ? null : e.clientX));
hero.addEventListener("pointerup", (e) => {
    if (startX === null) return;
    const dx = e.clientX - startX;
    startX = null;
    if (Math.abs(dx) > 50) show((index + (dx < 0 ? 1 : -1) + slides.length) % slides.length, { user: true });
});

/* El botón de modo del encabezado también mueve el hero. */
onModeChange((mode) => {
    const i = slides.findIndex((s) => s.dataset.mode === mode);
    if (i >= 0 && i !== index) show(i);
});

/* Encendido único y conteos del catálogo; el valor final siempre está en el HTML. */
const storefront = document.querySelector('.storefront');
if (storefront && !reduced && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);
            entry.target.classList.add('hud-online');
            entry.target.querySelectorAll('[data-count]').forEach(counter => {
                const final = Number(counter.dataset.count);
                const state = { value: 0 };
                gsap.to(state, { value: final, duration: 0.9, ease: 'power2.out', onUpdate: () => {
                    counter.textContent = Math.round(state.value).toLocaleString('es-MX');
                } });
            });
        });
    }, { threshold: 0.12 });
    storefront.querySelectorAll('.store-section, .rail-section').forEach(section => observer.observe(section));
}
if (storefront && !reduced && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    storefront.querySelectorAll('.p-card').forEach(card => {
        card.addEventListener('pointermove', event => {
            if (event.pointerType !== 'mouse') return;
            const box = card.getBoundingClientRect();
            const x = (event.clientX - box.left) / box.width;
            const y = (event.clientY - box.top) / box.height;
            card.style.setProperty('--rx', `${(0.5 - y) * 3}deg`);
            card.style.setProperty('--ry', `${(x - 0.5) * 3}deg`);
            card.style.setProperty('--mx', `${x * 100}%`);
            card.style.setProperty('--my', `${y * 100}%`);
        });
        card.addEventListener('pointerleave', () => {
            card.style.setProperty('--rx', '0deg');
            card.style.setProperty('--ry', '0deg');
        });
    });
}
