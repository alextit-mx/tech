/* PCs por nivel (inicio y equipos): salen del mismo motor que "Arma tu PC". */
import { byId, queryFor, recommend } from "./armado.js";

export const GAMING = [
    { use: "gaming1080", budget: "esencial", title: "Esencial", for: "Para jugar en 1080p" },
    { use: "gaming1440", budget: "equilibrada", title: "Equilibrada", for: "Para jugar en 1440p" },
    { use: "gaming1440", budget: "alta", title: "Alta", for: "1440p en ultra por años" },
    { use: "gaming4k", budget: "extrema", title: "Extrema", for: "4K sin concesiones" },
];

export const OTHERS = [
    { use: "stream", budget: "alta", title: "Para transmitir", for: "Jugar y transmitir a la vez" },
    { use: "create", budget: "alta", title: "Para crear", for: "Edición de video, 3D e IA" },
    { use: "office", budget: "esencial", title: "Escuela y oficina", for: "Sin tarjeta de video" },
];

const SPECS = [
    ["cpu", "Procesador"],
    ["gpu", "Video"],
    ["ram", "Memoria"],
    ["storage", "Disco"],
];

export function renderTiers(list, levels) {
    list.innerHTML = levels
        .map((level) => {
            const build = recommend(level.use, level.budget);
            const specs = SPECS.map(([id, label]) => {
                const part = byId(id, build[id]);
                const name = id === "gpu" && part?.id === "none" ? "Gráficos del procesador" : part?.name ?? "—";
                return `<li><span class="k">${label}</span><span class="v">${name}</span></li>`;
            }).join("");
            return `
                <li>
                    <div>
                        <h3>${level.title}</h3>
                        <p class="for">${level.for}</p>
                    </div>
                    <ul class="specs">${specs}</ul>
                    <a class="btn btn-ghost btn-sm" href="arma-tu-pc.html?${queryFor(build, level.use, level.budget)}">
                        Ver y ajustar
                        <span class="ic"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/sliders-horizontal.svg')"></span></span>
                    </a>
                </li>`;
        })
        .join("");
}
