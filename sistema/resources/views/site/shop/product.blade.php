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
            'availability' => ($product->stock > 0 || $product->stock_24h > 0) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
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
        {{-- Galería sin ampliación automática; conserva flechas, miniaturas y pantalla completa. --}}
        <div class="gallery" @if ($product->images->isNotEmpty()) data-gallery data-autoplay-interval="4500" @endif>
            <div @class(['gallery-main', 'showcase', 'is-pending' => $product->images->isEmpty()]) @if ($product->images->isNotEmpty()) data-gallery-stage aria-roledescription="carrusel" aria-label="Fotos de {{ $fullName }}" @endif>
                @forelse ($product->images as $image)
                    <figure class="g-slide {{ $loop->first ? 'is-active' : '' }}" @unless ($loop->first) aria-hidden="true" @endunless>
                        <img src="{{ asset('uploads/'.$image->path) }}" alt="{{ $fullName }}{{ $loop->count > 1 ? ' · foto '.$loop->iteration : '' }}" width="800" height="800" @unless ($loop->first) loading="lazy" @endunless />
                    </figure>
                @empty
                    @include('site.partials.photo-pending')
                @endforelse
                @if ($product->images->isNotEmpty())
                    <button class="g-expand" type="button" data-gallery-open aria-label="Ver en pantalla completa">⤢</button>
                @endif
                @if ($product->images->count() > 1)
                    <button class="g-arrow prev" type="button" data-gallery-prev aria-label="Foto anterior">‹</button>
                    <button class="g-arrow next" type="button" data-gallery-next aria-label="Foto siguiente">›</button>
                    <span class="g-count" aria-live="polite"><b data-gallery-index>1</b> / {{ $product->images->count() }}</span>
                    <span class="carousel-progress" aria-hidden="true"><i data-carousel-progress></i></span>
                @endif
            </div>
            @if ($product->images->count() > 1)
                <button class="carousel-pause" type="button" data-carousel-pause aria-pressed="false" aria-label="Pausar carrusel"><span>Pausar</span></button>
                <div class="gallery-thumbs" role="tablist" aria-label="Elegir foto">
                    @foreach ($product->images as $image)
                        <button class="showcase" type="button" role="tab" data-gallery-go="{{ $loop->index }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-label="Ver foto {{ $loop->iteration }}"><img src="{{ asset('uploads/'.$image->path) }}" alt="" width="96" height="96" loading="lazy" /></button>
                    @endforeach
                </div>
            @endif
            @if ($product->images->isNotEmpty())
                <dialog class="lightbox" data-lightbox aria-label="Fotos de {{ $fullName }}">
                    <div class="lightbox-vitrine showcase"><img src="" alt="" data-lightbox-img /></div>
                    <button class="lb-close" type="button" data-lightbox-close aria-label="Cerrar">×</button>
                    @if ($product->images->count() > 1)
                        <button class="lb-arrow prev" type="button" data-lightbox-prev aria-label="Foto anterior">‹</button>
                        <button class="lb-arrow next" type="button" data-lightbox-next aria-label="Foto siguiente">›</button>
                    @endif
                    <p class="lb-hint">Usa las flechas para cambiar de foto · Esc para cerrar</p>
                </dialog>
            @endif
        </div>

        <div class="product-info">
            <p class="eyebrow">{{ $product->brand?->name ?? $product->category->name }}</p>
            <h1>{{ $product->name }}</h1>
            <p class="product-meta"><span class="silk">SKU {{ $product->sku }}</span></p>

            <div class="buy-box hud-panel">
                @if ($price !== null)
                    <p class="p-price big">{{ money($price) }}<small>IVA incluido</small></p>
                @else
                    <p class="p-price big is-quote">Cotizar<small>Te damos el precio del día por WhatsApp</small></p>
                @endif
                <p class="stock" data-level="{{ $product->stockLevel() }}">{{ $product->stockLabel() }}</p>
                <dl class="stock-breakdown">
                    <div><dt class="silk">En el local</dt><dd>{{ $product->stock }} {{ $product->stock === 1 ? 'pieza' : 'piezas' }}</dd></div>
                    <div><dt class="silk">En 24 h</dt><dd>{{ $product->stock_24h }} {{ $product->stock_24h === 1 ? 'pieza' : 'piezas' }}</dd></div>
                </dl>
                <div class="qty-row">
                    <label>Cantidad <input type="number" name="quantity" inputmode="numeric" min="1" max="99" value="1" data-qty /></label>
                    <button class="btn btn-primary" type="button" data-add-to-cart="{{ $product->id }}" data-name="{{ $product->name }}">Agregar a mi cotización</button>
                </div>
                <a class="btn btn-ghost btn-sm" data-whats="Hola Tech Computer, me interesa {{ $fullName }} (SKU {{ $product->sku }}): {{ route('shop.product', $product) }}" href="#">Preguntar por WhatsApp</a>
                @if ($product->warranty_months)
                    <p class="muted small">Garantía de {{ $product->warranty_months }} {{ $product->warranty_months === 1 ? 'mes' : 'meses' }} con Tech Computer.</p>
                @endif
            </div>

            @if ($specs->isNotEmpty())
                <section class="spec-sheet hud-panel" aria-label="Ficha técnica">
                    <h2 class="silk">Ficha técnica</h2>
                    <table><caption class="sr-only">Especificaciones de {{ $product->name }}</caption><tbody>
                        @foreach ($specs as [$label, $value])
                            <tr><th scope="row" class="silk">{{ $label }}</th><td>{{ $value }}</td></tr>
                        @endforeach
                    </tbody></table>
                </section>
            @endif
        </div>
    </article>

    @if ($product->description || $product->short_description)
        <details class="product-description hud-panel">
            <summary>Detalles del producto</summary>
            <div>@if ($product->short_description)<p>{{ $product->short_description }}</p>@endif
            @if ($product->description)<p>{!! nl2br(e($product->description)) !!}</p>@endif</div>
        </details>
    @endif

    @if ($complete->isNotEmpty())
        @include('site.partials.rail', ['id' => 'completa', 'eyebrow' => 'Compatibles con esta pieza', 'title' => 'Completa tu equipo', 'link' => null, 'items' => $complete])
    @endif

    @if ($builds->isNotEmpty())
        <section class="section">
            <header class="rail-head"><div><p class="eyebrow">Ya armados</p><h2>Equipos que la incluyen</h2></div></header>
            <div class="b-grid">
                @foreach ($builds as $build)
                    @include('site.partials.build-card')
                @endforeach
            </div>
        </section>
    @endif

    @if ($related->isNotEmpty())
        @include('site.partials.rail', ['id' => 'similares', 'eyebrow' => $product->category->name, 'title' => 'Similares', 'link' => route('shop.category', $product->category), 'items' => $related])
    @endif
</main>
@endsection
