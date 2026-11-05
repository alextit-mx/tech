{{-- El título reutiliza la descripción administrada en Portal → Banners. --}}
@if ($banners->isNotEmpty())
    <section class="banners" aria-roledescription="carrusel" aria-label="Promociones" data-banners data-autoplay-interval="4500">
        <div class="banners-track">
            @foreach ($banners as $banner)
                <figure class="banner {{ $loop->first ? 'is-active' : '' }} {{ $banner->image_mobile_url ? 'has-mobile' : '' }}" aria-roledescription="diapositiva" aria-label="{{ $loop->iteration }} de {{ $loop->count }}" @unless ($loop->first) aria-hidden="true" @endunless>
                    <picture>
                        @if ($banner->image_mobile_url)<source media="(max-width: 640px)" srcset="{{ $banner->image_mobile_url }}" />@endif
                        <img src="{{ $banner->image_url }}" alt="{{ $banner->alt }}" width="2400" height="800" @unless ($loop->first) loading="lazy" @else fetchpriority="high" @endunless />
                    </picture>
                    <figcaption class="banner-copy">
                        <p class="silk">Tech Computer / Selección gamer</p>
                        <h2>{{ $banner->alt }}</h2>
                        <a class="btn btn-primary" href="{{ $banner->link ?: '#q-shop' }}" @unless ($loop->first) tabindex="-1" @endunless>{{ $banner->link ? 'Ver promoción' : 'Explorar catálogo' }} <span aria-hidden="true">↗</span></a>
                    </figcaption>
                </figure>
            @endforeach
        </div>
        <canvas class="banner-particles" aria-hidden="true" data-banner-particles></canvas>
        @if ($banners->count() > 1)
            <span class="carousel-progress" aria-hidden="true"><i data-carousel-progress></i></span>
            <button class="carousel-pause" type="button" data-carousel-pause aria-pressed="false" aria-label="Pausar carrusel"><span>Pausar</span></button>
            <button class="banners-arrow prev" type="button" aria-label="Anterior" data-banners-prev>‹</button>
            <button class="banners-arrow next" type="button" aria-label="Siguiente" data-banners-next>›</button>
            <div class="banners-dots" role="tablist" aria-label="Elegir promoción">
                @foreach ($banners as $banner)
                    <button type="button" role="tab" aria-label="Promoción {{ $loop->iteration }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-banners-dot="{{ $loop->index }}"><i></i></button>
                @endforeach
            </div>
        @endif
    </section>
@endif
