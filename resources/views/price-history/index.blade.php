@extends('layouts.app')

@section('content')

<div class="price-history-page">

    <style>
        /* центриране */
        .price-history-page td:nth-child(8),
        .price-history-page td:nth-child(9) {
            text-align: center;
        }

        /* всички badge-ове (като diff) */
        .price-history-page .badge-green,
        .price-history-page .badge-red,
        .price-history-page .badge-yellow,
        .price-history-page .badge-gray,
        .price-history-page .badge-blue {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 30px;
            padding: 0 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
            box-sizing: border-box;
        }

        /* 🔥 FIX САМО ЗА STATUS */
        .price-history-page td:nth-child(9) .badge-green,
        .price-history-page td:nth-child(9) .badge-red,
        .price-history-page td:nth-child(9) .badge-yellow,
        .price-history-page td:nth-child(9) .badge-gray {
            width: 110px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>

    <div class="cmp-page-head cmp-page-head-modern">
        <div>
            <h1 class="cmp-title">Price History</h1>
            <p class="cmp-subtitle">Track all saved competitor price checks over time.</p>
        </div>
    </div>

    <div class="cmp-toolbar-shell">
        <form method="GET" action="{{ route('price-history.index') }}" class="cmp-toolbar-form">

            <div class="cmp-toolbar-main" style="gap:10px; flex-wrap:wrap;">

                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Search..."
                    class="cmp-toolbar-input"
                    style="max-width:220px;"
                >

                <select name="product_id" class="cmp-toolbar-input" style="max-width:220px;">
                    <option value="">All Products</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" {{ (string)$productId === (string)$p->id ? 'selected' : '' }}>
                            {{ $p->name }}
                        </option>
                    @endforeach
                </select>

                <select name="store_id" class="cmp-toolbar-input" style="max-width:160px;">
                    <option value="">All Stores</option>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" {{ (string)$storeId === (string)$s->id ? 'selected' : '' }}>
                            {{ $s->name }}
                        </option>
                    @endforeach
                </select>

                <select name="status" class="cmp-toolbar-input" style="max-width:170px;">
                    <option value="">All Status</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st }}" {{ (string)$status === (string)$st ? 'selected' : '' }}>
                            {{ $st }}
                        </option>
                    @endforeach
                </select>

                <input type="date" name="date_from" value="{{ $dateFrom }}" class="cmp-toolbar-input">
                <input type="date" name="date_to" value="{{ $dateTo }}" class="cmp-toolbar-input">

            </div>

            <div class="cmp-toolbar-side" style="display:flex; gap:10px;">
                <button type="submit" class="btn">Filter</button>
                <a href="{{ route('price-history.index') }}" class="btn">Reset</a>
            </div>

        </form>
    </div>

    <div class="cmp-table-wrap">
        <table class="cmp-table" style="font-size:13px;">
            <thead>
                <tr>
                    <th style="width:140px;">Date</th>
                    <th>Product</th>
                    <th style="width:120px;">Store</th>
                    <th style="width:110px;">Our</th>
                    <th style="width:110px;">Comp</th>
                    <th style="width:110px;">Diff</th>
                    <th style="width:90px;">%</th>
                    <th style="width:130px;">Position</th>
                    <th style="width:120px;">Status</th>
                </tr>
            </thead>

            <tbody>
            @forelse($histories as $h)
                @php
                    $diff = $h->difference;
                    $percent = $h->percent_difference;

                    $statusBadge = 'badge-gray';
                    if ($h->status === 'Cheaper') {
                        $statusBadge = 'badge-green';
                    } elseif ($h->status === 'More Expensive') {
                        $statusBadge = 'badge-red';
                    } elseif ($h->status === 'Match') {
                        $statusBadge = 'badge-yellow';
                    }
                @endphp

                <tr>
                    <td>{{ optional($h->checked_at)->format('d.m H:i') ?: '—' }}</td>

                    <td style="max-width:260px;">
                        {{ $h->product->name ?? '—' }}
                    </td>

                    <td>{{ $h->store->name ?? '—' }}</td>

                    <td class="cmp-price">
                        {{ $h->our_price !== null ? number_format((float)$h->our_price, 2, '.', '') . ' €' : '—' }}
                    </td>

                    <td class="cmp-price">
                        {{ $h->competitor_price !== null ? number_format((float)$h->competitor_price, 2, '.', '') . ' €' : '—' }}
                    </td>

                    {{-- DIFF --}}
                    <td>
                        @if($diff !== null)
                            <span class="{{ (float)$diff < 0 ? 'badge-green' : ((float)$diff > 0 ? 'badge-red' : 'badge-gray') }}">
                                {{ (float)$diff > 0 ? '+' : '' }}{{ number_format((float)$diff, 2, '.', '') }} €
                            </span>
                        @else
                            —
                        @endif
                    </td>

                    {{-- % --}}
                    <td>
                        @if($percent !== null)
                            <span class="{{ (float)$percent < 0 ? 'badge-green' : ((float)$percent > 0 ? 'badge-red' : 'badge-gray') }}">
                                {{ (float)$percent > 0 ? '+' : '' }}{{ number_format((float)$percent, 2, '.', '') }}%
                            </span>
                        @else
                            —
                        @endif
                    </td>

                    {{-- POSITION --}}
                    <td>
                        @if($h->position === '#1 Best Price')
                            <span class="badge-green">#1 Best</span>
                        @elseif($h->position === 'Not Best Price')
                            <span class="badge-red">Not Best</span>
                        @elseif($h->position === 'Same Price')
                            <span class="badge-yellow">Match</span>
                        @else
                            <span class="badge-gray">{{ $h->position ?? '—' }}</span>
                        @endif
                    </td>

                    {{-- STATUS --}}
                    <td>
                        <span class="{{ $statusBadge }}">
                            {{ $h->status ?? '—' }}
                        </span>
                    </td>
                </tr>

            @empty
                <tr>
                    <td colspan="9" style="text-align:center;">No data</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:20px;">
        {{ $histories->links() }}
    </div>

</div>

@endsection