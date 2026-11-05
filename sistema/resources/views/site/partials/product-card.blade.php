@php
    $price = $product->publicPrice();
    $condition = $product->condition();
@endphp
<article class="p-card hud-panel">
    <a @class(['p-media', 'showcase', 'is-pending' => ! $product->imageUrl()]) href="{{ route('shop.product', $product) }}" tabindex="-1" aria-hidden="true">
        @if ($product->imageUrl())
            <img src="{{ $product->imageUrl() }}" alt="" width="400" height="400" loading="lazy" />
        @else
            @include('site.partials.photo-pending')
        @endif
        @if ($condition)<span class="p-badge">{{ \Illuminate\Support\Str::limit($condition, 32) }}</span>@endif
    </a>
    <div class="p-body">
        <span class="p-brand silk">{{ $product->brand?->name ?? $product->category?->name }}</span>
        <h3><a href="{{ route('shop.product', $product) }}" title="{{ $product->name }}">{{ $product->name }}</a></h3>
        <div class="p-foot">
            @if ($price !== null)
                <span class="p-price">{{ money($price) }}<small>MXN · IVA incluido</small></span>
            @else
                <span class="p-price is-quote">Cotizar<small>Consulta el precio del día</small></span>
            @endif
            <span class="stock" data-level="{{ $product->stockLevel() }}">{{ $product->stockLabel() }}</span>
        </div>
        <button class="btn btn-ghost btn-sm p-add" type="button" data-add-to-cart="{{ $product->id }}" data-name="{{ $product->name }}" aria-label="Agregar {{ $product->name }} a mi cotización"><span aria-hidden="true">+</span> A mi cotización</button>
    </div>
</article>
