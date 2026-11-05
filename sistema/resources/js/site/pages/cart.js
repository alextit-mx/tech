import { readCart, removeFromCart, setQuantity } from "../cart.js";

const list = document.querySelector("[data-cart-list]");
const empty = document.querySelector("[data-cart-empty]");
const summary = document.querySelector("[data-cart-summary]");
const totalNode = document.querySelector("[data-cart-total]");
const pendingNode = document.querySelector("[data-cart-pending]");
const form = document.querySelector("[data-cart-form]");
const inputs = document.querySelector("[data-cart-inputs]");

const money = (n) => "$" + n.toLocaleString("es-MX", { maximumFractionDigits: 2 });
let products = new Map();

async function load() {
    const items = readCart();
    if (items.length === 0) return render();
    try {
        const response = await fetch(`/carrito/productos?ids=${items.map((item) => item.id).join(",")}`, { headers: { Accept: "application/json" } });
        products = new Map((await response.json()).map((p) => [p.id, p]));
    } catch {
        products = new Map();
    }
    /* Quita lo que ya no está publicado. */
    items.filter((item) => !products.has(item.id)).forEach((item) => removeFromCart(item.id));
    render();
}

function render() {
    const items = readCart().filter((item) => products.has(item.id));
    empty.hidden = items.length > 0;
    summary.hidden = items.length === 0;
    list.innerHTML = "";

    let total = 0;
    let pending = false;
    for (const item of items) {
        const p = products.get(item.id);
        if (p.price === null) pending = true;
        else total += p.price * item.qty;

        const row = document.createElement("li");
        row.className = "cart-row";
        row.innerHTML = `
            <a class="cart-thumb showcase ${!p.image || p.image.includes('producto-sin-foto') ? 'is-pending' : ''}" href="${p.url}">${p.image && !p.image.includes('producto-sin-foto') ? `<img src="${p.image}" alt="" width="72" height="72" loading="lazy" />` : `<span class="photo-pending"><img src="/img/ats-emblema.png" alt="" width="48" height="48" /><span>Foto pendiente</span></span>`}</a>
            <div class="cart-info"><a href="${p.url}"></a><small></small></div>
            <label class="cart-qty"><span class="sr-only">Cantidad</span><input type="number" min="1" max="99" value="${item.qty}" /></label>
            <span class="cart-price">${p.price === null ? "Por confirmar" : money(p.price * item.qty)}</span>
            <button class="cart-remove" type="button" aria-label="Quitar">×</button>`;
        row.querySelector(".cart-info a").textContent = p.name;
        row.querySelector(".cart-info small").textContent = `${p.sku} · ${p.stock}`;
        row.querySelector("input").addEventListener("change", (e) => {
            setQuantity(item.id, Number(e.target.value) || 1);
            render();
        });
        row.querySelector(".cart-remove").addEventListener("click", () => {
            removeFromCart(item.id);
            render();
        });
        list.append(row);
    }
    totalNode.firstChild.textContent = money(total);
    pendingNode.hidden = !pending;
}

form?.addEventListener("submit", () => {
    inputs.innerHTML = readCart()
        .map((item, i) => `<input type="hidden" name="items[${i}][product_id]" value="${item.id}" /><input type="hidden" name="items[${i}][quantity]" value="${item.qty}" />`)
        .join("");
});

load();
