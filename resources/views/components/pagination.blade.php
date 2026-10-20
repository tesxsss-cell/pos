<nav class="table-pagination no-print" aria-label="Pagination tabel" data-server-pagination>
<span class="pagination-summary">{{ $paginator->total() ? $paginator->firstItem() : 0 }}–{{ $paginator->total() ? $paginator->lastItem() : 0 }} dari {{ $paginator->total() }} data · 10 / halaman</span>
<div class="pagination-actions">
@if($paginator->onFirstPage())<span class="page-button is-disabled" aria-disabled="true">Sebelumnya</span>@else<a class="page-button" href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>@endif
<span>Hal. {{ $paginator->currentPage() }} / {{ max(1,$paginator->lastPage()) }}</span>
@if($paginator->hasMorePages())<a class="page-button" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>@else<span class="page-button is-disabled" aria-disabled="true">Berikutnya</span>@endif
</div></nav>
