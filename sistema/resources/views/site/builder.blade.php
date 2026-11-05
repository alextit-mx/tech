@extends('layouts.site')

@section('title', 'Arma tu PC gamer en línea | Modo armado · Tech Computer')
@section('description', 'Arma tu PC gamer pieza por pieza con productos reales: compatibilidad, consumo y nivel de poder en vivo. Al terminar recibes tu cotización con folio y PDF.')
@section('body_class', 'builder-page')

@push('head')
<meta name="csrf-token" content="{{ csrf_token() }}" />
{{-- Estilos del loader en línea: debe verse antes de que llegue el CSS y el JS del configurador. --}}
<style>
    html.lab-loading { overflow: hidden; }
    .lab-loader {
        --p: 0; position: fixed; inset: 0; z-index: 200; display: grid; place-items: center; align-content: center; gap: 26px;
        background: radial-gradient(ellipse at 50% 42%, color-mix(in srgb, var(--red, #E30613) 22%, transparent), transparent 55%), #070608;
        color: #EAF2FF; font-family: "Chakra Petch", system-ui, sans-serif; transition: opacity 0.6s ease, visibility 0.6s;
    }
    .lab-loader::before { content: ""; position: absolute; inset: 0; pointer-events: none; opacity: 0.35;
        background: repeating-linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 0 1px, transparent 1px 3px); }
    .lab-loader__mark { position: relative; width: clamp(130px, 18vw, 190px); aspect-ratio: 400 / 474; }
    .lab-loader__mark img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; }
    .lab-loader__dim { opacity: 0.12; filter: grayscale(1) brightness(2); }
    .lab-loader__fill { clip-path: inset(calc((1 - var(--p)) * 100%) 0 0 0); filter: drop-shadow(0 0 18px color-mix(in srgb, var(--red, #E30613) 85%, transparent)); }
    .lab-loader__edge { position: absolute; left: -12%; right: -12%; height: 2px; top: calc((1 - var(--p)) * 100%);
        background: linear-gradient(90deg, transparent, #fff, transparent); box-shadow: 0 0 18px 4px var(--red, #E30613); opacity: calc(1 - var(--p) * 0.9); }
    .lab-loader__ring { position: absolute; inset: -22%; border-radius: 50%; border: 1px solid color-mix(in srgb, var(--red, #E30613) 45%, transparent);
        animation: lab-loader-ring 1.6s ease-out infinite; }
    .lab-loader__info { display: grid; justify-items: center; gap: 10px; width: min(320px, 78vw); }
    .lab-loader__brand { font-size: 11px; font-weight: 600; letter-spacing: 0.32em; color: #6E7480; text-transform: uppercase; }
    .lab-loader__pct { font-size: 34px; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1; }
    .lab-loader__bar { width: 100%; height: 3px; background: rgba(234, 242, 255, 0.08); overflow: hidden; }
    .lab-loader__bar i { display: block; height: 100%; width: 100%; transform-origin: left; transform: scaleX(var(--p));
        background: var(--red, #E30613); box-shadow: 0 0 12px var(--red, #E30613); }
    .lab-loader__msg { min-height: 1.2em; font-size: 13px; font-weight: 600; letter-spacing: 0.06em; color: #A9B0BA; }
    .lab-loader.done { opacity: 0; visibility: hidden; }
    .lab-loader.done .lab-loader__mark { transform: scale(1.12); filter: brightness(1.6); transition: transform 0.6s ease, filter 0.6s ease; }
    @keyframes lab-loader-ring { from { transform: scale(0.7); opacity: 0.9; } to { transform: scale(1.25); opacity: 0; } }
    @media (prefers-reduced-motion: reduce) { .lab-loader__ring { animation: none; opacity: 0.3; } .lab-loader__edge { display: none; } }
</style>
@endpush

@section('content')
<div class="lab-loader" data-lab-loader role="status" aria-live="polite">
    <div class="lab-loader__mark" aria-hidden="true">
        <span class="lab-loader__ring"></span>
        <img class="lab-loader__dim" src="/img/ats-emblema.png" alt="" />
        <img class="lab-loader__fill" src="/img/ats-emblema.png" alt="" />
        <span class="lab-loader__edge"></span>
    </div>
    <div class="lab-loader__info">
        <span class="lab-loader__brand">Tech Computer · Modo armado</span>
        <span class="lab-loader__pct" data-pct>0%</span>
        <span class="lab-loader__bar"><i></i></span>
        <span class="lab-loader__msg" data-msg>Cargando piezas…</span>
    </div>
</div>
<script>
    // Avanza solo hasta 92 % y espera a que el configurador avise que la escena está lista (evento build-lab:ready).
    (function () {
        var el = document.querySelector("[data-lab-loader]");
        if (!el) return;
        document.documentElement.classList.add("lab-loading");
        var pct = el.querySelector("[data-pct]");
        var msg = el.querySelector("[data-msg]");
        var steps = ["Cargando piezas…", "Calibrando RGB…", "Preparando la escena 3D…", "Encendiendo ventiladores…"];
        // El avance va por tiempo, no por cuadros: con la escena 3D trabajando detrás, los cuadros pueden ir lentos.
        var started = performance.now(), readyAt = 0, fromP = 0, p = 0, done = false;
        function paint() {
            el.style.setProperty("--p", (p / 100).toFixed(3));
            pct.textContent = Math.round(p) + "%";
            msg.textContent = p >= 100 ? "Listo" : steps[Math.min(steps.length - 1, Math.floor(p / 25))];
        }
        function progress(now) {
            if (!readyAt) return 92 * (1 - Math.exp(-(now - started) / 1100));
            return fromP + (100 - fromP) * Math.min(1, (now - readyAt) / 380);
        }
        function finish() {
            if (done) return;
            done = true;
            p = 100;
            paint();
            el.classList.add("done");
            document.documentElement.classList.remove("lab-loading");
            setTimeout(function () { el.remove(); window.dispatchEvent(new Event("build-lab:loaded")); }, 650);
        }
        function tick(now) {
            if (done) return;
            p = progress(now);
            paint();
            requestAnimationFrame(tick);
        }
        function ready() {
            if (readyAt) return;
            fromP = progress(performance.now());
            readyAt = performance.now();
            setTimeout(finish, 520);
        }
        window.addEventListener("build-lab:ready", function () {
            // Se muestra al menos un instante para que no parpadee en conexiones rápidas.
            setTimeout(ready, Math.max(0, 900 - (performance.now() - started)));
        }, { once: true });
        setTimeout(ready, 12000);
        requestAnimationFrame(tick);
    })();
</script>
<main class="build-main">
    <h1 class="sr-only">Arma tu PC gamer</h1>
    <div id="build-lab" @auth data-staff="1" @endauth>
        <noscript><p class="page">El configurador necesita JavaScript. Escríbenos por WhatsApp y armamos tu PC contigo.</p></noscript>
    </div>
    <script type="application/json" id="build-catalog">{!! json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
</main>
@endsection

@push('scripts')
    @vite('resources/js/builder/main.ts')
@endpush
