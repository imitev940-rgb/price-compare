@extends('layouts.app')

@section('content')

@if(session('success'))
    <div class="alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="cmp-page-head cmp-page-head-modern">
    <div>
        <h1 class="cmp-title">{{ __('messages.stores') }}</h1>
        <p class="cmp-subtitle">{{ __('messages.manage_stores') }}</p>
    </div>

    <div class="cmp-page-head-actions">
        <a href="{{ route('stores.create') }}" class="btn">
            {{ __('messages.add_store') }}
        </a>
    </div>
</div>

<div class="cmp-toolbar-shell">
    <form method="GET" class="cmp-toolbar-form" id="storesSearchForm">
        <div class="cmp-toolbar-main">
            <div class="cmp-toolbar-field cmp-toolbar-field-search cmp-toolbar-field-search-compact">
                <label class="cmp-toolbar-label">{{ __('messages.search') }}</label>
                <input
                    type="text"
                    name="search"
                    id="storesSearchInput"
                    value="{{ request('search') }}"
                    placeholder="{{ __('messages.search_stores') }}"
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
                <th>{{ __('messages.name') }}</th>
                <th>{{ __('messages.url') }}</th>
                <th style="width:140px;">{{ __('messages.actions') }}</th>
            </tr>
        </thead>

        <tbody>
            @forelse($stores as $store)
                <tr>
                    <td>{{ $store->name }}</td>

                    <td style="max-width:420px; word-break:break-word;">
                        @if($store->base_url)
                            <a href="{{ $store->base_url }}" target="_blank">
                                {{ $store->base_url }}
                            </a>
                        @elseif($store->url)
                            <a href="{{ $store->url }}" target="_blank">
                                {{ $store->url }}
                            </a>
                        @else
                            —
                        @endif
                    </td>

                    <td>
                        <div class="table-actions">
                            <a href="{{ route('stores.edit', $store) }}"
                               class="icon-btn"
                               title="{{ __('messages.edit') }}"
                               aria-label="{{ __('messages.edit') }}">
                                <i data-lucide="pencil"></i>
                            </a>

                            <form
                                action="{{ route('stores.destroy', $store) }}"
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
                    <td colspan="3">{{ __('messages.no_stores') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:20px;">
    {{ $stores->links() }}
</div>

<script>
    let storesSearchTimeout = null;

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('storesSearchInput');
        const searchForm = document.getElementById('storesSearchForm');

        if (searchInput && searchForm) {
            searchInput.addEventListener('input', function () {
                clearTimeout(storesSearchTimeout);

                storesSearchTimeout = setTimeout(() => {
                    searchForm.submit();
                }, 500);
            });
        }
    });
</script>

@endsection