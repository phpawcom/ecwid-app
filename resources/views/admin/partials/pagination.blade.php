@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="disabled">‹ Newer</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}">‹ Newer</a>
        @endif
        <span class="current">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}">Older ›</a>
        @else
            <span class="disabled">Older ›</span>
        @endif
        <span class="muted" style="border: none; background: none">{{ number_format($paginator->total()) }} entries</span>
    </nav>
@endif
