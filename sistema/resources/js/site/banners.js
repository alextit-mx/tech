import { createAutoplay } from "./carousel.js";

/* Promociones vigentes: flechas, puntos, avance automático y deslizar. */
const root = document.querySelector("[data-banners]");
if (root) {
    const slides = [...root.querySelectorAll(".banner")];
    const dots = [...root.querySelectorAll("[data-banners-dot]")];
    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    let index = 0;

    const show = (next) => {
        index = (next + slides.length) % slides.length;
        slides.forEach((slide, i) => {
            const active = i === index;
            slide.classList.toggle("is-active", active);
            slide.toggleAttribute("aria-hidden", !active);
            slide.querySelectorAll("a").forEach((a) => (active ? a.removeAttribute("tabindex") : (a.tabIndex = -1)));
        });
        dots.forEach((dot, i) => dot.setAttribute("aria-selected", String(i === index)));
    };
    const autoplay = createAutoplay(root, () => show(index + 1), { available: () => slides.length > 1 });
    const select = next => { show(next); autoplay.reset(); };
    root.querySelector("[data-banners-prev]")?.addEventListener("click", () => select(index - 1));
    root.querySelector("[data-banners-next]")?.addEventListener("click", () => select(index + 1));
    dots.forEach((dot, i) => dot.addEventListener("click", () => select(i)));

    let start = null;
    root.addEventListener("pointerdown", (e) => {
        start = e.pointerType === "mouse" ? null : { x: e.clientX, y: e.clientY };
    });
    root.addEventListener("pointercancel", () => (start = null));
    root.addEventListener("pointerup", (e) => {
        if (start === null) return;
        const dx = e.clientX - start.x;
        const dy = e.clientY - start.y;
        start = null;
        if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) select(index + (dx < 0 ? 1 : -1));
    });
    const canvas = root.querySelector("[data-banner-particles]");
    if (canvas && canvas.getClientRects().length && !reduced) particles(canvas, root);
}

/* Chispas que suben desde la base del banner, con el color del modo activo (también el arcoíris del modo RGB). */
function particles(canvas, root) {
    const ctx = canvas.getContext("2d");
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const sprites = {};
    let w = 0;
    let h = 0;
    let list = [];
    let raf = 0;
    let visible = true;
    let frame = 0;
    let tint = "";

    // Brillo suave pre-dibujado (más barato que un gradiente por partícula).
    const sprite = (color) => {
        const c = document.createElement("canvas");
        c.width = c.height = 64;
        const g = c.getContext("2d");
        const glow = g.createRadialGradient(32, 32, 0, 32, 32, 32);
        glow.addColorStop(0, "rgba(255,255,255,1)");
        glow.addColorStop(0.18, color);
        glow.addColorStop(1, "rgba(0,0,0,0)");
        g.fillStyle = glow;
        g.fillRect(0, 0, 64, 64);
        return c;
    };
    const readTint = () => {
        canvas.style.color = "var(--red)";
        const red = getComputedStyle(canvas).color;
        canvas.style.color = "var(--red-hot)";
        const hot = getComputedStyle(canvas).color;
        if (red + hot === tint) return;
        tint = red + hot;
        sprites.red = sprite(red);
        sprites.hot = sprite(hot);
    };
    const spawn = (anywhere = false) => ({
        x: Math.random() * w,
        y: anywhere ? h * (0.35 + Math.random() * 0.65) : h + Math.random() * 20 * dpr,
        vy: -(0.25 + Math.random() * 0.9) * dpr,
        drift: Math.random() * Math.PI * 2,
        size: (Math.random() < 0.15 ? 16 + Math.random() * 20 : 5 + Math.random() * 9) * dpr,
        life: 0,
        max: 180 + Math.random() * 260,
        hot: Math.random() < 0.35,
        flicker: 0.6 + Math.random() * 0.4,
    });
    const resize = () => {
        const box = canvas.getBoundingClientRect();
        w = canvas.width = Math.round(box.width * dpr);
        h = canvas.height = Math.round(box.height * dpr);
        const count = Math.max(36, Math.min(150, Math.round(box.width / 10)));
        list = Array.from({ length: count }, () => spawn(true));
    };
    const tick = () => {
        raf = 0;
        if (!visible || document.hidden) return;
        if (frame++ % 30 === 0) readTint();
        ctx.clearRect(0, 0, w, h);
        ctx.globalCompositeOperation = "lighter";
        for (const p of list) {
            p.life++;
            p.y += p.vy;
            p.x += Math.sin(p.drift + p.life * 0.02) * 0.35 * dpr;
            const t = p.life / p.max;
            if (t >= 1 || p.y < -p.size) {
                Object.assign(p, spawn());
                continue;
            }
            // Aparece, brilla y se apaga mientras sube (más tenue cerca del borde superior del canvas).
            ctx.globalAlpha = Math.sin(Math.PI * t) * p.flicker * (0.55 + 0.45 * Math.sin(p.life * 0.15 + p.drift)) * Math.min(1, p.y / (h * 0.35));
            ctx.drawImage(p.hot ? sprites.hot : sprites.red, p.x - p.size / 2, p.y - p.size / 2, p.size, p.size);
        }
        ctx.globalAlpha = 1;
        raf = requestAnimationFrame(tick);
    };
    const start = () => {
        if (!raf && visible && !document.hidden) raf = requestAnimationFrame(tick);
    };

    readTint();
    resize();
    new ResizeObserver(resize).observe(canvas);
    new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        start();
    }).observe(root);
    document.addEventListener("visibilitychange", start);
    start();
}
