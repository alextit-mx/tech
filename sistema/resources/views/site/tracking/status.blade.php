@extends('layouts.site')

@section('title', 'Estado de mi equipo | Tech Computer')
@section('description', 'Consulta el estado de tu equipo en servicio técnico con tu folio y los últimos 4 dígitos de tu teléfono.')

@push('head')
<meta name="robots" content="noindex" />
@endpush

@section('before')
<div class="backdrop" aria-hidden="true"></div>
@endsection

@section('content')
<main class="page shop">
    <div class="page-head">
        <h1>Estado de mi equipo</h1>
        <p class="intro">Escribe el folio de tu orden (viene en tu comprobante, p. ej. OS-2026-0012) y los últimos 4 dígitos del teléfono que nos dejaste.</p>
    </div>

    <form method="post" action="{{ route('tracking.status') }}" class="track-form">
        @csrf
        <label>Folio <input name="folio" required maxlength="30" value="{{ old('folio', request('folio')) }}" placeholder="OS-2026-0012" autocapitalize="characters" /></label>
        <label>Últimos 4 dígitos <input name="digits" required inputmode="numeric" maxlength="4" pattern="\d{4}" value="{{ old('digits') }}" /></label>
        <button class="btn btn-primary" type="submit">Consultar</button>
    </form>
    @if ($errors->any())<div class="form-errors" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    @if ($order)
        <section class="track-result panel" aria-live="polite">
            <div class="core">
                <p class="silk">{{ $order->folio }}</p>
                <h2>{{ $statuses[$order->status] ?? $order->status }}</h2>
                <p class="muted">{{ $order->device() }} · recibido el {{ $order->received_at->translatedFormat('j \d\e F') }}@if ($order->promised_at && ! in_array($order->status, ['delivered', 'unrepaired'])) · fecha estimada: {{ $order->promised_at->translatedFormat('j \d\e F') }}@endif</p>
                <ol class="timeline">
                    @foreach ($order->events as $event)
                        <li>
                            <time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->translatedFormat('j M, H:i') }}</time>
                            @if ($event->status)<strong>{{ $statuses[$event->status] ?? $event->status }}</strong>@endif
                            @if ($event->note)<p>{{ $event->note }}</p>@endif
                        </li>
                    @endforeach
                </ol>
                <a class="btn btn-ghost btn-sm" data-whats="Hola Tech Computer, pregunto por mi equipo con folio {{ $order->folio }}" href="#">Preguntar por WhatsApp</a>
            </div>
        </section>
    @elseif ($searched && ! $errors->any())
        <p class="empty" role="alert">No encontramos una orden con ese folio y teléfono. Revisa los datos o escríbenos por WhatsApp.</p>
    @endif
</main>
@endsection
