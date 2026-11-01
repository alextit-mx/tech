@extends('layouts.site')

@section('title', 'Mi cotización | Tech Computer')
@section('description', 'Revisa tu lista de productos y mándala para recibir tu cotización con precio del día.')

@push('head')
<meta name="robots" content="noindex" />
@endpush

@section('before')
<div class="backdrop" aria-hidden="true"></div>
@endsection

@section('content')
<main class="page shop">
    <div class="page-head">
        <p class="eyebrow">Mi cotización</p>
        <h1>Tu lista</h1>
        <p class="intro">Mándala y te respondemos por WhatsApp con existencias y precio del día. No pagas nada todavía.</p>
    </div>

    <div class="cart-layout" data-cart-page>
        <section aria-label="Productos de tu lista">
            <ul class="cart-list" data-cart-list><li class="muted">Cargando tu lista…</li></ul>
            <p class="cart-empty" data-cart-empty hidden>Tu lista está vacía. <a class="more" href="/tienda">Ir a la tienda</a> o <a class="more" href="/arma-tu-pc">arma tu PC</a>.</p>
        </section>

        <aside class="panel cart-summary" data-cart-summary hidden>
            <div class="core">
                <p class="silk">Total estimado</p>
                <p class="p-price big" data-cart-total>$0<small>IVA incluido</small></p>
                <p class="muted small" data-cart-pending hidden>Hay productos con precio por confirmar.</p>

                @if ($errors->any())
                    <div class="form-errors" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <form method="post" action="{{ route('cart.submit') }}" class="quote-form" data-cart-form>
                    @csrf
                    <div data-cart-inputs></div>
                    <label>Nombre <input name="name" required maxlength="120" autocomplete="name" value="{{ old('name') }}" /></label>
                    <label>WhatsApp <input name="phone" required inputmode="tel" autocomplete="tel" placeholder="55 1234 5678" value="{{ old('phone') }}" /></label>
                    <label>Correo <span class="muted">(opcional)</span> <input name="email" type="email" autocomplete="email" value="{{ old('email') }}" /></label>
                    <label>Comentarios <span class="muted">(opcional)</span> <textarea name="notes" rows="3" maxlength="1000">{{ old('notes') }}</textarea></label>
                    <label class="hp" aria-hidden="true">Sitio web <input name="website" tabindex="-1" autocomplete="off" /></label>
                    <button class="btn btn-primary" type="submit">Enviar mi cotización</button>
                </form>
            </div>
        </aside>
    </div>
</main>
@endsection

@push('scripts')
    @vite('resources/js/site/pages/cart.js')
@endpush
