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
    @php
        $icon = fn (string $name, bool $brand = false) => '<span class="ic"><span class="mask" style="--icon: url(\''.($brand ? 'https://cdn.jsdelivr.net/npm/simple-icons@13.21.0/icons/' : 'https://unpkg.com/lucide-static@0.469.0/icons/').$name.'.svg\')"></span></span>';
        // Modos del hero: cada uno cambia el tema de la página (ver resources/css/modes.css).
        $modes = [
            ['mode' => 'rgb', 'tab' => 'RGB', 'post' => [['Iluminación', 'sincronizada'], ['Ventiladores', '3 × 120 mm'], ['Modo armado', 'en línea']],
                'title' => ['Arma tu PC', 'en modo RGB.'], 'lede' => 'Elige cada pieza, mira cómo encaja en 3D y llévate tu cotización con folio al instante.',
                'cta' => ['Entrar al modo armado', '/arma-tu-pc', 'cpu'], 'alt' => ['Ver tarjetas de video', '/tienda/tarjetas-de-video', 'gamepad-2']],
            ['mode' => 'frost', 'tab' => 'Creador', 'post' => [['Núcleos', '16'], ['Memoria', '64 GB'], ['Enfriamiento', 'líquido']],
                'title' => ['Tu PC para crear:', 'edición, 3D e IA.'], 'lede' => 'Estaciones con enfriamiento líquido y memoria de sobra para que el render no te detenga.',
                'cta' => ['PCs para creadores', '/arma-tu-pc?uso=create&presupuesto=alta', 'snowflake'], 'alt' => ['Pedir asesoría', '/asesoria', 'messages-square']],
            ['mode' => 'amber', 'tab' => 'Servicio', 'post' => [['Diagnóstico', 'en curso'], ['Temperaturas', 'revisando'], ['Tu equipo', 'en buenas manos']],
                'title' => ['¿Tu PC no enciende?', 'La revisamos hoy.'], 'lede' => 'Mantenimiento, reparación y actualizaciones en nuestro local de Plaza de la Tecnología.',
                'cta' => ['Servicio técnico', '/servicio-tecnico', 'wrench'], 'alt' => ['Mi equipo ya está aquí', '/estado', 'search']],
        ];
    @endphp
    <section class="hero" aria-roledescription="carrusel" aria-label="Modos de Tech Computer">
        <div class="hero-copy">
            <div class="hero-slides">
                <article class="hero-slide is-active" data-mode="ats" id="modo-ats" role="tabpanel" aria-roledescription="diapositiva" aria-label="1 de 4: ATS">
                    <div class="post" aria-label="Tu PC: procesador, tarjeta de video y memoria listos">
                        <div class="row"><span class="label">Procesador</span><span class="ok">listo</span></div>
                        <div class="row"><span class="label">Tarjeta de video</span><span class="ok">lista</span></div>
                        <div class="row"><span class="label">Memoria RAM</span><span class="ok">lista</span></div>
                        <div class="row"><span class="label">Tu próxima PC</span><span class="bar"><i></i></span><span class="pct">0%</span><span class="cursor"></span></div>
                    </div>
                    <h1 class="hero-h">
                        <span class="line"><span>Tu próxima PC</span></span>
                        <span class="line"><span>se arma aquí.</span></span>
                    </h1>
                    <p class="lede">Equipos y componentes, armado a la medida, mantenimiento, reparación y asesoría en nuestro local de Plaza de la Tecnología.</p>
                    <div class="actions">
                        <a class="btn btn-primary" href="/arma-tu-pc">Arma tu PC {!! $icon('cpu') !!}</a>
                        <a class="btn btn-ghost" data-whats="Hola Tech Computer, quiero cotizar una PC gamer" href="https://wa.me/525549114435">Cotizar por WhatsApp {!! $icon('whatsapp', true) !!}</a>
                    </div>
                </article>
                @foreach ($modes as $i => $m)
                    <article class="hero-slide" data-mode="{{ $m['mode'] }}" id="modo-{{ $m['mode'] }}" role="tabpanel" aria-roledescription="diapositiva" aria-label="{{ $i + 2 }} de 4: {{ $m['tab'] }}" aria-hidden="true">
                        <div class="post">
                            @foreach ($m['post'] as [$label, $value])
                                <div class="row"><span class="label">{{ $label }}</span><span class="ok">{{ $value }}</span></div>
                            @endforeach
                        </div>
                        <h2 class="hero-h">
                            <span class="line"><span>{{ $m['title'][0] }}</span></span>
                            <span class="line"><span>{{ $m['title'][1] }}</span></span>
                        </h2>
                        <p class="lede">{{ $m['lede'] }}</p>
                        <div class="actions">
                            <a class="btn btn-primary" href="{{ $m['cta'][1] }}" tabindex="-1">{{ $m['cta'][0] }} {!! $icon($m['cta'][2]) !!}</a>
                            <a class="btn btn-ghost" href="{{ $m['alt'][1] }}" tabindex="-1">{{ $m['alt'][0] }} {!! $icon($m['alt'][2]) !!}</a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="hero-modes" role="tablist" aria-label="Elegir modo">
                @foreach ([['ats', 'ATS', 'Gamer'], ['rgb', 'RGB', 'Bestia'], ['frost', 'Frost', 'Creador'], ['amber', 'Amber', 'Servicio']] as $i => [$mode, $tab, $name])
                    <button type="button" class="hero-mode" role="tab" data-mode="{{ $mode }}" aria-controls="modo-{{ $mode }}" aria-selected="{{ $i === 0 ? 'true' : 'false' }}" tabindex="{{ $i === 0 ? 0 : -1 }}">
                        <span>{{ $tab }}</span><b>{{ $name }}</b><span class="dot" aria-hidden="true"></span><span class="bar" aria-hidden="true"></span>
                    </button>
                @endforeach
            </div>
            <button type="button" class="hero-pause" data-hero-pause aria-pressed="false" aria-label="Pausar o reanudar el cambio automático de modo"><i aria-hidden="true"></i><span>Pausar</span></button>
        </div>
    </section>

    <div class="storefront" id="que-hacemos">
        @include('site.partials.trust')

        <section class="store-section" id="tienda">
            <header class="rail-head"><div><p class="eyebrow">01 / Departamentos</p><h2>Departamentos</h2></div><a class="rail-all" href="/tienda">Explorar tienda ↗</a></header>
            @include('site.partials.departments')
        </section>

        @if ($immediate->isNotEmpty())
            @include('site.partials.rail', ['id' => 'inmediata', 'eyebrow' => '02 / En el local · listo para llevar', 'title' => 'Entrega inmediata', 'link' => '/buscar?q=&inmediata=1', 'items' => $immediate])
        @endif
        @if ($featured->isNotEmpty())
            @include('site.partials.rail', ['id' => 'destacados', 'eyebrow' => '03 / Piezas para tu siguiente nivel', 'title' => 'Destacados', 'link' => '/tienda', 'items' => $featured])
        @endif

        @if ($builds->isNotEmpty())
            <section class="store-section" id="equipos">
                <header class="rail-head"><div><p class="eyebrow">04 / Tu próximo equipo</p><h2>Equipos armados</h2></div><a class="rail-all" href="/tienda#equipos">Ver equipos ↗</a></header>
                <div class="b-grid">@foreach ($builds as $build) @include('site.partials.build-card') @endforeach</div>
            </section>
        @endif

        <section class="lab-cta hud-panel store-section">
            <div class="lab-schematic" aria-hidden="true">
                <span class="lab-core">{!! $icon('cpu') !!}</span>
                <span class="lab-node node-gpu">{!! $icon('monitor') !!}</span>
                <span class="lab-node node-ram">{!! $icon('memory-stick') !!}</span>
                <span class="lab-node node-drive">{!! $icon('hard-drive') !!}</span>
                <span class="lab-circuit"></span>
            </div>
            <div><p class="eyebrow">05 / Modo armado · en línea</p><h2>Tu PC, pieza por pieza.</h2><p>Elige componentes. Revisa compatibilidad. Cotiza tu armado.</p></div>
            <a class="btn btn-primary" href="/arma-tu-pc">Arma tu PC {!! $icon('cpu') !!}</a>
        </section>

        <section class="store-section" aria-labelledby="support-title">
            <header class="rail-head"><div><p class="eyebrow">06 / Soporte en el local</p><h2 id="support-title">Estamos para tu PC.</h2></div></header>
            <div class="support-grid">
                <article class="support-panel hud-panel">
                    {!! $icon('wrench') !!}<p class="eyebrow">Servicio técnico</p><h3>Que vuelva al juego.</h3><p>Diagnóstico, mantenimiento y reparación.</p>
                    <a class="btn btn-ghost" data-whats="Hola Tech Computer, necesito servicio técnico para mi equipo" href="https://wa.me/{{ setting('whatsapp', '525549114435') }}?text={{ urlencode('Hola Tech Computer, necesito servicio técnico para mi equipo') }}">Consultar servicio ↗</a>
                </article>
                <article class="support-panel hud-panel">
                    {!! $icon('messages-square') !!}<p class="eyebrow">Asesoría</p><h3>Elige con nosotros.</h3><p>Piezas y actualizaciones para lo que necesitas.</p>
                    <a class="btn btn-ghost" data-whats="Hola Tech Computer, quiero asesoría para elegir mi PC" href="https://wa.me/{{ setting('whatsapp', '525549114435') }}?text={{ urlencode('Hola Tech Computer, quiero asesoría para elegir mi PC') }}">Pedir asesoría ↗</a>
                </article>
            </div>
        </section>

        @php($address = setting('address', 'República de Uruguay 9, entrada 1, planta baja, locales 64 y 60, Centro, CDMX'))
        <section class="location-panel hud-panel store-section">
            <div><p class="eyebrow">07 / Punto de encuentro · CDMX</p><h2>Visítanos en la plaza.</h2><address>{{ $address }}</address></div>
            <div class="location-actions">
                <a class="btn btn-primary" href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($address) }}" target="_blank" rel="noopener">Abrir Google Maps {!! $icon('map-pin') !!}</a>
                <a class="btn btn-ghost" data-whats="Hola Tech Computer, quiero visitar su tienda" href="https://wa.me/{{ setting('whatsapp', '525549114435') }}">Escribir por WhatsApp</a>
            </div>
        </section>
    </div>
</main>
@endsection

@push('scripts')
    @vite('resources/js/site/pages/home.js')
@endpush
