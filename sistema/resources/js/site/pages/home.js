import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import Lenis from "lenis";
import { mountEmblem } from "../emblem.js";
import { byId, recommend } from "../armado.js";
import { GAMING, renderTiers } from "../niveles.js";

gsap.registerPlugin(ScrollTrigger);
const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const mobile = window.matchMedia("(max-width: 860px)").matches;

/* Niveles y el ejemplo del configurador salen del mismo motor. */
renderTiers(document.getElementById("tiers"), GAMING);
const sample = recommend("gaming1440", "equilibrada");
document.getElementById("sample").innerHTML = [
    ["cpu", "Procesador"], ["gpu", "Tarjeta de video"], ["ram", "Memoria"], ["storage", "Almacenamiento"], ["psu", "Fuente"],
].map(([id, label]) => `<div class="row"><span class="label">${label}</span><span class="ok">${byId(id, sample[id]).name}</span></div>`).join("");

/* Scroll suave. */
if (!reduced) {
    const lenis = new Lenis({ lerp: 0.09 });
    lenis.on("scroll", ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);
}

/* Arranque: la página en construcción iba en 72 %; aquí ya llega a 100 %. */
const rows = gsap.utils.toArray(".hero .post .row");
const pct = document.querySelector(".hero .post .pct");
const progress = { value: 0 };
gsap.timeline({ defaults: { ease: "power3.out" } })
    .to(rows, { opacity: 1, y: 0, duration: 0.45, stagger: reduced ? 0 : 0.24 }, reduced ? 0 : 0.4)
    .to(".hero .post .bar i", { width: "100%", duration: reduced ? 0 : 1.5, ease: "power2.inOut" }, reduced ? 0 : 1.3)
    .to(progress, { value: 100, duration: reduced ? 0 : 1.5, ease: "power2.inOut", onUpdate: () => (pct.textContent = `${Math.round(progress.value)}%`) }, "<")
    .to(".hero h1 .line span", { y: "0%", duration: reduced ? 0 : 1.1, stagger: 0.12, ease: "expo.out" }, reduced ? 0 : 1.5)
    .to(".hero .lede", { opacity: 1, duration: 0.8 }, "-=0.6")
    .to(".hero .actions", { opacity: 1, duration: 0.8 }, "-=0.5");

mountEmblem({ canvas: document.getElementById("scene"), src: "/img/ats-emblema.png", scatterTrigger: "#que-hacemos", reduced, mobile });
