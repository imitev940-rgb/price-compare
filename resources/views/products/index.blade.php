@extends('layouts.app')

@section('content')

@if(session('success'))
    <div class="alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="cmp-page-head">
    <div>
        <h1 class="cmp-title">Products</h1>
        <p class="cmp-subtitle">Manage your tracked products.</p>
    </div>

    <a href="{{ route('products.create') }}" class="btn">Add Product</a>
</div>

<form method="GET" action="{{ route('products.index') }}" class="cmp-search-form" style="margin-bottom: 20px;">
    <input
        type="text"
        name="search"
        class="cmp-search-input"
        placeholder="Search by name, SKU, EAN or brand..."
        value="{{ request('search') }}"
    >
    <button type="submit" class="btn">Search</button>
</form>

<div class="cmp-table-wrap">
    <table class="cmp-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>SKU</th>
                <th>EAN</th>
                <th>Brand</th>
                <th>Our Price</th>
                <th>Status</th>
                <th style="width: 320px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($products as $product)
                <tr>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->sku ?: '—' }}</td>
                    <td>{{ $product->ean ?: '—' }}</td>
                    <td>{{ $product->brand ?: '—' }}</td>
                    <td>{{ number_format($product->our_price, 2) }} €</td>
                    <td>
                        @if($product->is_active)
                            <span class="badge-green">Active</span>
                        @else
                            <span class="badge-red">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; gap:10px; flex-wrap:wrap;">
                            <a href="{{ route('products.show', $product) }}" class="btn">View</a>
                            <a href="{{ route('products.edit', $product) }}" class="btn">Edit</a>

                            <form action="{{ route('products.destroy', $product) }}" method="POST" onsubmit="return confirm('Сигурен ли си, че искаш да изтриеш този продукт?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Няма добавени продукти.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:20px;">
    {{ $products->links() }}
</div>

@endsection