@extends('layouts.site')

@section('title', 'Cotización '.$quote->folio.' | Tech Computer')
@section('description', 'Cotización '.$quote->folio.' de Tech Computer.')

@push('head')
<meta name="robots" content="noindex" />
@endpush

@section('before')
<div class="backdrop" aria-hidden="true"></div>
@endsection

@section('content')
<main class="page shop quote-page">
    <div class="page-head">
        <h1 class="folio">{{ $quote->folio }}</h1>
        <p class="quote-source">{{ \App\Models\Quote::SOURCES[$quote->source] ?? 'Cotización' }}</p>
        @if ($quote->build_name)<p class="lede">{{ $quote->build_name }}</p>@endif
        <p class="intro">Para {{ $quote->customer->name }} · vigente hasta el {{ $quote->valid_until->translatedFormat('j \d\e F \d\e Y') }}</p>
    </div>

    @if (session('created'))
        <div class="panel notice" data-clear-cart>
            <div class="core">
                <h2>¡Listo! Ya tenemos tu cotización.</h2>
                <p>Mándanos el folio por WhatsApp para atenderte más rápido; te confirmamos existencias y el precio del día.</p>
                <div class="actions">
                    <a class="btn btn-primary" href="{{ $quote->businessWhatsappUrl() }}" target="_blank" rel="noopener">Enviar a Tech Computer por WhatsApp</a>
                    <a class="btn btn-ghost" href="{{ route('quotes.public.pdf', $quote->public_token) }}">Descargar PDF</a>
                </div>
            </div>
        </div>
    @endif

    <div class="quote-table" role="table" aria-label="Partidas">
        <div class="qt-row qt-head" role="row"><span role="columnheader">Producto</span><span role="columnheader">Cant.</span><span role="columnheader">Precio</span><span role="columnheader">Importe</span></div>
        @foreach ($quote->items as $item)
            <div class="qt-row" role="row">
                <span role="cell">{{ $item->description }} @if ($item->sku)<small class="muted">{{ $item->sku }}</small>@endif</span>
                <span role="cell">{{ $item->quantity }}</span>
                <span role="cell">{{ $item->unit_price === null ? 'Por confirmar' : money($item->unit_price) }}</span>
                <span role="cell">{{ $item->line_total === null ? '—' : money($item->line_total) }}</span>
            </div>
        @endforeach
        @if ((float) $quote->discount > 0)
            <div class="qt-row qt-sum" role="row"><span role="cell">Descuento</span><span></span><span></span><span role="cell">−{{ money($quote->discount) }}</span></div>
        @endif
        <div class="qt-row qt-sum" role="row"><span role="cell">Subtotal</span><span></span><span></span><span role="cell">{{ money($quote->subtotal) }}</span></div>
        <div class="qt-row qt-sum" role="row"><span role="cell">IVA 16 %</span><span></span><span></span><span role="cell">{{ money($quote->tax) }}</span></div>
        <div class="qt-row qt-total" role="row"><span role="cell">{{ $quote->has_pending_prices ? 'Total parcial' : 'Total' }}</span><span></span><span></span><span role="cell">{{ money($quote->total) }}</span></div>
    </div>
    @if ($quote->has_pending_prices)
        <p class="muted small">Hay partidas por confirmar: te pasamos su precio del día por WhatsApp.</p>
    @endif
    @if ($quote->notes)
        <p class="muted">{{ $quote->notes }}</p>
    @endif

    @unless (session('created'))
        <div class="actions">
            <a class="btn btn-primary" href="{{ route('quotes.public.pdf', $quote->public_token) }}">Descargar PDF</a>
            <a class="btn btn-ghost" href="{{ $quote->businessWhatsappUrl() }}" target="_blank" rel="noopener">Hablar con Tech Computer</a>
        </div>
    @endunless
</main>
@endsection
