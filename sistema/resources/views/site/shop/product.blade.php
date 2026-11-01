@extends('layouts.site')

@php
    $price = $product->publicPrice();
    $fullName = trim(($product->brand?->name ? $product->brand->name.' ' : '').$product->name);
    $specs = collect($product->specs ?? [])->except('tier')->map(fn ($value, $key) => is_array($value) && isset($value['name'])
        ? [$value['name'], $value['value']]
        : [\App\Support\BuilderSpecs::label($key), is_bool($value) ? ($value ? 'Sí' : 'No') : (is_array($value) ? implode(', ', $value) : $value)])->values();
    $jsonLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $fullName,
        'sku' => $product->sku,
        'description' => $product->short_description ?: $product->description,
        'image' => $product->images->map(fn ($i) => asset('uploads/'.$i->path))->all() ?: null,
        'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
        'offers' => $price !== null ? [
            '@type' => 'Offer',
            'priceCurrency' => 'MXN',
            'price' => number_format($price, 2, '.', ''),
            'availability' => $product->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/BackOrder',
            'url' => route('shop.product', $product),
            'seller' => ['@type' => 'Organization', 'name' => 'Tech Computer'],
        ] : null,
    ]);
@endphp

@section('title', $fullName.' | Tech Computer')
@section('description', $product->short_description ?: 'Compra '.$fullName.' en Tech Computer, Plaza de la Tecnología, CDMX.')
@section('og_type', 'product')
@if ($product->imageUrl())
    @section('og_image', $product->imageUrl())
@endif

@push('head')
<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('before')
<div class="backdrop" aria-hidden="true"></div>
@endsection

@section('content')
<main class="page shop">
    <nav class="crumbs" aria-label="Ruta">
        <a href="/tienda">Tienda</a>
        @if ($product->category->parent)<a href="{{ route('shop.category', $product->category->parent) }}">{{ $product->category->parent->name }}</a>@endif
        <a href="{{ route('shop.category', $product->category) }}">{{ $product->category->name }}</a>
    </nav>

    <article class="product">
        <div class="gallery" data-gallery>
            <div class="gallery-main">
                @if ($product->images->isNotEmpty())
                    <img src="{{ asset('uploads/'.$product->images->first()->path) }}" alt="{{ $fullName }}" width="800" height="800" data-gallery-main />
                @else
                    <img class="p-placeholder" src="/img/ats-emblema.png" alt="" width="240" height="240" />
                @endif
            </div>
            @if ($product->images->count() > 1)
                <div class="gallery-thumbs">
                    @foreach ($product->images as $image)
                        <button type="button" data-gallery-thumb="{{ asset('uploads/'.$image->path) }}" aria-label="Ver foto {{ $loop->iteration }}"><img src="{{ asset('uploads/'.$image->path) }}" alt="" width="96" height="96" loading="lazy" /></button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="product-info">
            <span class="silk">SKU {{ $product->sku }}</span>
            <h1>@if ($product->brand)<span class="p-brand">{{ $product->brand->name }}</span> @endif{{ $product->name }}</h1>
            @if ($product->short_description)<p class="lede">{{ $product->short_description }}</p>@endif

            <div class="buy-box">
                @if ($price !== null)
                    <p class="p-price big">{{ money($price) }}<small>IVA incluido</small></p>
                @else
                    <p class="p-price big is-quote">Cotizar<small>Te damos el precio del día por WhatsApp</small></p>
                @endif
                <p class="stock" data-level="{{ $product->stock > 3 ? 'ok' : ($product->stock > 0 ? 'low' : 'none') }}">{{ $product->stockLabel() }}</p>
                <div class="qty-row">
                    <label>Cantidad <input type="number" min="1" max="99" value="1" data-qty /></label>
                    <button class="btn btn-primary" type="button" data-add-to-cart="{{ $product->id }}" data-name="{{ $product->name }}">Agregar a mi cotización</button>
                </div>
                <a class="btn btn-ghost btn-sm" data-whats="Hola Tech Computer, me interesa {{ $fullName }} (SKU {{ $product->sku }}): {{ route('shop.product', $product) }}" href="#">Preguntar por WhatsApp</a>
                @if ($product->warranty_months)
                    <p class="muted small">Garantía de {{ $product->warranty_months }} {{ $product->warranty_months === 1 ? 'mes' : 'meses' }} con Tech Computer.</p>
                @endif
            </div>

            @if ($specs->isNotEmpty())
                <section class="spec-sheet" aria-label="Ficha técnica">
                    <h2 class="silk">Ficha técnica</h2>
                    <dl>
                        @foreach ($specs as [$label, $value])
                            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </div>
    </article>

    @if ($product->description)
        <section class="section prose">
            <h2>Descripción</h2>
            {!! nl2br(e($product->description)) !!}
        </section>
    @endif

    @if ($related->isNotEmpty())
        <section class="section">
            <h2>También en {{ $product->category->name }}</h2>
            <div class="p-grid">
                @foreach ($related as $product)
                    @include('site.partials.product-card')
                @endforeach
            </div>
        </section>
    @endif
</main>
@endsection
