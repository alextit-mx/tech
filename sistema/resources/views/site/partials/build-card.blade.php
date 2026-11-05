@php
    $total = $build->total();
    [$level, $label] = $build->availability();
    $parts = $build->parts();
@endphp
<article class="b-card hud-panel" data-use="{{ $build->use }}">
    <a @class(['b-media', 'showcase' => $build->imageUrl(), 'is-parts' => ! $build->imageUrl()]) href="{{ route('shop.build', $build) }}" tabindex="-1" aria-hidden="true">
        @if ($build->imageUrl())
            <img src="{{ $build->imageUrl() }}" alt="" width="640" height="400" loading="lazy" />
        @else
            <span class="b-parts">
                @forelse ($parts->filter(fn ($part) => $part->imageUrl())->take(4) as $part)
                    <span class="showcase"><img src="{{ $part->imageUrl() }}" alt="" width="160" height="160" loading="lazy" /></span>
                @empty
                    <span class="showcase is-pending">@include('site.partials.photo-pending')</span>
                @endforelse
            </span>
        @endif
        <span class="silk">{{ \App\Models\Build::USES[$build->use] ?? '' }}</span>
    </a>
    <div class="b-body">
        <h3><a href="{{ route('shop.build', $build) }}">{{ $build->name }}</a></h3>
        @if ($build->tagline)<p class="b-tag">{{ $build->tagline }}</p>@endif
        <dl class="b-specs">
            @foreach ($build->highlights() as $h)
                <div><dt>{{ $h['label'] }}</dt><dd>{{ $h['value'] }}</dd></div>
            @endforeach
        </dl>
        <div class="p-foot">
            @if ($total !== null)
                <span class="p-price">{{ money($total) }}<small>{{ $parts->count() }} piezas · IVA incluido</small></span>
            @else
                <span class="p-price is-quote">Cotizar<small>{{ $parts->count() }} piezas</small></span>
            @endif
            <span class="stock" data-level="{{ $level }}">{{ $label }}</span>
        </div>
        <div class="b-actions">
            <a class="btn btn-primary btn-sm" href="{{ $build->builderUrl() }}">Personalízalo</a>
            <button class="btn btn-ghost btn-sm" type="button" data-add-build="{{ $parts->pluck('id')->implode(',') }}" data-name="{{ $build->name }}">Agregar a mi cotización</button>
        </div>
    </div>
</article>
