{{-- Carrusel horizontal de productos: desplazamiento con snap; las flechas las activa shop.js. --}}
<section class="rail-section" id="{{ $id }}" data-rail data-autoplay-interval="4500" aria-roledescription="carrusel" aria-label="{{ $title }}">
    <header class="rail-head">
        <div>
            @if (! empty($eyebrow))<p class="eyebrow">{{ $eyebrow }}</p>@endif
            <h2>{{ $title }}</h2>
        </div>
        <div class="rail-tools">
            @if (! empty($link))<a class="rail-all" href="{{ $link }}">Ver todo</a>@endif
            <button class="carousel-pause" type="button" data-carousel-pause aria-pressed="false" aria-label="Pausar carrusel"><span>Pausar</span></button>
            <button class="rail-arrow" type="button" data-rail-prev aria-label="Anteriores" disabled>‹</button>
            <button class="rail-arrow" type="button" data-rail-next aria-label="Siguientes">›</button>
        </div>
    </header>
    <div class="p-rail" data-rail-track tabindex="0" aria-label="{{ $title }}">
        @foreach ($items as $product)
            @include('site.partials.product-card')
        @endforeach
    </div>
    <span class="carousel-progress" aria-hidden="true"><i data-carousel-progress></i></span>
</section>
