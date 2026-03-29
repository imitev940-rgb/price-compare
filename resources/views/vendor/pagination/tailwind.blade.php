@if ($paginator->hasPages())
    <nav class="app-pagination" role="navigation" aria-label="Pagination Navigation">

        <div class="app-pagination-mobile">
            @if ($paginator->onFirstPage())
                <span class="pg-btn disabled">{{ __('pagination.previous') }}</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pg-btn">
                    {{ __('pagination.previous') }}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pg-btn">
                    {{ __('pagination.next') }}
                </a>
            @else
                <span class="pg-btn disabled">{{ __('pagination.next') }}</span>
            @endif
        </div>

        <div class="app-pagination-desktop">
            <div class="app-pagination-summary">
                @if ($paginator->firstItem())
                    {{ __('Showing') }}
                    <strong>{{ $paginator->firstItem() }}</strong>
                    {{ __('to') }}
                    <strong>{{ $paginator->lastItem() }}</strong>
                    {{ __('of') }}
                    <strong>{{ $paginator->total() }}</strong>
                    {{ __('results') }}
                @else
                    {{ __('Showing') }}
                    <strong>{{ $paginator->count() }}</strong>
                    {{ __('results') }}
                @endif
            </div>

            <div class="app-pagination-pages">
                @if ($paginator->onFirstPage())
                    <span class="pg-btn disabled" aria-disabled="true">«</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pg-btn" aria-label="{{ __('pagination.previous') }}">«</a>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="pg-dots">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="pg-btn active" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="pg-btn" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pg-btn" aria-label="{{ __('pagination.next') }}">»</a>
                @else
                    <span class="pg-btn disabled" aria-disabled="true">»</span>
                @endif
            </div>
        </div>
    </nav>
@endif