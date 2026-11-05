{{-- Secciones de tienda; las promociones vigentes quedan fuera de esta caché. --}}
@php($selection = collect($rails)->firstWhere('id', 'destacados'))
@if ($selection)
    <section class="store-section" id="destacados">
        <header class="rail-head"><div><p class="eyebrow">Disponibles para tu próximo armado</p><h2>Destacados</h2></div></header>
        <div class="p-grid">@foreach ($selection['items']->take(8) as $product) @include('site.partials.product-card') @endforeach</div>
    </section>
@endif
@if ($builds->isNotEmpty())
    @php($uses = $builds->pluck('use')->unique()->values())
    <section class="builds-showcase" id="equipos" data-build-tabs>
        <header class="rail-head">
            <div>
                <p class="eyebrow">Listos para armar</p>
                <h2>Equipos armados</h2>
                <p class="muted">Elige un equipo y personaliza sus piezas.</p>
            </div>
            @if ($uses->count() > 1)
                <div class="tabs" role="tablist" aria-label="Uso del equipo">
                    <button type="button" role="tab" aria-selected="true" data-tab="">Todos</button>
                    @foreach ($uses as $use)
                        <button type="button" role="tab" aria-selected="false" data-tab="{{ $use }}">{{ \App\Models\Build::USES[$use] ?? $use }}</button>
                    @endforeach
                </div>
            @endif
        </header>
        <div class="b-grid">
            @foreach ($builds as $build)
                @include('site.partials.build-card')
            @endforeach
        </div>
    </section>
@endif

<section class="store-section">
    <header class="rail-head"><div><p class="eyebrow">Explora</p><h2>Departamentos</h2></div></header>
    @include('site.partials.departments')
</section>

@foreach ($rails as $rail)
    @continue($rail['id'] === 'destacados')
    @include('site.partials.rail', $rail)
    @if ($loop->iteration === 2)
        <section class="lab-cta hud-panel">
            <div>
                <p class="eyebrow">Arma tu PC</p>
                <h2>Tu PC, pieza por pieza.</h2>
                <p>Elige componentes, revisa compatibilidad y cotiza.</p>
            </div>
            <a class="btn btn-primary" href="/arma-tu-pc">Abrir el configurador</a>
        </section>
    @endif
@endforeach

@if ($brands->isNotEmpty())
    <section class="store-section brand-strip" aria-label="Marcas">
        <header class="rail-head"><div><p class="eyebrow">En la tienda</p><h2>Marcas</h2></div></header>
        <ul>
            @foreach ($brands as $brand)
                <li><a href="/buscar?q={{ urlencode($brand->name) }}">{{ $brand->name }}<small>{{ $brand->products_count }}</small></a></li>
            @endforeach
        </ul>
    </section>
@endif
