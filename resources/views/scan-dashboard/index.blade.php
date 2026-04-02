@extends('layouts.app')

@section('content')

<div class="cmp-page-head cmp-page-head-modern">
    <div>
        <h1 class="cmp-title">Scan Dashboard</h1>
        <p class="cmp-subtitle">Следене на due links, top/normal товари и сканиране.</p>
    </div>

    <div class="cmp-page-head-actions">
        <a href="{{ route('comparison') }}" class="btn">Back</a>
    </div>
</div>

<div class="cmp-cards-grid" style="margin-bottom:20px;">
    <div class="cmp-stat-card">
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Active Links</div>
            <div class="cmp-stat-value">{{ $activeLinks }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Due Now</div>
            <div class="cmp-stat-value">{{ $dueCount }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Due Top</div>
            <div class="cmp-stat-value">{{ $dueTop }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Due Normal</div>
            <div class="cmp-stat-value">{{ $dueNormal }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Checked Today</div>
            <div class="cmp-stat-value">{{ $checkedToday }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Blocked</div>
            <div class="cmp-stat-value">{{ $blockedCount }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Errors</div>
            <div class="cmp-stat-value">{{ $errorCount }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Last Checked</div>
            <div class="cmp-stat-value" style="font-size:16px;">
                {{ $latestCheckedAt ? $latestCheckedAt->format('d.m.Y H:i') : '—' }}
            </div>
        </div>
    </div>
</div>

<style>
    .cmp-search-wrap {
        display: flex;
        justify-content: center;
        margin-bottom: 18px;
        overflow-x: auto;
    }

    .cmp-search-wrap form {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        flex-wrap: nowrap;
    }

    .cmp-search-wrap input {
        width: 420px;
        height: 52px;
        padding: 0 18px;
        border-radius: 16px;
        border: 1px solid #dbe2ea;
        background: #f8fafc;
        font-size: 15px;
        outline: none;
    }

    .cmp-search-wrap select {
        height: 52px;
        padding: 0 14px;
        border-radius: 16px;
        border: 1px solid #dbe2ea;
        background: #f8fafc;
        font-size: 15px;
        outline: none;
        min-width: 140px;
    }

    .cmp-search-wrap input:focus,
    .cmp-search-wrap select:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 2px rgba(37,99,235,0.12);
    }

    .cmp-pagination-wrap {
        margin-top: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .cmp-pagination-info {
        color: #64748b;
        font-size: 14px;
    }

    @media (max-width: 768px) {
        .cmp-search-wrap {
            justify-content: flex-start;
        }

        .cmp-search-wrap input {
            width: 320px;
        }

        .cmp-search-wrap select {
            min-width: 120px;
        }
    }
</style>

<h2 style="margin-bottom:10px;">Due Links</h2>

<div class="cmp-search-wrap">
    <form method="GET" action="{{ url()->current() }}" id="searchForm">
        <input
            type="text"
            name="q"
            id="searchInput"
            value="{{ request('q') }}"
            placeholder="Търси по продукт..."
            autocomplete="off"
        >

        <select name="priority" id="prioritySelect">
            <option value="">All</option>
            <option value="top" {{ request('priority') === 'top' ? 'selected' : '' }}>Top</option>
            <option value="normal" {{ request('priority') === 'normal' ? 'selected' : '' }}>Normal</option>
        </select>
    </form>
</div>

<div class="cmp-table-wrap">
    <table class="cmp-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Priority</th>
                <th>Store</th>
                <th>Last Checked</th>
                <th>Last Price</th>
                <th>Status</th>
                <th>Auto</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dueTable as $link)
                <tr>
                    <td>{{ $link->product->name ?? '—' }}</td>

                    <td>
                        @if(($link->product->scan_priority ?? 'normal') === 'top')
                            <span class="badge-red">Top</span>
                        @else
                            <span class="badge-blue">Normal</span>
                        @endif
                    </td>

                    <td>{{ $link->store->name ?? '—' }}</td>

                    <td>
                        {{ $link->last_checked_at ? $link->last_checked_at->format('d.m.Y H:i') : 'Never' }}
                    </td>

                    <td>
                        {{ $link->last_price !== null ? number_format((float) $link->last_price, 2, '.', '') . ' €' : '—' }}
                    </td>

                    <td>
                        @php $status = $link->search_status; @endphp

                        @if($status === 'found')
                            <span class="badge-green">Found</span>
                        @elseif($status === 'pending_parser')
                            <span class="badge-yellow">Pending</span>
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
                        @elseif($status === 'mismatch')
                            <span class="badge-red">Mismatch</span>
                        @else
                            <span class="badge-gray">{{ $status ?: '—' }}</span>
                        @endif
                    </td>

                    <td>
                        @if($link->is_auto_found)
                            <span class="badge-blue">Yes</span>
                        @else
                            <span class="badge-gray">No</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center;">Няма due links.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="cmp-pagination-wrap">
    <div class="cmp-pagination-info">
        Showing {{ $dueTable->firstItem() ?? 0 }} - {{ $dueTable->lastItem() ?? 0 }} of {{ $dueTable->total() }} results
    </div>

    <div>
        {{ $dueTable->onEachSide(1)->links() }}
    </div>
</div>

<script>
    let searchTimer;

    const searchForm = document.getElementById('searchForm');
    const searchInput = document.getElementById('searchInput');
    const prioritySelect = document.getElementById('prioritySelect');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimer);

            searchTimer = setTimeout(() => {
                searchForm.submit();
            }, 500);
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimer);
                searchForm.submit();
            }
        });
    }

    if (prioritySelect) {
        prioritySelect.addEventListener('change', function () {
            searchForm.submit();
        });
    }
</script>

@endsection