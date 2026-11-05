@extends('layouts.site')

@section('title', 'Consultar garantía | Tech Computer')
@section('description', 'Revisa la vigencia de tu garantía Tech Computer con el folio y el número de serie o los últimos 4 dígitos de tu teléfono.')

@push('head')
<meta name="robots" content="noindex" />
@endpush

@section('before')
<div class="backdrop" aria-hidden="true"></div>
@endsection

@section('content')
<main class="page shop">
    <div class="page-head">
        <h1>Consultar garantía</h1>
        <p class="intro">Escribe el folio de tu póliza de garantía (p. ej. GAR-2026-0040) y el número de serie de la pieza o los últimos 4 dígitos de tu teléfono.</p>
    </div>

    <form method="post" action="{{ route('tracking.warranty') }}" class="track-form">
        @csrf
        <label>Folio <input name="folio" required maxlength="30" value="{{ old('folio', request('folio')) }}" placeholder="GAR-2026-0040" autocapitalize="characters" /></label>
        <label>Serie o últimos 4 dígitos <input name="proof" required maxlength="80" value="{{ old('proof') }}" /></label>
        <button class="btn btn-primary" type="submit">Consultar</button>
    </form>
    @if ($errors->any())<div class="form-errors" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    @if ($warranty)
        <section class="track-result panel" aria-live="polite">
            <div class="core">
                <p class="silk">{{ $warranty->folio }}</p>
                <h2>{{ $warranty->status === 'void' ? 'Garantía anulada' : ($warranty->status === 'active' ? 'Garantía vigente' : 'Garantía vencida') }}</h2>
                <p class="muted">Emitida el {{ $warranty->starts_at->translatedFormat('j \d\e F \d\e Y') }}</p>
                <div class="quote-table" role="table" aria-label="Piezas cubiertas">
                    <div class="qt-row qt-head" role="row"><span role="columnheader">Pieza</span><span role="columnheader">Meses</span><span role="columnheader">Vence</span><span role="columnheader">Estado</span></div>
                    @foreach ($warranty->items as $item)
                        <div class="qt-row" role="row">
                            <span role="cell">{{ $item->description }} @if ($item->serial)<small class="muted">Serie {{ $item->serial }}</small>@endif</span>
                            <span role="cell">{{ $item->months }}</span>
                            <span role="cell">{{ $item->endsAt()->format('d/m/Y') }}</span>
                            <span role="cell" class="stock" data-level="{{ $item->isActive() ? 'ok' : 'none' }}">{{ $item->isActive() ? 'Vigente' : 'Vencida' }}</span>
                        </div>
                    @endforeach
                </div>
                <a class="btn btn-ghost btn-sm" data-whats="Hola Tech Computer, quiero hacer válida mi garantía {{ $warranty->folio }}" href="#">Hacer válida por WhatsApp</a>
            </div>
        </section>
    @elseif ($searched && ! $errors->any())
        <p class="empty" role="alert">No encontramos una garantía con esos datos. Revisa el folio o escríbenos por WhatsApp.</p>
    @endif
</main>
@endsection
