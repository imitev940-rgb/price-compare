@extends('layouts.app')

@section('content')

@if(session('success'))
    <div class="alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="cmp-page-head">
    <div>
        <h1 class="cmp-title">Price History</h1>
        <p class="cmp-subtitle">Track all saved competitor price checks over time.</p>
    </div>
</div>

<form method="GET" action="{{ route('price-history.index') }}" style="margin-bottom: 20px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
    <select name="product_id" style="max-width: 320px;">
        <option value="">All products</option>
        @foreach($products as $product)
            <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                {{ $product->name }}
            </option>
        @endforeach
    </select>

    <button type="submit" class="btn">Filter</button>

    @if(request('product_id'))
        <a href="{{ route('price-history.index') }}" class="btn">Clear</a>
    @endif
</form>

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

@endsection