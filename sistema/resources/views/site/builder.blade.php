@extends('layouts.site')

@section('title', 'Arma tu PC gamer en línea | Modo armado · Tech Computer')
@section('description', 'Arma tu PC gamer pieza por pieza con productos reales: compatibilidad, consumo y nivel de poder en vivo. Al terminar recibes tu cotización con folio y PDF.')
@section('body_class', 'builder-page')

@push('head')
<meta name="csrf-token" content="{{ csrf_token() }}" />
@endpush

@section('content')
<main class="build-main">
    <h1 class="sr-only">Arma tu PC gamer</h1>
    <div id="build-lab">
        <noscript><p class="page">El configurador necesita JavaScript. Escríbenos por WhatsApp y armamos tu PC contigo.</p></noscript>
    </div>
    <script type="application/json" id="build-catalog">{!! json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
</main>
@endsection

@push('scripts')
    @vite('resources/js/builder/main.ts')
@endpush
