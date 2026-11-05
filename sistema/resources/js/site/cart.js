/* Carrito de cotización: en el navegador solo se guardan ids y cantidades; los precios los da el servidor. */

const KEY = "tc-cart";

export function readCart() {
    try {
        const items = JSON.parse(localStorage.getItem(KEY) || "[]");
        return Array.isArray(items) ? items.filter((item) => item && item.id > 0) : [];
    } catch {
        return [];
    }
}

export function writeCart(items) {
    try {
        localStorage.setItem(KEY, JSON.stringify(items));
    } catch {
        /* Modo privado o almacenamiento bloqueado: el carrito vive solo en esta página. */
    }
    updateBadge(items);
}

export function addToCart(id, quantity = 1) {
    const items = readCart();
    const found = items.find((item) => item.id === id);
    if (found) found.qty = Math.min(99, found.qty + quantity);
    else items.push({ id, qty: Math.min(99, Math.max(1, quantity)) });
    writeCart(items);
}

export function setQuantity(id, quantity) {
    writeCart(readCart().map((item) => (item.id === id ? { ...item, qty: Math.min(99, Math.max(1, quantity)) } : item)));
}

export function removeFromCart(id) {
    writeCart(readCart().filter((item) => item.id !== id));
}

export function clearCart() {
    writeCart([]);
}

function updateBadge(items = readCart()) {
    const count = items.reduce((sum, item) => sum + item.qty, 0);
    document.querySelectorAll("[data-cart-count]").forEach((badge) => {
        badge.textContent = String(count);
        badge.hidden = count === 0;
    });
}

let toastTimer;
function toast(message) {
    let node = document.querySelector(".cart-toast");
    if (!node) {
        node = document.createElement("div");
        node.className = "cart-toast";
        node.setAttribute("role", "status");
        document.body.append(node);
    }
    node.innerHTML = `<span></span><a href="/carrito">Ver mi cotización</a>`;
    node.querySelector("span").textContent = message;
    node.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => node.classList.remove("show"), 3200);
}

/* El botón confirma en el mismo lugar ("✓ Agregado") además del aviso flotante. */
function confirmButton(button) {
    if (!button.dataset.label) button.dataset.label = button.textContent;
    button.textContent = "✓ Agregado";
    button.classList.add("is-added");
    clearTimeout(button._addedTimer);
    button._addedTimer = setTimeout(() => {
        button.textContent = button.dataset.label;
        button.classList.remove("is-added");
    }, 1800);
}

document.addEventListener("click", (event) => {
    const button = event.target.closest("[data-add-to-cart]");
    if (button) {
        const qtyInput = button.closest(".qty-row")?.querySelector("[data-qty]");
        addToCart(Number(button.dataset.addToCart), Number(qtyInput?.value) || 1);
        toast(`${button.dataset.name || "Producto"} agregado a tu cotización`);
        confirmButton(button);
        return;
    }
    // Equipo armado: todas sus piezas, una de cada una.
    const build = event.target.closest("[data-add-build]");
    if (!build) return;
    const ids = build.dataset.addBuild.split(",").map(Number).filter((id) => id > 0);
    ids.forEach((id) => addToCart(id, 1));
    toast(`${build.dataset.name || "Equipo"}: ${ids.length} piezas agregadas a tu cotización`);
    confirmButton(build);
});

/* La página de la cotización recién creada vacía el carrito. */
if (document.querySelector("[data-clear-cart]")) clearCart();
updateBadge();
