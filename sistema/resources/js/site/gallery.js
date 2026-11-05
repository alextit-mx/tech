import { createAutoplay } from "./carousel.js";

/* Galería: fotos completas, miniaturas, deslizar y pantalla completa sin ampliar imágenes pequeñas. */
const gallery = document.querySelector("[data-gallery]");
if (gallery) {
    const stage = gallery.querySelector("[data-gallery-stage]");
    const slides = [...stage.querySelectorAll(".g-slide")];
    const thumbs = [...gallery.querySelectorAll("[data-gallery-go]")];
    const strip = gallery.querySelector(".gallery-thumbs");
    const counter = gallery.querySelector("[data-gallery-index]");
    const box = gallery.querySelector("[data-lightbox]");
    const big = box?.querySelector("[data-lightbox-img]");
    const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    let index = 0;

    const src = () => slides[index].querySelector("img").src;
    const show = (next) => {
        index = (next + slides.length) % slides.length;
        slides.forEach((slide, i) => {
            slide.classList.toggle("is-active", i === index);
            slide.toggleAttribute("aria-hidden", i !== index);
        });
        thumbs.forEach((thumb, i) => thumb.setAttribute("aria-selected", String(i === index)));
        // Centra la miniatura activa sin mover la página.
        const thumb = thumbs[index];
        if (strip && thumb) strip.scrollTo({ left: thumb.offsetLeft - strip.clientWidth / 2 + thumb.clientWidth / 2, behavior: reduced ? "auto" : "smooth" });
        if (counter) counter.textContent = String(index + 1);
        if (box?.open) big.src = src();

    };
    const autoplay = createAutoplay(gallery, () => show(index + 1), { available: () => slides.length > 1 });
    const step = (dir) => { show(index + dir); autoplay.reset(); };

    gallery.querySelector("[data-gallery-prev]")?.addEventListener("click", (e) => { e.stopPropagation(); step(-1); });
    gallery.querySelector("[data-gallery-next]")?.addEventListener("click", (e) => { e.stopPropagation(); step(1); });
    thumbs.forEach((thumb, i) => thumb.addEventListener("click", () => { show(i); autoplay.reset(); }));

    /* Deslizar en celular. */
    let startX = null;
    stage.addEventListener("pointerdown", (e) => (startX = e.pointerType === "mouse" ? null : e.clientX));
    stage.addEventListener("pointerup", (e) => {
        if (startX === null) return;
        const dx = e.clientX - startX;
        startX = null;
        if (Math.abs(dx) > 40) {
            stage.dataset.swiped = "1";
            step(dx < 0 ? 1 : -1);
        }
    });

    /* Pantalla completa. */
    if (box && big) {
        const open = () => {
            big.src = src();
            big.alt = slides[index].querySelector("img").alt;
            box.classList.remove("is-zoomed");
            box.showModal();
            autoplay.block("lightbox", true);
        };
        stage.addEventListener("click", (e) => {
            if (e.target.closest("button:not([data-gallery-open])")) return;
            if (stage.dataset.swiped) return delete stage.dataset.swiped;
            open();
        });
        box.addEventListener("close", () => {
            box.classList.remove("is-zoomed");
            autoplay.block("lightbox", false);
        });
        box.querySelector("[data-lightbox-close]").addEventListener("click", () => box.close());
        box.querySelector("[data-lightbox-prev]")?.addEventListener("click", () => { box.classList.remove("is-zoomed"); show(index - 1); });
        box.querySelector("[data-lightbox-next]")?.addEventListener("click", () => { box.classList.remove("is-zoomed"); show(index + 1); });
        box.addEventListener("click", (e) => e.target === box && box.close());
        box.addEventListener("keydown", (e) => {
            if (e.key === "ArrowLeft" || e.key === "ArrowRight") {
                box.classList.remove("is-zoomed");
                show(index + (e.key === "ArrowLeft" ? -1 : 1));
            }
        });

    }

}
