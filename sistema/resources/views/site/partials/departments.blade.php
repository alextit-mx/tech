<ul class="departments">
    @foreach ($departmentTiles as $tile)
        <li>
            <a class="dept hud-panel" href="{{ $tile['url'] }}">
                <span class="dept-photo showcase"><img src="{{ $tile['image'] }}" alt="{{ $tile['image_alt'] }}" width="240" height="180" loading="lazy" /></span>
                <span class="silk">Departamento</span>
                <span class="dept-name">{{ $tile['name'] }}</span>
                <span class="dept-count"><b data-count="{{ $tile['count'] }}">{{ number_format($tile['count']) }}</b> {{ $tile['count'] === 1 ? 'producto' : 'productos' }} <span aria-hidden="true">↗</span></span>
            </a>
        </li>
    @endforeach
</ul>
