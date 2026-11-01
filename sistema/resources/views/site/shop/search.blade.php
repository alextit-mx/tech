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
<main class="page shop">
    <div class="page-head">
        <p class="eyebrow">Buscar</p>
        <h1>{{ $term !== '' ? 'Resultados para "'.$term.'"' : '¿Qué estás buscando?' }}</h1>
        <form class="big-search" action="/buscar" role="search">
            <label class="sr-only" for="q-page">Buscar productos</label>
            <input id="q-page" name="q" type="search" value="{{ $term }}" placeholder="RTX 5070, tóner HP, teclado mecánico…" autofocus />
            <button class="btn btn-primary" type="submit">Buscar</button>
        </form>
    </div>

    @if ($products && $products->isNotEmpty())
        <div class="p-grid">
            @foreach ($products as $product)
                @include('site.partials.product-card')
            @endforeach
        </div>
        @include('site.partials.pager', ['paginator' => $products])
    @elseif ($products)
        <div class="empty">
            <p>No encontramos "{{ $term }}" en la tienda, pero seguro te lo conseguimos.</p>
            <a class="btn btn-primary btn-sm" data-whats="Hola Tech Computer, busco: {{ $term }}" href="#">Preguntar por WhatsApp</a>
        </div>
    @endif
</main>
@endsection
