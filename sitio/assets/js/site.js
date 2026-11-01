/* Tech Computer · lo que comparten todas las páginas. */

export const WHATSAPP = "525549114435";

/* Enlace de WhatsApp con el mensaje ya escrito. */
export function whatsappUrl(text) {
    return `https://wa.me/${WHATSAPP}?text=${encodeURIComponent(text)}`;
}

/* Copia al portapapeles y avisa a lectores de pantalla. */
export async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        return false;
    }
}

function announce(message) {
    let live = document.getElementById("live");
    if (!live) {
        live = document.createElement("p");
        live.id = "live";
        live.className = "sr-only";
        live.setAttribute("aria-live", "polite");
        document.body.append(live);
    }
    live.textContent = message;
}

/* Menú en celular. */
const toggle = document.querySelector(".nav-toggle");
const nav = document.getElementById("nav");
toggle?.addEventListener("click", () => {
    const open = nav.classList.toggle("open");
    toggle.setAttribute("aria-expanded", String(open));
    toggle.textContent = open ? "Cerrar" : "Menú";
});

/* Enlaces "data-whats": WhatsApp con el mensaje del enlace. */
document.querySelectorAll("[data-whats]").forEach((link) => {
    link.href = whatsappUrl(link.dataset.whats || "Hola Tech Computer");
    link.target = "_blank";
    link.rel = "noopener";
});

/* Botones "Copiar" (cuenta y CLABE). */
document.querySelectorAll(".copy[data-copy]").forEach((button) => {
    button.addEventListener("click", async () => {
        if (await copyText(button.dataset.copy)) {
            button.textContent = "Copiado";
            button.classList.add("done");
            announce("Copiado al portapapeles");
            setTimeout(() => {
                button.textContent = "Copiar";
                button.classList.remove("done");
            }, 1800);
        } else {
            button.textContent = "Selecciona y copia";
        }
    });
});

/* Año del pie. */
document.querySelectorAll("[data-year]").forEach((node) => (node.textContent = new Date().getFullYear()));

export { announce };
