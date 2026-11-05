@extends('layouts.site')

@section('title', 'Tienda | PCs gamer y componentes | Tech Computer')
@section('description', 'Componentes, periféricos y equipos armados con precio en MXN, IVA incluido y disponibilidad por pieza. Visítanos en Plaza de la Tecnología, CDMX.')
@section('body_class', $banners->isNotEmpty() ? 'has-hero-banners' : '')

@section('before')
<div class="backdrop" aria-hidden="true"></div>
@endsection

@section('content')
<main class="page shop shop-home">
    @include('site.partials.banners')
    <section class="catalog-head">
        <div><p class="eyebrow">Tienda / Tech Computer</p><h1>Tu siguiente mejora.</h1><p>Componentes, periféricos y equipos armados.</p></div>
        <form class="big-search" action="/buscar" role="search">
            <label class="sr-only" for="q-shop">Buscar productos</label>
            <input id="q-shop" name="q" type="search" placeholder="Ryzen, RTX, SSD, monitor…" autocomplete="off" data-suggest />
            <button class="btn btn-primary" type="submit">Buscar</button>
        </form>
    </section>
    @include('site.partials.trust')
    {!! $sections !!}
</main>
@endsection
