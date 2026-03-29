@extends('layouts.app')

@section('content')

@if(session('success'))
    <div class="alert-success">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert-error">
        {{ session('error') }}
    </div>
@endif

<div class="cmp-page-head">
    <div>
        <h1 class="cmp-title">{{ $product->name }}</h1>
        <p class="cmp-subtitle">Product details and linked competitor URLs.</p>
    </div>

    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <form action="{{ route('products.auto-search', $product) }}" method="POST" style="margin:0;">
            @csrf
            <button type="submit" class="btn">Auto Search Links</button>
        </form>

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
                <th>Matched Title</th>
                <th>Last Price</th>
                <th>Status</th>
                <th>Last Checked</th>
                <th>Auto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($product->competitorLinks as $link)
                <tr>
                    <td>{{ $link->store->name ?? '—' }}</td>

                    <td style="max-width: 420px; word-break: break-word;">
                        @if(!empty($link->product_url) && $link->product_url !== '#')
                            <a href="{{ $link->product_url }}" target="_blank" rel="noopener noreferrer">
                                {{ $link->product_url }}
                            </a>
                        @else
                            —
                        @endif
                    </td>

                    <td style="max-width: 280px; word-break: break-word;">
                        {{ $link->matched_title ?: ($link->competitor_product_name ?: '—') }}
                    </td>

                    <td>
                        {{ $link->last_price !== null ? number_format($link->last_price, 2) . ' €' : '—' }}
                    </td>

                    <td>
                        @php
                            $status = $link->search_status;
                        @endphp

                        @if($status === 'found')
                            <span class="badge-green">Found</span>
                        @elseif($status === 'pending_parser')
                            <span class="badge-yellow">Pending</span>
                        @elseif($status === 'not_found')
                            <span class="badge-red">Not Found</span>
                        @elseif($status === 'blocked')
                            <span class="badge-red">Blocked</span>
                        @elseif($status === 'price_not_found')
                            <span class="badge-red">No Price</span>
                        @elseif($status === 'request_failed')
                            <span class="badge-red">Request Failed</span>
                        @elseif($status === 'invalid_url')
                            <span class="badge-red">Invalid URL</span>
                        @elseif($status === 'error')
                            <span class="badge-red">Error</span>
                        @else
                            <span class="badge-gray">{{ $status ?: '—' }}</span>
                        @endif
                    </td>

                    <td>
                        {{ $link->last_checked_at ? $link->last_checked_at->format('d.m.Y H:i') : '—' }}
                    </td>

                    <td>
                        @if($link->is_auto_found)
                            <span class="badge-blue">Yes</span>
                        @else
                            <span class="badge-gray">No</span>
                        @endif
                    </td>
                </tr>

                @if(!empty($link->last_error))
                    <tr>
                        <td colspan="7" style="background:#fff8f8; color:#b91c1c; font-size:13px;">
                            <strong>Error:</strong> {{ $link->last_error }}
                        </td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="7">Няма competitor links за този продукт.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection