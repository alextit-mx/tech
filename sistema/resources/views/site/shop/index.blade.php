@extends('layouts.site')

@section('title', 'Tienda | PCs gamer, componentes, periféricos e impresoras | Tech Computer')
@section('description', 'Procesadores, tarjetas de video, memoria, periféricos gamer, accesorios, impresoras, tintas y tóner con precio con IVA y existencias en Plaza de la Tecnología, CDMX.')

@section('before')
<div class="backdrop" aria-hidden="true"></div>
@endsection

@section('content')
<main class="page shop">
    <div class="page-head">
        <p class="eyebrow">Tienda</p>
        <h1>Todo para tu PC, tu setup y tu oficina</h1>
        <p class="intro">Precios en pesos con IVA incluido. Arma tu lista, mándala y te confirmamos existencias y precio del día por WhatsApp.</p>
    </div>

    @include('site.partials.departments')

    @if ($featured->isNotEmpty())
        <section class="section">
            <h2>Destacados</h2>
            <div class="p-grid">
                @foreach ($featured as $product)
                    @include('site.partials.product-card')
                @endforeach
            </div>
        </section>
    @endif
</main>
@endsection
