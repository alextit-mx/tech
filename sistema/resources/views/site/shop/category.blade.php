@extends('layouts.site')

@section('title', $category->name.' | Tienda Tech Computer')
@section('description', $category->description ?: $category->name.' con precio con IVA y existencias en Tech Computer, Plaza de la Tecnología, CDMX.')

@section('before')
<div class="backdrop" aria-hidden="true"></div>
@endsection

@php
    $query = request()->query();
    $link = fn (array $changes) => url()->current().'?'.http_build_query(array_filter(array_merge($query, $changes, ['page' => null]), fn ($v) => $v !== null && $v !== ''));
@endphp

@section('content')
<main class="page shop">
    <nav class="crumbs" aria-label="Ruta">
        <a href="/tienda">Tienda</a>
        @if ($category->parent)<a href="{{ route('shop.category', $category->parent) }}">{{ $category->parent->name }}</a>@endif
        <span aria-current="page">{{ $category->name }}</span>
    </nav>
    <div class="page-head">
        <h1>{{ $category->name }}</h1>
        <p class="intro">{{ $products->total() }} {{ $products->total() === 1 ? 'producto' : 'productos' }} · precios con IVA incluido</p>
    </div>

    <div class="shop-layout">
        <aside class="filters" aria-label="Filtros">
            @if ($category->children->isNotEmpty())
                <div class="filter-group">
                    <h2 class="silk">Tipo</h2>
                    <a href="{{ $link(['sub' => null]) }}" @class(['chip', 'on' => ! request('sub')])>Todo</a>
                    @foreach ($category->children as $child)
                        @if ($counts[$child->id] ?? false)
                            <a href="{{ $link(['sub' => $child->slug]) }}" @class(['chip', 'on' => request('sub') === $child->slug])>{{ $child->name }} <small>{{ $counts[$child->id] }}</small></a>
                        @endif
                    @endforeach
                </div>
            @endif
            @if ($brands->isNotEmpty())
                <div class="filter-group">
                    <h2 class="silk">Marca</h2>
                    <a href="{{ $link(['marca' => null]) }}" @class(['chip', 'on' => ! request('marca')])>Todas</a>
                    @foreach ($brands as $brand)
                        <a href="{{ $link(['marca' => $brand->slug]) }}" @class(['chip', 'on' => request('marca') === $brand->slug])>{{ $brand->name }}</a>
                    @endforeach
                </div>
            @endif
            <form class="filter-group" method="get">
                @foreach (array_intersect_key($query, array_flip(['sub', 'marca'])) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
                @endforeach
                <h2 class="silk">Precio</h2>
                <div class="range">
                    <label><span class="sr-only">Mínimo</span><input type="number" name="min" min="0" step="100" placeholder="Mín." value="{{ request('min') }}" /></label>
                    <label><span class="sr-only">Máximo</span><input type="number" name="max" min="0" step="100" placeholder="Máx." value="{{ request('max') }}" /></label>
                </div>
                <label class="check"><input type="checkbox" name="existencia" value="1" @checked(request()->boolean('existencia')) /> Solo en existencia</label>
                <label class="sort">Ordenar
                    <select name="orden">
                        <option value="">Recomendados</option>
                        <option value="precio" @selected(request('orden') === 'precio')>Menor precio</option>
                        <option value="precio-desc" @selected(request('orden') === 'precio-desc')>Mayor precio</option>
                        <option value="nombre" @selected(request('orden') === 'nombre')>Nombre</option>
                    </select>
                </label>
                <button class="btn btn-ghost btn-sm" type="submit">Aplicar</button>
            </form>
        </aside>

        <section aria-label="Productos">
            @if ($products->isEmpty())
                <div class="empty">
                    <p>No hay productos con esos filtros. Pregúntanos por WhatsApp: muchas piezas las conseguimos el mismo día.</p>
                    <a class="btn btn-primary btn-sm" data-whats="Hola Tech Computer, busco {{ $category->name }}" href="#">Preguntar por WhatsApp</a>
                </div>
            @else
                <div class="p-grid">
                    @foreach ($products as $product)
                        @include('site.partials.product-card')
                    @endforeach
                </div>
                @include('site.partials.pager', ['paginator' => $products])
            @endif
        </section>
    </div>
</main>
@endsection
