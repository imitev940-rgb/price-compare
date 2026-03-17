@extends('layouts.app')

@section('content')

@if(session('success'))
    <div class="alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="cmp-page-head">
    <div>
        <h1 class="cmp-title">Price Comparison</h1>
        <p class="cmp-subtitle">
            Monitor your products against competitor prices in one clean view.
        </p>
    </div>
</div>

<div class="cmp-stats-grid dashboard-cards">

    <div class="cmp-stat-card">
        <div class="cmp-stat-left">
            <div class="cmp-stat-icon products-icon">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M4 7.5L12 3l8 4.5M4 7.5l8 4.5m-8-4.5V16.5L12 21m0-9l8-4.5m-8 4.5V21m8-13.5V16.5L12 21"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
        </div>
        <div class="cmp-stat-content">
            <div class="card-title">Products</div>
            <div class="card-value">{{ $productsCount }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-left">
            <div class="cmp-stat-icon stores-icon">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M3 10h18M5 10V7.5L7 4h10l2 3.5V10M6 10v8h12v-8M9 14h6"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
        </div>
        <div class="cmp-stat-content">
            <div class="card-title">Stores</div>
            <div class="card-value">{{ $storesCount }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-left">
            <div class="cmp-stat-icon links-icon">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M10 13a5 5 0 0 1 0-7l1.5-1.5a5 5 0 1 1 7 7L17 13M14 11a5 5 0 0 1 0 7L12.5 19.5a5 5 0 0 1-7-7L7 11"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
        </div>
        <div class="cmp-stat-content">
            <div class="card-title">Competitor Links</div>
            <div class="card-value">{{ $linksCount }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-left">
            <div class="cmp-stat-icon wins-icon">
                <svg viewBox="0 0 24 24" fill="none">
                    <path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4ZM5 6H3a2 2 0 0 0 2 2M19 6h2a2 2 0 0 1-2 2"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
        </div>
        <div class="cmp-stat-content">
            <div class="card-title">Best Price Wins</div>
            <div class="card-value">{{ $bestPriceWins }}</div>
        </div>
    </div>

</div>

<div class="cmp-toolbar">

    <form action="{{ route('prices.check') }}" method="POST" class="cmp-update-form">
        @csrf
        <button type="submit" class="btn">
            Update Prices
        </button>
    </form>

    <form method="GET" action="{{ route('comparison') }}" class="cmp-search-form">
        <input
            type="text"
            name="search"
            class="cmp-search-input"
            placeholder="Search product, SKU, brand..."
            value="{{ request('search') }}"
        >

        <button type="submit" class="btn">Search</button>

        <button type="submit" name="sort" value="name_asc" class="cmp-sort-btn">
            Name ↑
        </button>

        <button type="submit" name="sort" value="name_desc" class="cmp-sort-btn">
            Name ↓
        </button>

        <button type="submit" name="sort" value="price_asc" class="cmp-sort-btn">
            Price ↑
        </button>

        <button type="submit" name="sort" value="price_desc" class="cmp-sort-btn">
            Price ↓
        </button>
    </form>

</div>

<div class="cmp-table-wrap">
    <table class="cmp-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Our Price</th>
                <th>Best Competitor</th>
                <th>Competitor Price</th>
                <th>Difference</th>
                <th>Percent</th>
                <th>Position</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
            @foreach($products as $product)
                @php
                    $bestPrice = null;
                    $bestStore = null;

                    foreach ($product->competitorLinks as $link) {
                        if ($link->last_price !== null) {
                            if ($bestPrice === null || $link->last_price < $bestPrice) {
                                $bestPrice = $link->last_price;
                                $bestStore = $link->store->name ?? null;
                            }
                        }
                    }

                    $diff = null;
                    $percent = null;
                    $position = null;

                    if ($bestPrice !== null) {
                        $diff = $bestPrice - $product->our_price;
                        $percent = $bestPrice > 0 ? ($diff / $bestPrice) * 100 : 0;

                        if ($product->our_price < $bestPrice) {
                            $position = '#1 Best Price';
                        } elseif ($product->our_price > $bestPrice) {
                            $position = 'Behind competitor';
                        } else {
                            $position = 'Same price';
                        }
                    }
                @endphp

                <tr>
                    <td>
                        <div class="cmp-product-cell">
                            <div class="cmp-product-name">{{ $product->name }}</div>
                        </div>
                    </td>

                    <td class="cmp-price">{{ number_format($product->our_price, 2) }} €</td>

                    <td>{{ $bestStore ?? '—' }}</td>

                    <td class="cmp-price">
                        @if($bestPrice !== null)
                            {{ number_format($bestPrice, 2) }} €
                        @else
                            —
                        @endif
                    </td>

                    <td class="cmp-price">
                        @if($diff !== null)
                            @if($diff > 0)
                                <span class="cmp-diff-positive">{{ number_format($diff, 2) }} €</span>
                            @elseif($diff < 0)
                                <span class="cmp-diff-negative">-{{ number_format(abs($diff), 2) }} €</span>
                            @else
                                <span class="cmp-diff-neutral">0.00 €</span>
                            @endif
                        @else
                            —
                        @endif
                    </td>

                    <td>
                        @if($percent !== null)
                            {{ number_format(abs($percent), 1) }}%
                        @else
                            —
                        @endif
                    </td>

                    <td>
                        @if($position === '#1 Best Price')
                            <span class="badge-green">{{ $position }}</span>
                        @elseif($position === 'Behind competitor')
                            <span class="badge-red">{{ $position }}</span>
                        @elseif($position === 'Same price')
                            <span class="badge-yellow">{{ $position }}</span>
                        @else
                            <span class="badge-blue">No data</span>
                        @endif
                    </td>

                    <td>
                        @if($diff !== null)
                            @if($diff > 0)
                                <span class="badge-green">Cheaper</span>
                            @elseif($diff < 0)
                                <span class="badge-red">More expensive</span>
                            @else
                                <span class="badge-yellow">Same price</span>
                            @endif
                        @else
                            <span class="badge-blue">No data</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@endsection