@php($price = $product->publicPrice())
<article class="p-card">
    <a class="p-media" href="{{ route('shop.product', $product) }}" tabindex="-1" aria-hidden="true">
        @if ($product->imageUrl())
            <img src="{{ $product->imageUrl() }}" alt="" width="400" height="400" loading="lazy" />
        @else
            <img class="p-placeholder" src="/img/ats-emblema.png" alt="" width="160" height="160" loading="lazy" />
        @endif
        <span class="silk">{{ $product->category?->name }}</span>
    </a>
    <div class="p-body">
        <h3><a href="{{ route('shop.product', $product) }}">@if ($product->brand)<span class="p-brand">{{ $product->brand->name }}</span> @endif{{ $product->name }}</a></h3>
        @if ($product->short_description)
            <p class="p-specs">{{ $product->short_description }}</p>
        @endif
        <div class="p-foot">
            @if ($price !== null)
                <span class="p-price">{{ money($price) }}<small>IVA incluido</small></span>
            @else
                <span class="p-price is-quote">Cotizar<small>Precio del día por WhatsApp</small></span>
            @endif
            <span class="stock" data-level="{{ $product->stock > 3 ? 'ok' : ($product->stock > 0 ? 'low' : 'none') }}">{{ $product->stockLabel() }}</span>
        </div>
        <button class="btn btn-ghost btn-sm p-add" type="button" data-add-to-cart="{{ $product->id }}" data-name="{{ $product->name }}">Agregar a mi cotización</button>
    </div>
</article>
