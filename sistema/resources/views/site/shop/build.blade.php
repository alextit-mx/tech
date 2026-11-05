@extends('layouts.site')

@php
    $total = $build->total();
    [$level, $label] = $build->availability();
    $parts = $build->parts();
    $issues = $build->errors();
    $use = \App\Models\Build::USES[$build->use] ?? '';
    $image = $build->imageUrl() ?? $parts->first(fn ($p) => $p->imageUrl())?->imageUrl();
    $jsonLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => 'PC '.$build->name,
        'description' => $build->tagline ?: 'Equipo armado '.$use.' con '.$parts->count().' piezas compatibles.',
        'image' => $image,
        'category' => 'Computadoras de escritorio',
        'offers' => $total !== null ? [
            '@type' => 'Offer', 'priceCurrency' => 'MXN', 'price' => number_format($total, 2, '.', ''),
            'availability' => $level === 'ok' ? 'https://schema.org/InStock' : 'https://schema.org/BackOrder',
            'url' => route('shop.build', $build), 'seller' => ['@type' => 'Organization', 'name' => 'Tech Computer'],
        ] : null,
    ]);
@endphp

@section('title', 'PC '.$build->name.' armada | Tech Computer')
@section('description', ($build->tagline ? $build->tagline.' ' : '').'Equipo armado '.$use.': '.$parts->map(fn ($p) => \App\Models\Build::shortName($p))->take(4)->join(', ').'. Personalízalo en Arma tu PC.')
@if ($image)
    @section('og_image', $image)
@endif

@push('head')
<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@if (! $build->is_published)<meta name="robots" content="noindex" />@endif
@endpush

@section('before')
<div class="backdrop" aria-hidden="true"></div>
@endsection

@section('content')
<main class="page shop">
    <nav class="crumbs" aria-label="Ruta">
        <a href="/tienda">Tienda</a>
        <a href="/tienda#equipos">Equipos armados</a>
        <span aria-current="page">{{ $build->name }}</span>
    </nav>

    <article class="build-page">
        <div class="build-hero">
            <p class="eyebrow">{{ $use }}</p>
            <h1>{{ $build->name }}</h1>
            @if ($build->tagline)<p class="lede">{{ $build->tagline }}</p>@endif
            <dl class="b-specs big">
                @foreach ($build->highlights() as $h)
                    <div><dt>{{ $h['label'] }}</dt><dd>{{ $h['value'] }}</dd></div>
                @endforeach
            </dl>
        </div>

        <aside class="buy-box build-buy">
            @if ($total !== null)
                <p class="p-price big">{{ money($total) }}<small>Total de {{ $parts->count() }} piezas · IVA incluido</small></p>
            @else
                <p class="p-price big is-quote">Cotizar<small>Te damos el precio del día por WhatsApp</small></p>
            @endif
            <p class="stock" data-level="{{ $level }}">{{ $label }}</p>
            @if ($issues)
                <p class="form-errors">{{ $issues[0] }}</p>
            @else
                <p class="ok-note">Todas las piezas son compatibles · consumo estimado {{ \App\Support\BuildValidator::consumption($parts->map(fn ($p) => $p->specs ?? [])->all()) }} W</p>
            @endif
            <a class="btn btn-primary" href="{{ $build->builderUrl() }}">Personalízalo en Arma tu PC</a>
            <button class="btn btn-ghost" type="button" data-add-build="{{ $parts->pluck('id')->implode(',') }}" data-name="{{ $build->name }}">Agregar todo a mi cotización</button>
            <a class="btn btn-ghost btn-sm" data-whats="Hola Tech Computer, me interesa el equipo {{ $build->name }}: {{ route('shop.build', $build) }}" href="#">Preguntar por WhatsApp</a>
        </aside>

        <section class="build-parts" aria-label="Piezas">
            <h2 class="silk">Piezas del equipo</h2>
            <ol>
                @foreach ($parts as $slot => $part)
                    @php($price = $part->trashed() || ! $part->is_published ? null : $part->publicPrice())
                    <li>
                        <span @class(['bp-thumb', 'showcase', 'is-pending' => ! $part->imageUrl()])>@if ($part->imageUrl())<img src="{{ $part->imageUrl() }}" alt="" width="72" height="72" loading="lazy" />@else @include('site.partials.photo-pending') @endif</span>
                        <span class="bp-info">
                            <small>{{ \App\Models\Build::SLOT_LABELS[$slot] ?? $slot }}</small>
                            @if ($part->is_published && ! $part->trashed())
                                <a href="{{ route('shop.product', $part) }}">{{ $part->name }}</a>
                            @else
                                <span>{{ $part->name }}</span>
                            @endif
                            <span class="stock" data-level="{{ $part->stockLevel() }}">{{ $part->stockLabel() }}</span>
                        </span>
                        <span class="bp-price">{{ $price !== null ? money($price) : 'Cotizar' }}</span>
                    </li>
                @endforeach
            </ol>
        </section>
    </article>

    @if ($others->isNotEmpty())
        <section class="section">
            <header class="rail-head"><div><p class="eyebrow">Compara</p><h2>Otros equipos armados</h2></div></header>
            <div class="b-grid">
                @foreach ($others as $build)
                    @include('site.partials.build-card')
                @endforeach
            </div>
        </section>
    @endif
</main>
@endsection
