@extends('layouts.app')

@section('content')

<div class="cmp-page-head cmp-page-head-modern">
    <div>
        <h1 class="cmp-title">{{ __('messages.products') }}</h1>
    </div>

   <div class="cmp-page-head-actions">
    <a href="{{ route('products.import.create') }}" class="btn">
        Import Products
    </a>

    <a href="{{ route('products.create') }}" class="btn">
        {{ __('messages.add_product') }}
    </a>
</div>
</div>

@if(session('success'))
    <div class="alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="cmp-toolbar-shell">
    <form method="GET" class="cmp-toolbar-form" id="productsSearchForm">
        <div class="cmp-toolbar-main" style="justify-content: center;">
            <div class="cmp-toolbar-field cmp-toolbar-field-search cmp-toolbar-field-search-compact">
                <label class="cmp-toolbar-label">{{ __('messages.search') }}</label>
                <input
                    type="text"
                    name="search"
                    id="productsSearchInput"
                    value="{{ request('search') }}"
                    placeholder="{{ __('messages.search_products') }}"
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
                <th>{{ __('messages.sku') }}</th>
                <th>{{ __('messages.ean') }}</th>
                <th>{{ __('messages.brand') }}</th>
                <th>{{ __('messages.our_price') }}</th>
                <th>{{ __('messages.status') }}</th>
                <th>{{ __('messages.actions') }}</th>
            </tr>
        </thead>

        <tbody>
            @forelse($products as $product)
                <tr>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->sku }}</td>
                    <td>{{ $product->ean }}</td>
                    <td>{{ $product->brand }}</td>
                    <td>{{ $product->our_price }}</td>

                    <td>
                        @if($product->is_active)
                            <span class="badge-green">{{ __('messages.active') }}</span>
                        @else
                            <span class="badge-red">{{ __('messages.inactive') }}</span>
                        @endif
                    </td>

                    <td>
                        <div class="table-actions">
                            <a href="{{ route('products.show', $product) }}" class="icon-btn" title="{{ __('messages.view') }}" aria-label="{{ __('messages.view') }}">
                                <i data-lucide="eye"></i>
                            </a>

                            <a href="{{ route('products.edit', $product) }}" class="icon-btn" title="{{ __('messages.edit') }}" aria-label="{{ __('messages.edit') }}">
                                <i data-lucide="pencil"></i>
                            </a>

                            <form action="{{ route('products.destroy', $product) }}" method="POST" onsubmit="return confirm('{{ __('messages.delete_confirm') }}')">
                                @csrf
                                @method('DELETE')

                                <button type="submit" class="icon-btn danger" title="{{ __('messages.delete') }}" aria-label="{{ __('messages.delete') }}">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">{{ __('messages.no_products') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:20px;">
    {{ $products->links() }}
</div>

<script>
    let productsSearchTimeout = null;

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('productsSearchInput');
        const searchForm = document.getElementById('productsSearchForm');

        if (searchInput && searchForm) {
            searchInput.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                searchForm.submit();
            });
        }
    });
</script>

<style>
.cmp-toolbar-main {
    display: flex;
    justify-content: center !important;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 16px;
}
.cmp-toolbar-field-search-compact {
    min-width: 350px;
}
</style>

@endsection