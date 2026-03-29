@extends('layouts.app')

@section('content')

@if(session('success'))
    <div class="alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="cmp-page-head cmp-page-head-modern">
    <div>
        <h1 class="cmp-title">Price History</h1>
        <p class="cmp-subtitle">Track all saved competitor price checks over time.</p>
    </div>
</div>

<div class="cmp-toolbar-shell">
    <form method="GET" action="{{ route('price-history.index') }}" class="cmp-toolbar-form" id="priceHistorySearchForm">
        <div class="cmp-toolbar-main">
            <div class="cmp-toolbar-field cmp-toolbar-field-search cmp-toolbar-field-search-compact">
                <label class="cmp-toolbar-label">Search</label>
                <input
                    type="text"
                    name="search"
                    id="priceHistorySearchInput"
                    value="{{ request('search') }}"
                    placeholder="Search product, SKU, brand, store..."
                    class="cmp-toolbar-input"
                >
            </div>
        </div>

        @if(request('search'))
            <div class="cmp-toolbar-side">
                <a href="{{ route('price-history.index') }}" class="btn">Clear</a>
            </div>
        @endif
    </form>
</div>

<div class="cmp-table-wrap">
    <table class="cmp-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Product</th>
                <th>Store</th>
                <th>Our Price</th>
                <th>Competitor Price</th>
                <th>Difference</th>
                <th>% Diff</th>
                <th>Position</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($histories as $history)
                @php
                    $difference = $history->difference;
                    $percentDifference = $history->percent_difference;
                    $status = $history->status;

                    $differenceClass = 'cmp-diff-neutral';
                    if ($difference !== null) {
                        if ((float) $difference < 0) {
                            $differenceClass = 'cmp-diff-positive';
                        } elseif ((float) $difference > 0) {
                            $differenceClass = 'cmp-diff-negative';
                        }
                    }

                    $percentClass = 'cmp-diff-neutral';
                    if ($percentDifference !== null) {
                        if ((float) $percentDifference < 0) {
                            $percentClass = 'cmp-diff-positive';
                        } elseif ((float) $percentDifference > 0) {
                            $percentClass = 'cmp-diff-negative';
                        }
                    }

                    $badgeClass = 'badge-blue';
                    if ($status === 'Cheaper') {
                        $badgeClass = 'badge-green';
                    } elseif ($status === 'More Expensive') {
                        $badgeClass = 'badge-red';
                    } elseif ($status === 'Match') {
                        $badgeClass = 'badge-yellow';
                    }
                @endphp

                <tr>
                    <td>{{ $history->checked_at ? $history->checked_at->format('d.m.Y H:i') : '—' }}</td>
                    <td>{{ $history->product->name ?? '—' }}</td>
                    <td>{{ $history->store->name ?? '—' }}</td>

                    <td class="cmp-price">
                        {{ $history->our_price !== null ? number_format((float) $history->our_price, 2) . ' €' : '—' }}
                    </td>

                    <td class="cmp-price">
                        {{ $history->competitor_price !== null ? number_format((float) $history->competitor_price, 2) . ' €' : '—' }}
                    </td>

                    <td>
                        @if($difference !== null)
                            <span class="{{ $differenceClass }}">
                                {{ number_format((float) $difference, 2) }} €
                            </span>
                        @else
                            —
                        @endif
                    </td>

                    <td>
                        @if($percentDifference !== null)
                            <span class="{{ $percentClass }}">
                                {{ number_format((float) $percentDifference, 2) }}%
                            </span>
                        @else
                            —
                        @endif
                    </td>

                    <td>{{ $history->position ?? '—' }}</td>

                    <td>
                        <span class="{{ $badgeClass }}">{{ $status ?? '—' }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center;">No price history records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top: 20px;">
    {{ $histories->withQueryString()->links() }}
</div>

<script>
    let priceHistorySearchTimeout = null;

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('priceHistorySearchInput');
        const searchForm = document.getElementById('priceHistorySearchForm');

        if (searchInput && searchForm) {
            searchInput.addEventListener('input', function () {
                clearTimeout(priceHistorySearchTimeout);

                priceHistorySearchTimeout = setTimeout(() => {
                    searchForm.submit();
                }, 500);
            });
        }
    });
</script>

@endsection