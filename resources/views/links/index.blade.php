@extends('layouts.app')

@section('content')

@if(session('success'))
    <div class="alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="cmp-page-head cmp-page-head-modern">
    <div>
        <h1 class="cmp-title">{{ __('messages.competitor_links') }}</h1>
        <p class="cmp-subtitle">{{ __('messages.manage_links') }}</p>
    </div>

    <div class="cmp-page-head-actions">
        <a href="{{ route('links.create') }}" class="btn">
            {{ __('messages.add_link') }}
        </a>
    </div>
</div>

<div class="cmp-toolbar-shell">
    <form method="GET" class="cmp-toolbar-form" id="linksSearchForm">
        <div class="cmp-toolbar-main">
            <div class="cmp-toolbar-field cmp-toolbar-field-search cmp-toolbar-field-search-compact">
                <label class="cmp-toolbar-label">{{ __('messages.search') }}</label>
                <input
                    type="text"
                    name="search"
                    id="linksSearchInput"
                    value="{{ request('search') }}"
                    placeholder="{{ __('messages.search_links') }}"
                    class="cmp-toolbar-input"
                >
            </div>

            <div class="cmp-toolbar-field cmp-toolbar-field-small">
                <label for="per_page" class="cmp-toolbar-label">{{ __('messages.per_page') }}</label>
                <select id="per_page" name="per_page" class="cmp-toolbar-select" onchange="this.form.submit()">
                    <option value="10" {{ (int) request('per_page', 10) === 10 ? 'selected' : '' }}>10</option>
                    <option value="25" {{ (int) request('per_page') === 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ (int) request('per_page') === 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ (int) request('per_page') === 100 ? 'selected' : '' }}>100</option>
                </select>
            </div>
        </div>
    </form>
</div>

<div class="cmp-table-wrap">
    <table class="cmp-table">
        <thead>
            <tr>
                <th>{{ __('messages.product') }}</th>
                <th>{{ __('messages.store') }}</th>
                <th>{{ __('messages.url') }}</th>
                <th>{{ __('messages.last_price') }}</th>
                <th style="width:140px;">{{ __('messages.actions') }}</th>
            </tr>
        </thead>

        <tbody>
            @forelse($links as $link)
                <tr>
                    <td>{{ $link->product->name ?? '—' }}</td>

                    <td>{{ $link->store->name ?? '—' }}</td>

                    <td style="max-width:420px; word-break:break-word;">
                        @if($link->product_url)
                            <a href="{{ $link->product_url }}" target="_blank">
                                {{ $link->product_url }}
                            </a>
                        @else
                            —
                        @endif
                    </td>

                    <td>
                        {{ $link->last_price !== null ? number_format($link->last_price, 2) . ' €' : '—' }}
                    </td>

                    <td>
                        <div class="table-actions">
                            <a href="{{ route('links.edit', $link) }}"
                               class="icon-btn"
                               title="{{ __('messages.edit') }}"
                               aria-label="{{ __('messages.edit') }}">
                                <i data-lucide="pencil"></i>
                            </a>

                            <form
                                action="{{ route('links.destroy', $link) }}"
                                method="POST"
                                onsubmit="return confirm('{{ __('messages.delete_confirm') }}');"
                            >
                                @csrf
                                @method('DELETE')

                                <button type="submit"
                                        class="icon-btn danger"
                                        title="{{ __('messages.delete') }}"
                                        aria-label="{{ __('messages.delete') }}">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">{{ __('messages.no_links') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:20px;">
    {{ $links->links() }}
</div>

<script>
    let linksSearchTimeout = null;

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('linksSearchInput');
        const searchForm = document.getElementById('linksSearchForm');

        if (searchInput && searchForm) {
            searchInput.addEventListener('input', function () {
                clearTimeout(linksSearchTimeout);

                linksSearchTimeout = setTimeout(() => {
                    searchForm.submit();
                }, 500);
            });
        }
    });
</script>

@endsection