@extends('layouts.app')

@section('content')

<div class="price-history-page">

    <style>
        .price-history-page td:nth-child(8),
        .price-history-page td:nth-child(9) { text-align: center; }

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

        .price-history-page td:nth-child(9) .badge-green,
        .price-history-page td:nth-child(9) .badge-red,
        .price-history-page td:nth-child(9) .badge-yellow,
        .price-history-page td:nth-child(9) .badge-gray {
            width: 110px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Product dropdown */
        .ph-wrap { position: relative; }
        .ph-trigger { }
        .ph-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            min-width: 340px;
            z-index: 999;
            background: #fff;
            border: 1px solid #dbe3ef;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.10);
        }
        .ph-dropdown.open { display: block; }
        .ph-search-wrap { padding: 8px; border-bottom: 1px solid #f1f5f9; }
        .ph-search {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #dbe3ef;
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 13px;
            outline: none;
            margin: 0;
        }
        .ph-list { max-height: 260px; overflow-y: auto; }
        .ph-opt {
            padding: 8px 12px;
            cursor: pointer;
            font-size: 13px;
            color: #374151;
        }
        .ph-opt:hover { background: #eff6ff; }
        .ph-opt.active { background: #dbeafe; font-weight: 600; }

        /* Force single row toolbar */
        .price-history-page .cmp-toolbar-form {
            display: flex !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
            gap: 10px !important;
        }
        .price-history-page .cmp-toolbar-main {
            display: flex !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
            gap: 10px !important;
            flex: 1 !important;
            min-width: 0 !important;
            overflow-x: auto !important;
        }
        .price-history-page .cmp-toolbar-side {
            flex-shrink: 0 !important;
        }
        .price-history-page .cmp-toolbar-input {
            flex-shrink: 0 !important;
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

            <div class="cmp-toolbar-main" style="display:flex !important; flex-wrap:nowrap !important; gap:10px; align-items:center; overflow-x:auto;">

                {{-- Search + Product dropdown combined --}}
                <div class="ph-wrap">
                    <input type="hidden" name="product_id" id="phId" value="{{ $productId }}">
                    <input
                        type="text"
                        id="phSearch"
                        name="search"
                        value="{{ $productId ? ($products->firstWhere('id', $productId)?->name ?? $search) : $search }}"
                        placeholder="Search product..."
                        class="cmp-toolbar-input ph-trigger"
                        autocomplete="off"
                        style="max-width:220px;"
                    >
                    <div class="ph-dropdown" id="phDropdown">
                        <div class="ph-list">
                            <div class="ph-opt {{ !$productId ? 'active' : '' }}"
                                data-value="" data-label="" data-search="all products">
                                — All Products —
                            </div>
                            @foreach($products as $p)
                                <div class="ph-opt {{ (string)$productId === (string)$p->id ? 'active' : '' }}"
                                    data-value="{{ $p->id }}"
                                    data-label="{{ $p->name }}"
                                    data-search="{{ strtolower($p->name) }} {{ strtolower($p->sku ?? '') }}">
                                    {{ $p->name }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <select name="store_id" class="cmp-toolbar-input" style="max-width:130px;">
                    <option value="">All Stores</option>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" {{ (string)$storeId === (string)$s->id ? 'selected' : '' }}>
                            {{ $s->name }}
                        </option>
                    @endforeach
                </select>

                <select name="status" class="cmp-toolbar-input" style="max-width:130px;">
                    <option value="">All Status</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st }}" {{ (string)$status === (string)$st ? 'selected' : '' }}>
                            {{ $st }}
                        </option>
                    @endforeach
                </select>

                <input type="date" name="date_from" value="{{ $dateFrom }}" class="cmp-toolbar-input" style="min-width:130px; max-width:130px;">
                <input type="date" name="date_to"   value="{{ $dateTo }}"   class="cmp-toolbar-input" style="min-width:130px; max-width:130px;">

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
                    $diff        = $h->difference;
                    $percent     = $h->percent_difference;
                    $statusBadge = 'badge-gray';
                    if ($h->status === 'Cheaper')            $statusBadge = 'badge-green';
                    elseif ($h->status === 'More Expensive') $statusBadge = 'badge-red';
                    elseif ($h->status === 'Match')          $statusBadge = 'badge-yellow';
                @endphp
                <tr>
                    <td>{{ optional($h->checked_at)->format('d.m H:i') ?: '—' }}</td>
                    <td style="max-width:260px;">{{ $h->product->name ?? '—' }}</td>
                    <td>{{ $h->store->name ?? '—' }}</td>
                    <td class="cmp-price">{{ $h->our_price !== null ? number_format((float)$h->our_price, 2, '.', '') . ' €' : '—' }}</td>
                    <td class="cmp-price">{{ $h->competitor_price !== null ? number_format((float)$h->competitor_price, 2, '.', '') . ' €' : '—' }}</td>
                    <td>
                        @if($diff !== null)
                            <span class="{{ (float)$diff < 0 ? 'badge-green' : ((float)$diff > 0 ? 'badge-red' : 'badge-gray') }}">
                                {{ (float)$diff > 0 ? '+' : '' }}{{ number_format((float)$diff, 2, '.', '') }} €
                            </span>
                        @else —
                        @endif
                    </td>
                    <td>
                        @if($percent !== null)
                            <span class="{{ (float)$percent < 0 ? 'badge-green' : ((float)$percent > 0 ? 'badge-red' : 'badge-gray') }}">
                                {{ (float)$percent > 0 ? '+' : '' }}{{ number_format((float)$percent, 2, '.', '') }}%
                            </span>
                        @else —
                        @endif
                    </td>
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
                    <td><span class="{{ $statusBadge }}">{{ $h->status ?? '—' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;">No data</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:20px;">{{ $histories->links() }}</div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const trigger  = document.getElementById('phSearch');
    const dropdown = document.getElementById('phDropdown');
    const hiddenId = document.getElementById('phId');
    const opts     = Array.from(document.querySelectorAll('.ph-opt'));

    // Search input IS the trigger
    trigger.addEventListener('focus', () => {
        dropdown.classList.add('open');
    });

    trigger.addEventListener('input', () => {
        const q = trigger.value.toLowerCase();
        dropdown.classList.add('open');
        hiddenId.value = ''; // clear product selection when typing freely
        opts.forEach(o => {
            o.style.display = !q || (o.dataset.search || '').includes(q) ? '' : 'none';
        });
    });

    opts.forEach(o => {
        o.addEventListener('mousedown', (e) => {
            e.preventDefault(); // prevent blur before click
            hiddenId.value = o.dataset.value;
            trigger.value  = o.dataset.label; // fill input with product name
            opts.forEach(x => x.classList.remove('active'));
            o.classList.add('active');
            dropdown.classList.remove('open');
            opts.forEach(x => x.style.display = '');
        });
    });

    trigger.addEventListener('blur', () => {
        setTimeout(() => dropdown.classList.remove('open'), 150);
    });

    document.addEventListener('click', (e) => {
        if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('open');
        }
    });
});
</script>

@endsection