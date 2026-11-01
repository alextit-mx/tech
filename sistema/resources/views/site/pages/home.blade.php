@extends('layouts.site')

@section('title', 'Tech Computer | PCs gamer, componentes y servicio técnico en Plaza de la Tecnología')
@section('description', 'Venta de PCs gamer y componentes, armado a la medida, mantenimiento, reparación y asesoría en Plaza de la Tecnología, Centro de la Ciudad de México. Arma tu PC en línea y cotiza por WhatsApp.')

@section('before')
<div class="backdrop" aria-hidden="true"></div>
<canvas id="scene" aria-hidden="true"></canvas>
<img class="fallback-emblem" src="/img/ats-emblema.png" alt="" />
@endsection

@section('content')
<main>
    <section class="hero">
        <div class="hero-copy">
            <div class="post" aria-label="Tu PC: procesador, tarjeta de video y memoria listos">
                <div class="row"><span class="label">Procesador</span><span class="ok">listo</span></div>
                <div class="row"><span class="label">Tarjeta de video</span><span class="ok">lista</span></div>
                <div class="row"><span class="label">Memoria RAM</span><span class="ok">lista</span></div>
                <div class="row"><span class="label">Tu próxima PC</span><span class="bar"><i></i></span><span class="pct">0%</span><span class="cursor"></span></div>
            </div>
            <h1>
                <span class="line"><span>Tu próxima PC gamer</span></span>
                <span class="line"><span>se arma aquí.</span></span>
            </h1>
            <p class="lede">Equipos y componentes, armado a la medida, mantenimiento, reparación y asesoría en nuestro local de Plaza de la Tecnología.</p>
            <div class="actions">
                <a class="btn btn-primary" href="/arma-tu-pc">
                    Arma tu PC
                    <span class="ic"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/cpu.svg')"></span></span>
                </a>
                <a class="btn btn-ghost" data-whats="Hola Tech Computer, quiero cotizar una PC gamer" href="https://wa.me/525549114435">
                    Cotizar por WhatsApp
                    <span class="ic"><span class="mask" style="--icon: url('https://cdn.jsdelivr.net/npm/simple-icons@13.21.0/icons/whatsapp.svg')"></span></span>
                </a>
            </div>
        </div>
    </section>

    <section class="section" id="que-hacemos">
        <h2>Todo lo que tu PC necesita, en un solo local</h2>
        <p class="intro">Vendemos, armamos, cuidamos y reparamos computadoras, con gente que juega y sabe lo que cada pieza aporta.</p>
        <ul class="sheet">
            <li>
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/monitor.svg')"></span></span>
                <h3>Equipos y componentes</h3>
                <div><p>PCs gamer completas, procesadores, tarjetas de video, memoria, almacenamiento y periféricos.</p><a class="more" href="/tienda">Ver equipos y componentes</a></div>
            </li>
            <li>
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/cpu.svg')"></span></span>
                <h3>Armado a la medida</h3>
                <div><p>Elige tus piezas con nuestro configurador o con nosotros; te la entregamos armada, probada y con Windows listo.</p><a class="more" href="/arma-tu-pc">Arma tu PC</a></div>
            </li>
            <li>
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/fan.svg')"></span></span>
                <h3>Mantenimiento</h3>
                <div><p>Limpieza a fondo, pasta térmica nueva y revisión de temperaturas para que no se caliente ni haga ruido.</p><a class="more" href="/servicio-tecnico#mantenimiento">Ver mantenimiento</a></div>
            </li>
            <li>
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/wrench.svg')"></span></span>
                <h3>Reparación</h3>
                <div><p>No enciende, se apaga, no da video o se traba: encontramos la falla y te decimos cómo resolverla.</p><a class="more" href="/servicio-tecnico#reparacion">Ver reparación</a></div>
            </li>
            <li>
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/rocket.svg')"></span></span>
                <h3>Actualización</h3>
                <div><p>Más memoria, un SSD NVMe o una tarjeta de video nueva para que tu equipo actual rinda más.</p><a class="more" href="/servicio-tecnico#actualizacion">Ver actualizaciones</a></div>
            </li>
            <li>
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/messages-square.svg')"></span></span>
                <h3>Asesoría</h3>
                <div><p>Te ayudamos a decidir qué comprar según lo que juegas o haces, sin venderte de más.</p><a class="more" href="/asesoria">Pedir asesoría</a></div>
            </li>
        </ul>
    </section>

    <section class="section split" id="arma">
        <div>
            <h2>Arma tu PC pieza por pieza</h2>
            <p class="intro">Dinos para qué la quieres y cuánto quieres invertir. Te proponemos una PC completa con componentes actuales, revisamos que todo sea compatible y la mandas por WhatsApp para cotizarla.</p>
            <div class="actions">
                <a class="btn btn-primary" href="/arma-tu-pc">
                    Abrir el configurador
                    <span class="ic"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/sliders-horizontal.svg')"></span></span>
                </a>
            </div>
        </div>
        <div class="panel">
            <div class="core">
                <h3>Ejemplo: PC para jugar en 1440p</h3>
                <div class="post" id="sample" aria-label="Ejemplo de PC recomendada"></div>
                <a class="more" href="/arma-tu-pc?uso=gaming1440&amp;presupuesto=equilibrada">Ajustarla a mi gusto</a>
            </div>
        </div>
    </section>

    <section class="section" id="niveles">
        <h2>PCs gamer por nivel</h2>
        <p class="intro">Cuatro puntos de partida con piezas actuales. Ábrelos en el configurador para cambiar lo que quieras.</p>
        <ol class="tiers" id="tiers"></ol>
    </section>

    <section class="section" id="tienda">
        <h2>La tienda</h2>
        <p class="intro">Componentes, periféricos gamer, accesorios, impresoras y consumibles con precio con IVA y existencias.</p>
        @include('site.partials.departments')
        @if ($featured->isNotEmpty())
            <div class="p-grid">
                @foreach ($featured as $product)
                    @include('site.partials.product-card')
                @endforeach
            </div>
        @endif
        <div class="actions"><a class="btn btn-ghost" href="/tienda">Ver toda la tienda</a></div>
    </section>

    <section class="section" id="proceso">
        <h2>Así armamos tu PC</h2>
        <ol class="steps">
            <li><h3>Elegimos contigo</h3><p>Con el configurador o en el local, según tus juegos, tus programas y tu presupuesto.</p></li>
            <li><h3>Revisamos y cotizamos</h3><p>Confirmamos compatibilidad y existencias, y te pasamos el precio del día.</p></li>
            <li><h3>Armamos</h3><p>Montaje, cableado ordenado y flujo de aire pensado para que no se caliente.</p></li>
            <li><h3>Probamos y entregamos</h3><p>Windows y controladores instalados, pruebas de temperatura y rendimiento antes de entregarla.</p></li>
        </ol>
    </section>
</main>

<section class="cta-band">
    <h2>¿Ya sabes qué quieres? Mándanos tu lista o visítanos.</h2>
    <div class="actions">
        <a class="btn btn-primary" data-whats="Hola Tech Computer, quiero cotizar" href="https://wa.me/525549114435">
            Cotizar por WhatsApp
            <span class="ic"><span class="mask" style="--icon: url('https://cdn.jsdelivr.net/npm/simple-icons@13.21.0/icons/whatsapp.svg')"></span></span>
        </a>
        <a class="btn btn-ghost" href="/contacto">
            Cómo llegar
            <span class="ic"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/map-pin.svg')"></span></span>
        </a>
    </div>
</section>
@endsection

@push('scripts')
    @vite('resources/js/site/pages/home.js')
@endpush
