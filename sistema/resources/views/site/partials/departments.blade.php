@php($icons = ['pcs-armadas' => 'monitor', 'componentes' => 'cpu', 'perifericos-gamer' => 'gamepad-2', 'accesorios' => 'cable', 'impresoras-y-consumibles' => 'printer'])
<ul class="departments">
    @foreach ($departments as $department)
        <li>
            <a class="dept" href="{{ route('shop.category', $department) }}">
                <span class="glyph"><span class="mask" style="--icon: url('https://unpkg.com/lucide-static@0.469.0/icons/{{ $icons[$department->slug] ?? 'box' }}.svg')"></span></span>
                <span class="dept-name">{{ $department->name }}</span>
                @if ($department->children->isNotEmpty())
                    <span class="dept-subs">{{ $department->children->pluck('name')->take(5)->join(' · ') }}</span>
                @endif
            </a>
        </li>
    @endforeach
</ul>
