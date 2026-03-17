@extends('layouts.app')

@section('content')

<div class="cmp-page-head">
    <div>
        <h1 class="cmp-title">{{ $product->name }}</h1>
        <p class="cmp-subtitle">Product details and linked competitor URLs.</p>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="{{ route('products.edit', $product) }}" class="btn">Edit Product</a>
        <a href="{{ route('products.index') }}" class="btn">Back</a>
    </div>
</div>

<div class="panel-card" style="margin-bottom: 24px;">
    <div class="mb-4"><strong>Name:</strong> {{ $product->name }}</div>
    <div class="mb-4"><strong>SKU:</strong> {{ $product->sku ?: '—' }}</div>
    <div class="mb-4"><strong>EAN:</strong> {{ $product->ean ?: '—' }}</div>
    <div class="mb-4"><strong>Brand:</strong> {{ $product->brand ?: '—' }}</div>
    <div class="mb-4"><strong>Our Price:</strong> {{ number_format($product->our_price, 2) }} €</div>
    <div class="mb-4">
        <strong>Status:</strong>
        @if($product->is_active)
            <span class="badge-green">Active</span>
        @else
            <span class="badge-red">Inactive</span>
        @endif
    </div>
</div>

<h2 style="margin-bottom:16px;">Competitor Links</h2>

<div class="cmp-table-wrap">
    <table class="cmp-table">
        <thead>
            <tr>
                <th>Store</th>
                <th>URL</th>
                <th>Last Price</th>
            </tr>
        </thead>
        <tbody>
            @forelse($product->competitorLinks as $link)
                <tr>
                    <td>{{ $link->store->name ?? '—' }}</td>
                    <td style="max-width: 500px; word-break: break-word;">{{ $link->url }}</td>
                    <td>{{ $link->last_price !== null ? number_format($link->last_price, 2) . ' €' : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">Няма competitor links за този продукт.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection