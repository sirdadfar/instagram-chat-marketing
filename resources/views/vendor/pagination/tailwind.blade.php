@php
    $current = $paginator->currentPage();
    $last = max(1, $paginator->lastPage());
    $start = max(1, $current - 2);
    $end = min($last, $current + 2);
    $fa = static fn ($value): string => \App\Support\PersianText::digits($value);
@endphp
@if ($paginator->hasPages())
<nav class="panel-pagination" role="navigation" aria-label="صفحه‌بندی نتایج">
    <p class="pagination-summary">
        نمایش {{ $fa($paginator->firstItem() ?? 0) }} تا {{ $fa($paginator->lastItem() ?? 0) }} از {{ $fa($paginator->total()) }} نتیجه
    </p>
    <div class="pagination-links">
        @if ($paginator->onFirstPage())
            <span class="page-btn is-disabled" aria-disabled="true">قبل</span>
        @else
            <a class="page-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">قبل</a>
        @endif

        @if ($start > 1)
            <a class="page-btn" href="{{ $paginator->url(1) }}" aria-label="صفحه {{ $fa(1) }}">{{ $fa(1) }}</a>
            @if ($start > 2)
                <span class="page-ellipsis" aria-hidden="true">…</span>
            @endif
        @endif

        @for ($page = $start; $page <= $end; $page++)
            @if ($page === $current)
                <span class="page-btn is-active" aria-current="page">{{ $fa($page) }}</span>
            @else
                <a class="page-btn" href="{{ $paginator->url($page) }}" aria-label="صفحه {{ $fa($page) }}">{{ $fa($page) }}</a>
            @endif
        @endfor

        @if ($end < $last)
            @if ($end < $last - 1)
                <span class="page-ellipsis" aria-hidden="true">…</span>
            @endif
            <a class="page-btn" href="{{ $paginator->url($last) }}" aria-label="صفحه {{ $fa($last) }}">{{ $fa($last) }}</a>
        @endif

        @if ($paginator->hasMorePages())
            <a class="page-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">بعد</a>
        @else
            <span class="page-btn is-disabled" aria-disabled="true">بعد</span>
        @endif
    </div>
</nav>
@endif
