@if ($paginator->hasPages())
    <nav class="pagination-nav" role="navigation" aria-label="Paginación">
        <p class="muted">
            Mostrando {{ $paginator->firstItem() ?? 0 }} a {{ $paginator->lastItem() ?? 0 }}
            de {{ $paginator->total() }} resultados
        </p>
        <div class="pagination-links">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary" aria-disabled="true">Anterior</span>
            @else
                <a class="btn btn-secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
            @endif
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="btn" aria-hidden="true">{{ $element }}</span>
                @elseif (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn btn-primary" aria-current="page" aria-label="Página {{ $page }}">{{ $page }}</span>
                        @else
                            <a class="btn btn-secondary" href="{{ $url }}" aria-label="Ir a la página {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if ($paginator->hasMorePages())
                <a class="btn btn-secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a>
            @else
                <span class="btn btn-secondary" aria-disabled="true">Siguiente</span>
            @endif
        </div>
    </nav>
@endif
