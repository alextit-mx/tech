@extends('layouts.site')

@section('title', ($term !== '' ? 'Resultados para "'.$term.'"' : 'Buscar').' | Tech Computer')
@section('description', 'Busca procesadores, tarjetas de video, periféricos, impresoras y consumibles en Tech Computer.')

@push('head')
<meta name="robots" content="noindex" />
@endpush

@section('before')
<div class="backdrop" aria-hidden="true"></div>
@endsection

@section('content')
<main class="page shop" data-listing>
    <div class="page-head">
        <p class="eyebrow">Catálogo / búsqueda</p>
        <h1>Encuentra tu pieza.</h1>
        @if ($term !== '')<p class="intro">{{ $products?->total() ?? 0 }} {{ $products?->total() === 1 ? 'resultado' : 'resultados' }} para «{{ $term }}»</p>@endif
        <form class="big-search" action="/buscar" role="search">
            <label class="sr-only" for="q-page">Buscar productos</label>
            <input id="q-page" name="q" type="search" value="{{ $term }}" placeholder="RTX 5070, tóner HP, teclado mecánico…" autocomplete="off" data-suggest @if ($term === '') autofocus @endif />
            <button class="btn btn-primary" type="submit">Buscar</button>
        </form>
    </div>

    @if ($products)
        <form class="search-filters hud-panel" method="get" aria-label="Filtrar resultados">
            <input type="hidden" name="q" value="{{ $term }}" />
            @foreach (['min', 'max', 'marca'] as $key)
                @if (request()->filled($key))<input type="hidden" name="{{ $key }}" value="{{ request($key) }}" />@endif
            @endforeach
            <label class="check"><input type="checkbox" name="inmediata" value="1" @checked(request()->boolean('inmediata')) /> Entrega inmediata</label>
            <label class="check"><input type="checkbox" name="manana" value="1" @checked(request()->boolean('manana')) /> Local o en 24 h</label>
            <label class="sort">Ordenar
                <select name="orden">
                    <option value="">Recomendados</option>
                    <option value="precio" @selected(request('orden') === 'precio')>Menor precio</option>
                    <option value="precio-desc" @selected(request('orden') === 'precio-desc')>Mayor precio</option>
                    <option value="nombre" @selected(request('orden') === 'nombre')>Nombre</option>
                </select>
            </label>
            <button class="btn btn-ghost btn-sm" type="submit">Aplicar</button>
            <a class="chip" href="/buscar?q={{ urlencode($term) }}">Limpiar filtros</a>
        </form>
    @endif

    @if ($products && $products->isNotEmpty())
        <div class="p-grid">
            @foreach ($products as $product)
                @include('site.partials.product-card')
            @endforeach
        </div>
        @include('site.partials.pager', ['paginator' => $products])
    @elseif ($products)
        <div class="empty hud-panel">
            @include('site.partials.photo-pending')
            <h2>Sin coincidencias</h2><p>Prueba otro nombre o consulta disponibilidad.</p>
            <a class="btn btn-primary btn-sm" data-whats="Hola Tech Computer, busco: {{ $term }}" href="#">Preguntar por WhatsApp</a>
        </div>
    @endif
</main>
@endsection
