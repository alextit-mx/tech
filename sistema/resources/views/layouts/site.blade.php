<!doctype html>
<html lang="es-MX">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
<title>@yield('title', 'Tech Computer | PCs gamer, componentes y servicio técnico')</title>
<meta name="description" content="@yield('description', 'PCs gamer, componentes, periféricos, impresoras y consumibles, armado a la medida y servicio técnico en Plaza de la Tecnología, CDMX.')" />
<meta name="theme-color" content="#070608" />
<link rel="canonical" href="@yield('canonical', url()->current())" />
<meta property="og:type" content="@yield('og_type', 'website')" />
<meta property="og:site_name" content="Tech Computer" />
<meta property="og:locale" content="es_MX" />
<meta property="og:title" content="@yield('title', 'Tech Computer')" />
<meta property="og:description" content="@yield('description', 'PCs gamer y servicio técnico en Plaza de la Tecnología, CDMX.')" />
<meta property="og:image" content="@yield('og_image', asset('img/ats-computer.png'))" />
<meta property="og:url" content="{{ url()->current() }}" />
<meta name="twitter:card" content="summary_large_image" />
<link rel="icon" type="image/png" href="/img/favicon.png" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Chakra+Petch:wght@500;600;700&display=swap" rel="stylesheet" />
<script>document.documentElement.classList.add("js");</script>
@vite('resources/js/site/main.js')
@stack('head')
</head>
<body class="@yield('body_class')">
@yield('before')

@php
    $nav = ['/tienda' => 'Tienda', '/arma-tu-pc' => 'Arma tu PC', '/servicio-tecnico' => 'Servicio técnico', '/asesoria' => 'Asesoría', '/contacto' => 'Contacto'];
    $whatsapp = setting('whatsapp', '525549114435');
@endphp
<header class="site-header">
    <a class="brand" href="/"><img src="/img/ats-computer.png" alt="Tech Computer, inicio" width="300" height="117" /></a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav">Menú</button>
    <nav class="site-nav" id="nav" aria-label="Principal">
        @foreach ($nav as $href => $label)
            <a href="{{ $href }}" @if (request()->is(ltrim($href, '/').'*') || ($href === '/tienda' && request()->is('producto/*', 'buscar'))) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="header-tools">
        <form class="header-search" action="/buscar" role="search">
            <label class="sr-only" for="q">Buscar productos</label>
            <input id="q" name="q" type="search" placeholder="Buscar" value="{{ request()->is('buscar') ? request('q') : '' }}" />
        </form>
        <a class="cart-link" href="/carrito" aria-label="Mi cotización">
            <span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/clipboard-list.svg')"></span>
            <span class="cart-count" data-cart-count hidden>0</span>
        </a>
        <a class="btn btn-primary btn-sm btn-whats" data-whats="Hola Tech Computer" href="https://wa.me/{{ $whatsapp }}">
            WhatsApp
            <span class="ic"><span class="mask" style="--icon: url('https://cdn.jsdelivr.net/npm/simple-icons@13.21.0/icons/whatsapp.svg')"></span></span>
        </a>
    </div>
</header>

@yield('content')

<footer class="site-footer">
    <div>
        <img src="/img/ats-computer.png" alt="Tech Computer" width="300" height="117" loading="lazy" />
        <p>PCs gamer, componentes, periféricos, impresoras y consumibles, armado a la medida y servicio técnico en Plaza de la Tecnología, Centro de la Ciudad de México.</p>
    </div>
    <nav aria-label="Sitio">
        @foreach ($nav as $href => $label)
            <a href="{{ $href }}">{{ $label }}</a>
        @endforeach
        <a href="/estado">Estado de mi equipo</a>
        <a href="/garantia">Consultar garantía</a>
    </nav>
    <div class="reach">
        <a data-whats="Hola Tech Computer" href="https://wa.me/{{ $whatsapp }}">WhatsApp {{ preg_replace('/^52(\d{2})(\d{4})(\d{4})$/', '$1 $2 $3', $whatsapp) }}</a>
        <a href="mailto:{{ setting('email', 'techcomputerlocal64@gmail.com') }}">{{ setting('email', 'techcomputerlocal64@gmail.com') }}</a>
        <span>{{ setting('address', 'República de Uruguay 9, entrada 1, planta baja, locales 64 y 60, Centro, CDMX') }}</span>
    </div>
    <div class="legal"><span>{{ setting('legal_name', 'Tech Computer ATS SA de CV') }}, RFC {{ setting('rfc', 'TCA240830IM3') }}</span><span>Precios en pesos con IVA incluido, sujetos a cambio y existencias.</span></div>
</footer>

<script>window.TC = { whatsapp: @json($whatsapp) };</script>
@stack('scripts')
</body>
</html>
