@extends('layouts.app')

@section('content')

<div class="cmp-page-head cmp-page-head-modern">
    <div>
        <h1 class="cmp-title">{{ __('messages.price_comparison') }}</h1>
        <p class="cmp-subtitle">{{ __('messages.market_overview') }}</p>
    </div>
</div>

<div class="cmp-cards-grid">
    <div class="cmp-stat-card">
        <div class="cmp-stat-icon cmp-stat-icon-blue">
            <i data-lucide="package"></i>
        </div>
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">{{ __('messages.products') }}</div>
            <div class="cmp-stat-value">{{ $productsCount }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-icon cmp-stat-icon-indigo">
            <i data-lucide="store"></i>
        </div>
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">{{ __('messages.stores') }}</div>
            <div class="cmp-stat-value">{{ $storesCount }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-icon cmp-stat-icon-red">
           <i data-lucide="alert-triangle"></i>
        </div>
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Not #1 in Pazaruvaj</div>
            <div class="cmp-stat-value">{{ $notBestPriceCount }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-icon cmp-stat-icon-green">
            <i data-lucide="trophy"></i>
        </div>
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Best Price Wins</div>
            <div class="cmp-stat-value">{{ $bestPriceWins }}</div>
        </div>
    </div>
</div>

<div class="cmp-toolbar-shell">
    <form method="GET" class="cmp-toolbar-form">
        <div class="cmp-toolbar-main">
            <div class="cmp-toolbar-field cmp-toolbar-field-search cmp-toolbar-field-search-compact">
                <label class="cmp-toolbar-label">Search</label>
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Търси по продукт..."
                    class="cmp-toolbar-input"
                >
            </div>

            <div class="cmp-toolbar-field">
                <label for="sort" class="cmp-toolbar-label">Sort by</label>
                <select id="sort" name="sort" class="cmp-toolbar-select" onchange="this.form.submit()">
                    <option value="">Default</option>
                    <option value="name_asc" {{ request('sort') === 'name_asc' ? 'selected' : '' }}>Name A → Z</option>
                    <option value="name_desc" {{ request('sort') === 'name_desc' ? 'selected' : '' }}>Name Z → A</option>
                    <option value="price_asc" {{ request('sort') === 'price_asc' ? 'selected' : '' }}>Our Price Low → High</option>
                    <option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>Our Price High → Low</option>
                    <option value="lowest_asc" {{ request('sort') === 'lowest_asc' ? 'selected' : '' }}>Lowest Price Low → High</option>
                    <option value="lowest_desc" {{ request('sort') === 'lowest_desc' ? 'selected' : '' }}>Lowest Price High → Low</option>
                    <option value="market_asc" {{ request('sort') === 'market_asc' ? 'selected' : '' }}>Market Price Low → High</option>
                    <option value="market_desc" {{ request('sort') === 'market_desc' ? 'selected' : '' }}>Market Price High → Low</option>
                    <option value="diff_percent_asc" {{ request('sort') === 'diff_percent_asc' ? 'selected' : '' }}>Diff % Low → High</option>
                    <option value="diff_percent_desc" {{ request('sort') === 'diff_percent_desc' ? 'selected' : '' }}>Diff % High → Low</option>
                    <option value="offers_asc" {{ request('sort') === 'offers_asc' ? 'selected' : '' }}>Offers Count Low → High</option>
                    <option value="offers_desc" {{ request('sort') === 'offers_desc' ? 'selected' : '' }}>Offers Count High → Low</option>
                    <option value="top_offer_asc" {{ request('sort') === 'top_offer_asc' ? 'selected' : '' }}>Top Offer Position 1 → 3+</option>
                    <option value="top_offer_desc" {{ request('sort') === 'top_offer_desc' ? 'selected' : '' }}>Top Offer Position 3+ → 1</option>
                </select>
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

            <div class="cmp-toolbar-field cmp-toolbar-field-small">
                <label for="status" class="cmp-toolbar-label">Status</label>
                <select id="status" name="status" class="cmp-toolbar-select" onchange="this.form.submit()">
                    <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>All</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active only</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive only</option>
                </select>
            </div>
        </div>

        <div class="cmp-toolbar-side">
            <a href="{{ route('comparison.export.csv', request()->query()) }}"
               class="cmp-toolbar-icon"
               title="Export CSV"
               aria-label="Export CSV">
                <i data-lucide="file-text"></i>
            </a>

            <a href="{{ route('comparison.export.excel', request()->query()) }}"
               class="cmp-toolbar-icon"
               title="Export Excel"
               aria-label="Export Excel">
                <i data-lucide="sheet"></i>
            </a>

            <a href="{{ route('comparison.export.pdf', request()->query()) }}"
               class="cmp-toolbar-icon"
               title="Export PDF"
               aria-label="Export PDF">
                <i data-lucide="file-output"></i>
            </a>

            <div class="cmp-columns-wrap">
                <button type="button" class="cmp-columns-btn cmp-columns-btn-premium" onclick="toggleColumnsMenu()">
                    <i data-lucide="columns-3"></i>
                    <span>Колони</span>
                </button>

                <div id="columnsMenu" class="cmp-columns-menu">
                    <label><input type="checkbox" data-col="technopolis" checked> Technopolis</label>
                    <label><input type="checkbox" data-col="technomarket" checked> Technomarket</label>
                    <label><input type="checkbox" data-col="zora" checked> Zora</label>
                    <label><input type="checkbox" data-col="pazaruvaj" checked> Pazaruvaj</label>
                    <label><input type="checkbox" data-col="lowest" checked> Lowest</label>
                    <label><input type="checkbox" data-col="offers" checked> Offers</label>
                    <label><input type="checkbox" data-col="position" checked> Position</label>
                    <label><input type="checkbox" data-col="diff" checked> Diff €</label>
                    <label><input type="checkbox" data-col="diff_percent" checked> Diff %</label>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="cmp-table-wrap">
    <table class="cmp-table">
        <thead>
            <tr>
                <th class="col-product">Product</th>
                <th class="col-our_price">Our Price</th>
                <th class="col-technopolis">Techno<br>polis</th>
                <th class="col-technomarket">Techno<br>Market</th>
                <th class="col-zora">Zora</th>
                <th class="col-pazaruvaj">Pazaruvaj</th>
                <th class="col-lowest">Lowest</th>
                <th class="col-offers">Offers</th>
                <th class="col-position">Position</th>
                <th class="col-diff">Diff €</th>
                <th class="col-diff_percent">Diff %</th>
                <th class="col-toggle"></th>
            </tr>
        </thead>

        <tbody>
            @forelse($products as $product)
                <tr class="cmp-main-row">
                    <td class="col-product">
                        <a href="{{ route('products.show', $product) }}" class="cmp-product-link">
                            {{ $product->name }}
                        </a>
                    </td>

                    <td class="cmp-price col-our_price">
                        @if($product->our_price !== null)
                            {{ number_format((float) $product->our_price, 2) }}
                        @else
                            —
                        @endif
                    </td>

                    <td class="cmp-price col-technopolis">
                        @if($product->technopolis_price !== null)
                            {{ number_format((float) $product->technopolis_price, 2) }}
                        @else
                            —
                        @endif
                    </td>

                    <td class="cmp-price col-technomarket">
                        @if($product->technomarket_price !== null)
                            {{ number_format((float) $product->technomarket_price, 2) }}
                        @else
                            —
                        @endif
                    </td>

                    <td class="cmp-price col-zora">
                        @if($product->zora_price !== null)
                            {{ number_format((float) $product->zora_price, 2) }}
                        @else
                            —
                        @endif
                    </td>

                    <td class="cmp-price col-pazaruvaj">
                        @if($product->pazaruvaj_lowest_price !== null)
                            {{ number_format((float) $product->pazaruvaj_lowest_price, 2) }}
                        @else
                            —
                        @endif
                    </td>

                    <td class="cmp-price col-lowest">
                        @if($product->lowest_market_price !== null)
                            <span class="cmp-lowest-price-text">
                                {{ number_format((float) $product->lowest_market_price, 2) }}
                            </span>
                        @else
                            —
                        @endif
                    </td>

                    <td class="col-offers">
                        <span class="cmp-offers-count-pill">
                            {{ $product->offers_count ?? 0 }}
                        </span>
                    </td>

                    <td class="col-position">
                        @if($product->pazaruvaj_our_position == 1)
                            <span class="badge-green">#1</span>
                        @elseif($product->pazaruvaj_our_position)
                            <span class="badge-gray">#{{ $product->pazaruvaj_our_position }}</span>
                        @else
                            —
                        @endif
                    </td>

                    <td class="col-diff">
                        @if($product->difference_amount !== null)
                            @php $d = (float) $product->difference_amount; @endphp
                            <span class="cmp-diff-chip {{ $d < 0 ? 'up' : ($d > 0 ? 'down' : 'flat') }}">
                                <span class="cmp-diff-arrow">{{ $d < 0 ? '↑' : ($d > 0 ? '↓' : '•') }}</span>
                                {{ number_format(abs($d), 2) }}
                            </span>
                        @else
                            —
                        @endif
                    </td>

                    <td class="col-diff_percent">
                        @if($product->difference_percent !== null)
                            @php $p = (float) $product->difference_percent; @endphp
                            <span class="cmp-diff-chip {{ $p < 0 ? 'up' : ($p > 0 ? 'down' : 'flat') }}">
                                <span class="cmp-diff-arrow">{{ $p < 0 ? '↑' : ($p > 0 ? '↓' : '•') }}</span>
                                {{ number_format(abs($p), 1) }}%
                            </span>
                        @else
                            —
                        @endif
                    </td>

                    <td class="col-toggle">
                        @if(($product->pazaruvaj_offers_list ?? collect())->count() > 0)
                            <button
                                type="button"
                                class="cmp-arrow-btn"
                                onclick="toggleOffers('offers-{{ $product->id }}', this)"
                                title="Show Pazaruvaj offers"
                            >
                                ▼
                            </button>
                        @endif
                    </td>
                </tr>

                @if(($product->pazaruvaj_offers_list ?? collect())->count() > 0)
                    <tr class="cmp-offers-row">
                        <td colspan="12">
                            <div id="offers-{{ $product->id }}" class="cmp-offers-panel">
                                <div class="cmp-offers-headline">
                                    <div class="cmp-offers-title">
                                        Pazaruvaj offers for {{ $product->name }}
                                    </div>

                                    @if($product->pazaruvaj_lowest_price !== null)
                                        <div class="cmp-offers-summary">
                                            Lowest:
                                            <strong>{{ number_format((float) $product->pazaruvaj_lowest_price, 2) }} €</strong>
                                            · Offers:
                                            <strong>{{ $product->pazaruvaj_offers_count ?? 0 }}</strong>
                                        </div>
                                    @endif
                                </div>

                                <div class="cmp-offers-grid">
                                    @foreach($product->pazaruvaj_offers_list as $offer)
                                        @php
                                            $cardClass = '';
                                            $rankClass = '';
                                            $storeName = mb_strtolower(trim((string) ($offer->store_name ?? '')));
                                            $normalizedStoreName = str_replace([' ', '-', '_'], '', $storeName);
                                            $isOurStore = str_contains($normalizedStoreName, 'technika')
                                                || str_contains($normalizedStoreName, 'техника');

                                            if ((int) $offer->position === 1) {
                                                $cardClass = 'top-1';
                                                $rankClass = 'top-1';
                                            } elseif ((int) $offer->position === 2) {
                                                $cardClass = 'top-2';
                                                $rankClass = 'top-2';
                                            } elseif ((int) $offer->position === 3) {
                                                $cardClass = 'top-3';
                                                $rankClass = 'top-3';
                                            }

                                            if ($isOurStore) {
                                                if ((int) $offer->position === 1) {
                                                    $cardClass .= ' our-top';
                                                } else {
                                                    $cardClass .= ' our-not-top';
                                                }
                                            }
                                        @endphp

                                        <div class="cmp-offer-card{{ $cardClass ? ' ' . trim($cardClass) : '' }}">
                                            <div class="cmp-offer-left">
                                                <span class="cmp-offer-rank {{ $rankClass }}">
                                                    #{{ $offer->position }}
                                                </span>

                                                <div class="cmp-offer-info">
                                                    <div class="cmp-offer-store">
                                                        {{ $offer->store_name }}
                                                    </div>

                                                    <div class="cmp-offer-meta">
                                                        @if($isOurStore && (int) $offer->position === 1)
                                                            Technika.bg is #1
                                                        @elseif($isOurStore)
                                                            Technika.bg offer
                                                        @elseif((int) $offer->position <= 3)
                                                            Top {{ $offer->position }} offer
                                                        @else
                                                            Market offer
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="cmp-offer-right">
                                                @if($offer->is_lowest)
                                                    <span class="cmp-lowest-pill">Lowest</span>
                                                @endif

                                                <span class="cmp-offer-price">
                                                    {{ number_format((float) $offer->price, 2) }} €
                                                </span>

                                                @if($offer->offer_url)
                                                    <a href="{{ $offer->offer_url }}"
                                                       target="_blank"
                                                       class="icon-btn cmp-offer-open-icon"
                                                       title="Open offer"
                                                       aria-label="Open offer">
                                                        <i data-lucide="external-link"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr>
                    <td colspan="12" style="text-align:center;">No products found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:20px;">
    {{ $products->links() }}
</div>

<script>
    let searchTimeout = null;

    function toggleOffers(id, btn) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.toggle('open');
        if (btn) btn.classList.toggle('active');
    }

    function toggleColumnsMenu() {
        const menu = document.getElementById('columnsMenu');
        if (menu) menu.classList.toggle('open');
    }

    function toggleColumn(col, show) {
        document.querySelectorAll('.col-' + col).forEach(el => {
            el.style.display = show ? '' : 'none';
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('#columnsMenu input').forEach(cb => {
            const col = cb.dataset.col;
            const saved = localStorage.getItem('col-' + col);

            if (saved === 'false') {
                cb.checked = false;
                toggleColumn(col, false);
            }

            cb.addEventListener('change', function () {
                toggleColumn(col, this.checked);
                localStorage.setItem('col-' + col, this.checked);
            });
        });

        document.addEventListener('click', function (e) {
            const menu = document.getElementById('columnsMenu');
            const wrap = document.querySelector('.cmp-columns-wrap');

            if (menu && wrap && !wrap.contains(e.target)) {
                menu.classList.remove('open');
            }
        });

        const searchInput = document.querySelector('input[name="search"]');

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimeout);

                searchTimeout = setTimeout(() => {
                    const form = this.closest('form');
                    if (form) {
                        form.submit();
                    }
                }, 500);
            });
        }
    });
</script>

@endsection