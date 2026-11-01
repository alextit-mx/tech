@if ($paginator->hasPages())
    <nav class="pager" aria-label="Páginas">
        @if ($paginator->previousPageUrl())<a class="btn btn-ghost btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>@endif
        <span>Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>
        @if ($paginator->nextPageUrl())<a class="btn btn-ghost btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a>@endif
    </nav>
@endif
