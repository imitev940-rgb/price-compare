@extends('layouts.app')

@section('content')
<div class="comparison-page-only">

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

    <a href="{{ route('comparison', array_merge(request()->query(), ['pazaruvaj_rank' => 'not_top'])) }}"
       class="cmp-stat-card cmp-stat-card-link">
        <div class="cmp-stat-icon cmp-stat-icon-red">
            <i data-lucide="alert-triangle"></i>
        </div>
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Not #1 in Pazaruvaj</div>
            <div class="cmp-stat-value">{{ $notBestPriceCount }}</div>
        </div>
    </a>

    <a href="{{ route('comparison', array_merge(request()->query(), ['pazaruvaj_rank' => 'top'])) }}"
       class="cmp-stat-card cmp-stat-card-link">
        <div class="cmp-stat-icon cmp-stat-icon-green">
            <i data-lucide="trophy"></i>
        </div>
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Best Price Wins</div>
            <div class="cmp-stat-value">{{ $bestPriceWins }}</div>
        </div>
    </a>
</div>

<div class="cmp-toolbar-shell">
    <form method="GET" class="cmp-toolbar-form">
        @if(request('pazaruvaj_rank'))
            <input type="hidden" name="pazaruvaj_rank" value="{{ request('pazaruvaj_rank') }}">
        @endif

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
                    <option value="" {{ request('sort') === null || request('sort') === '' ? 'selected' : '' }}>Default</option>

                    <option value="name_asc" {{ request('sort') === 'name_asc' ? 'selected' : '' }}>Name A → Z</option>
                    <option value="name_desc" {{ request('sort') === 'name_desc' ? 'selected' : '' }}>Name Z → A</option>

                    <option value="price_asc" {{ request('sort') === 'price_asc' ? 'selected' : '' }}>Our Price Low → High</option>
                    <option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>Our Price High → Low</option>

                    <option value="lowest_asc" {{ request('sort') === 'lowest_asc' ? 'selected' : '' }}>Pazaruvaj Low → High</option>
                    <option value="lowest_desc" {{ request('sort') === 'lowest_desc' ? 'selected' : '' }}>Pazaruvaj High → Low</option>

                    <option value="market_asc" {{ request('sort') === 'market_asc' ? 'selected' : '' }}>Market Lowest Low → High</option>
                    <option value="market_desc" {{ request('sort') === 'market_desc' ? 'selected' : '' }}>Market Lowest High → Low</option>

                    <option value="offers_asc" {{ request('sort') === 'offers_asc' ? 'selected' : '' }}>Offers Count Low → High</option>
                    <option value="offers_desc" {{ request('sort') === 'offers_desc' ? 'selected' : '' }}>Offers Count High → Low</option>

                    <option value="position_asc" {{ request('sort') === 'position_asc' ? 'selected' : '' }}>Position 1 → 3+</option>
                    <option value="position_desc" {{ request('sort') === 'position_desc' ? 'selected' : '' }}>Position 3+ → 1</option>

                    <option value="diff_asc" {{ request('sort') === 'diff_asc' ? 'selected' : '' }}>Pazaruvaj Diff € Low → High</option>
                    <option value="diff_desc" {{ request('sort') === 'diff_desc' ? 'selected' : '' }}>Pazaruvaj Diff € High → Low</option>

                    <option value="diff_percent_asc" {{ request('sort') === 'diff_percent_asc' ? 'selected' : '' }}>Pazaruvaj Diff % Low → High</option>
                    <option value="diff_percent_desc" {{ request('sort') === 'diff_percent_desc' ? 'selected' : '' }}>Pazaruvaj Diff % High → Low</option>

                    <option value="tp_diff_asc" {{ request('sort') === 'tp_diff_asc' ? 'selected' : '' }}>TP Diff € Low → High</option>
                    <option value="tp_diff_desc" {{ request('sort') === 'tp_diff_desc' ? 'selected' : '' }}>TP Diff € High → Low</option>

                    <option value="tp_diff_percent_asc" {{ request('sort') === 'tp_diff_percent_asc' ? 'selected' : '' }}>TP Diff % Low → High</option>
                    <option value="tp_diff_percent_desc" {{ request('sort') === 'tp_diff_percent_desc' ? 'selected' : '' }}>TP Diff % High → Low</option>

                    <option value="tm_diff_asc" {{ request('sort') === 'tm_diff_asc' ? 'selected' : '' }}>TM Diff € Low → High</option>
                    <option value="tm_diff_desc" {{ request('sort') === 'tm_diff_desc' ? 'selected' : '' }}>TM Diff € High → Low</option>

                    <option value="tm_diff_percent_asc" {{ request('sort') === 'tm_diff_percent_asc' ? 'selected' : '' }}>TM Diff % Low → High</option>
                    <option value="tm_diff_percent_desc" {{ request('sort') === 'tm_diff_percent_desc' ? 'selected' : '' }}>TM Diff % High → Low</option>

                    <option value="techmart_diff_asc" {{ request('sort') === 'techmart_diff_asc' ? 'selected' : '' }}>Techmart Diff € Low → High</option>
                    <option value="techmart_diff_desc" {{ request('sort') === 'techmart_diff_desc' ? 'selected' : '' }}>Techmart Diff € High → Low</option>

                    <option value="techmart_diff_percent_asc" {{ request('sort') === 'techmart_diff_percent_asc' ? 'selected' : '' }}>Techmart Diff % Low → High</option>
                    <option value="techmart_diff_percent_desc" {{ request('sort') === 'techmart_diff_percent_desc' ? 'selected' : '' }}>Techmart Diff % High → Low</option>

                    <option value="tehnomix_diff_asc" {{ request('sort') === 'tehnomix_diff_asc' ? 'selected' : '' }}>Tehnomix Diff € Low → High</option>
                    <option value="tehnomix_diff_desc" {{ request('sort') === 'tehnomix_diff_desc' ? 'selected' : '' }}>Tehnomix Diff € High → Low</option>

                    <option value="tehnomix_diff_percent_asc" {{ request('sort') === 'tehnomix_diff_percent_asc' ? 'selected' : '' }}>Tehnomix Diff % Low → High</option>
                    <option value="tehnomix_diff_percent_desc" {{ request('sort') === 'tehnomix_diff_percent_desc' ? 'selected' : '' }}>Tehnomix Diff % High → Low</option>

                    <option value="zora_diff_asc" {{ request('sort') === 'zora_diff_asc' ? 'selected' : '' }}>Zora Diff € Low → High</option>
                    <option value="zora_diff_desc" {{ request('sort') === 'zora_diff_desc' ? 'selected' : '' }}>Zora Diff € High → Low</option>

                    <option value="zora_diff_percent_asc" {{ request('sort') === 'zora_diff_percent_asc' ? 'selected' : '' }}>Zora Diff % Low → High</option>
                    <option value="zora_diff_percent_desc" {{ request('sort') === 'zora_diff_percent_desc' ? 'selected' : '' }}>Zora Diff % High → Low</option>
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

            <button type="button"
                    id="resetColumnWidthsBtn"
                    class="cmp-toolbar-icon cmp-toolbar-reset-icon"
                    title="Reset column widths"
                    aria-label="Reset column widths">
                <i data-lucide="rotate-ccw"></i>
            </button>

            @if(request('pazaruvaj_rank'))
                <a href="{{ route('comparison', request()->except('pazaruvaj_rank')) }}"
                   class="cmp-toolbar-icon"
                   title="Clear Pazaruvaj filter"
                   aria-label="Clear Pazaruvaj filter">
                    <i data-lucide="x"></i>
                </a>
            @endif

            <div class="cmp-columns-wrap">
                <button type="button" class="cmp-columns-btn cmp-columns-btn-premium" onclick="toggleColumnsMenu()">
                    <i data-lucide="columns-3"></i>
                    <span>Колони</span>
                </button>

                <div id="columnsMenu" class="cmp-columns-menu">
                    <label><input type="checkbox" data-col="technopolis" checked> Technopolis</label>
                    <label><input type="checkbox" data-col="technopolis_diff_euro"> TP Diff €</label>
                    <label><input type="checkbox" data-col="technopolis_diff_percent"> TP Diff %</label>

                    <label><input type="checkbox" data-col="technomarket" checked> Technomarket</label>
                    <label><input type="checkbox" data-col="technomarket_diff_euro"> TM Diff €</label>
                    <label><input type="checkbox" data-col="technomarket_diff_percent"> TM Diff %</label>

                    <label><input type="checkbox" data-col="techmart" checked> Techmart</label>
                    <label><input type="checkbox" data-col="techmart_diff_euro"> Techmart Diff €</label>
                    <label><input type="checkbox" data-col="techmart_diff_percent"> Techmart Diff %</label>

                    <label><input type="checkbox" data-col="tehnomix" checked> Tehnomix</label>
                    <label><input type="checkbox" data-col="tehnomix_diff_euro"> Tehnomix Diff €</label>
                    <label><input type="checkbox" data-col="tehnomix_diff_percent"> Tehnomix Diff %</label>

                    <label><input type="checkbox" data-col="zora" checked> Zora</label>
                    <label><input type="checkbox" data-col="zora_diff_euro"> Zora Diff €</label>
                    <label><input type="checkbox" data-col="zora_diff_percent"> Zora Diff %</label>

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

@if(request('pazaruvaj_rank') === 'not_top')
    <div class="cmp-filter-badge-wrap">
        <span class="cmp-filter-badge cmp-filter-badge-red">Showing only products where you are NOT #1 in Pazaruvaj</span>
    </div>
@endif

@if(request('pazaruvaj_rank') === 'top')
    <div class="cmp-filter-badge-wrap">
        <span class="cmp-filter-badge cmp-filter-badge-green">Showing only products where you ARE #1 in Pazaruvaj</span>
    </div>
@endif

<div class="cmp-table-wrap" id="comparisonTableWrap">
    <table class="cmp-table cmp-resizable-table" id="comparisonResizableTable">
        <thead>
            <tr>
                <th class="col-product">Product</th>

                <th class="col-our_price">Our Price</th>

                <th class="col-lowest">Lowest <span class="col-lowest-info" id="lowestInfoTh" title="Показва най-ниската цена и магазина">ℹ</span></th>

                <th class="col-technopolis">ТП</th>
                <th class="col-technopolis_diff_euro">TP Diff €</th>
                <th class="col-technopolis_diff_percent">TP Diff %</th>

                <th class="col-technomarket">ТМЕ</th>
                <th class="col-technomarket_diff_euro">TM Diff €</th>
                <th class="col-technomarket_diff_percent">TM Diff %</th>

                <th class="col-techmart">THM</th>
                <th class="col-techmart_diff_euro">Techmart Diff €</th>
                <th class="col-techmart_diff_percent">Techmart Diff %</th>

                <th class="col-tehnomix">ТМХ</th>
                <th class="col-tehnomix_diff_euro">Tehnomix Diff €</th>
                <th class="col-tehnomix_diff_percent">Tehnomix Diff %</th>

                <th class="col-zora">Zora</th>
                <th class="col-zora_diff_euro">Zora Diff €</th>
                <th class="col-zora_diff_percent">Zora Diff %</th>

                <th class="col-pazaruvaj">Pazaruvaj</th>
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

                    <td class="cmp-price col-lowest">
                        @if($product->lowest_market_price !== null)
                            <span class="cmp-lowest-price-text">{{ number_format((float) $product->lowest_market_price, 2) }}</span>
                            @if($product->pazaruvaj_lowest_store ?? null)
                                <span class="lowest-store-info" data-store="{{ $product->pazaruvaj_lowest_store ?? '' }}" onclick="showStoreTooltip(event, this)">ℹ</span>
                            @endif
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

                    <td class="col-technopolis_diff_euro">
                        @if($product->technopolis_diff_euro !== null)
                            @php $d = (float) $product->technopolis_diff_euro; @endphp
                            <span class="cmp-diff-chip {{ $d < 0 ? 'up' : ($d > 0 ? 'down' : 'flat') }}">
                                <span class="cmp-diff-arrow">{{ $d < 0 ? '↑' : ($d > 0 ? '↓' : '•') }}</span>
                                {{ number_format(abs($d), 2) }}
                            </span>
                        @else
                            —
                        @endif
                    </td>

                    <td class="col-technopolis_diff_percent">
                        @if($product->technopolis_diff_percent !== null)
                            @php $p = (float) $product->technopolis_diff_percent; @endphp
                            <span class="cmp-diff-chip {{ $p < 0 ? 'up' : ($p > 0 ? 'down' : 'flat') }}">
                                <span class="cmp-diff-arrow">{{ $p < 0 ? '↑' : ($p > 0 ? '↓' : '•') }}</span>
                                {{ number_format(abs($p), 1) }}%
                            </span>
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

                    <td class="col-technomarket_diff_euro">
                        @if($product->technomarket_diff_euro !== null)
                            @php $d = (float) $product->technomarket_diff_euro; @endphp
                            <span class="cmp-diff-chip {{ $d < 0 ? 'up' : ($d > 0 ? 'down' : 'flat') }}">
                                <span class="cmp-diff-arrow">{{ $d < 0 ? '↑' : ($d > 0 ? '↓' : '•') }}</span>
                                {{ number_format(abs($d), 2) }}
                            </span>
                        @else
                            —
                        @endif
                    </td>

                    <td class="col-technomarket_diff_percent">
                        @if($product->technomarket_diff_percent !== null)
                            @php $p = (float) $product->technomarket_diff_percent; @endphp
                            <span class="cmp-diff-chip {{ $p < 0 ? 'up' : ($p > 0 ? 'down' : 'flat') }}">
                                <span class="cmp-diff-arrow">{{ $p < 0 ? '↑' : ($p > 0 ? '↓' : '•') }}</span>
                                {{ number_format(abs($p), 1) }}%
                            </span>
                        @else
                            —
                        @endif
                    </td>

                    <td class="cmp-price col-techmart">
                        @if($product->techmart_price !== null)
                            {{ number_format((float) $product->techmart_price, 2) }}
                        @else
                            —
                        @endif
                    </td>

                    <td class="col-techmart_diff_euro">
                        @if($product->techmart_diff_euro !== null)
                            @php $d = (float) $product->techmart_diff_euro; @endphp
                            <span class="cmp-diff-chip {{ $d < 0 ? 'up' : ($d > 0 ? 'down' : 'flat') }}">
                                <span class="cmp-diff-arrow">{{ $d < 0 ? '↑' : ($d > 0 ? '↓' : '•') }}</span>
                                {{ number_format(abs($d), 2) }}
                            </span>
                        @else
                            —
                        @endif
                    </td>

                    <td class="col-techmart_diff_percent">
                        @if($product->techmart_diff_percent !== null)
                            @php $p = (float) $product->techmart_diff_percent; @endphp
                            <span class="cmp-diff-chip {{ $p < 0 ? 'up' : ($p > 0 ? 'down' : 'flat') }}">
                                <span class="cmp-diff-arrow">{{ $p < 0 ? '↑' : ($p > 0 ? '↓' : '•') }}</span>
                                {{ number_format(abs($p), 1) }}%
                            </span>
                        @else
                            —
                        @endif
                    </td>

                    <td class="cmp-price col-tehnomix">
                        @if($product->tehnomix_price !== null)
                            {{ number_format((float) $product->tehnomix_price, 2) }}
                        @else
                            —
                        @endif
                    </td>

                    <td class="col-tehnomix_diff_euro">
                        @if($product->tehnomix_diff_euro !== null)
                            @php $d = (float) $product->tehnomix_diff_euro; @endphp
                            <span class="cmp-diff-chip {{ $d < 0 ? 'up' : ($d > 0 ? 'down' : 'flat') }}">
                                <span class="cmp-diff-arrow">{{ $d < 0 ? '↑' : ($d > 0 ? '↓' : '•') }}</span>
                                {{ number_format(abs($d), 2) }}
                            </span>
                        @else
                            —
                        @endif
                    </td>

                    <td class="col-tehnomix_diff_percent">
                        @if($product->tehnomix_diff_percent !== null)
                            @php $p = (float) $product->tehnomix_diff_percent; @endphp
                            <span class="cmp-diff-chip {{ $p < 0 ? 'up' : ($p > 0 ? 'down' : 'flat') }}">
                                <span class="cmp-diff-arrow">{{ $p < 0 ? '↑' : ($p > 0 ? '↓' : '•') }}</span>
                                {{ number_format(abs($p), 1) }}%
                            </span>
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

                    <td class="col-zora_diff_euro">
                        @if($product->zora_diff_euro !== null)
                            @php $d = (float) $product->zora_diff_euro; @endphp
                            <span class="cmp-diff-chip {{ $d < 0 ? 'up' : ($d > 0 ? 'down' : 'flat') }}">
                                <span class="cmp-diff-arrow">{{ $d < 0 ? '↑' : ($d > 0 ? '↓' : '•') }}</span>
                                {{ number_format(abs($d), 2) }}
                            </span>
                        @else
                            —
                        @endif
                    </td>

                    <td class="col-zora_diff_percent">
                        @if($product->zora_diff_percent !== null)
                            @php $p = (float) $product->zora_diff_percent; @endphp
                            <span class="cmp-diff-chip {{ $p < 0 ? 'up' : ($p > 0 ? 'down' : 'flat') }}">
                                <span class="cmp-diff-arrow">{{ $p < 0 ? '↑' : ($p > 0 ? '↓' : '•') }}</span>
                                {{ number_format(abs($p), 1) }}%
                            </span>
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
                    </td><td class="col-offers">
                        <span class="cmp-offers-count-pill">
                            {{ $product->pazaruvaj_offers_count ?? 0 }}
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
                        <td colspan="24">
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
                    <td colspan="24" style="text-align:center;">No products found.</td>
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

    function setColumnVisibility(col, show) {
        document.querySelectorAll('.col-' + col).forEach(el => {
            el.classList.toggle('cmp-col-hidden', !show);
        });
    }

    const DIFF_COLS_HIDDEN_BY_DEFAULT = [
        'technopolis_diff_euro','technopolis_diff_percent',
        'technomarket_diff_euro','technomarket_diff_percent',
        'techmart_diff_euro','techmart_diff_percent',
        'tehnomix_diff_euro','tehnomix_diff_percent',
        'zora_diff_euro','zora_diff_percent',
    ];

    function reapplySavedColumnVisibility() {
        document.querySelectorAll('#columnsMenu input[data-col]').forEach(cb => {
            const col = cb.dataset.col;
            const saved = localStorage.getItem('col-' + col);

            if (saved !== null) {
                const show = saved !== 'false';
                cb.checked = show;
                setColumnVisibility(col, show);
            } else if (DIFF_COLS_HIDDEN_BY_DEFAULT.includes(col)) {
                cb.checked = false;
                setColumnVisibility(col, false);
            } else {
                cb.checked = true;
                setColumnVisibility(col, true);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        reapplySavedColumnVisibility();

        document.querySelectorAll('#columnsMenu input[data-col]').forEach(cb => {
            cb.addEventListener('change', function () {
                const col = this.dataset.col;
                setColumnVisibility(col, this.checked);
                localStorage.setItem('col-' + col, this.checked ? 'true' : 'false');
                requestAnimationFrame(() => syncStickyOffsets());
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
                    if (form) form.submit();
                }, 500);
            });
        }

        initResizableComparisonTable();
        initColumnDragDrop();
        syncStickyOffsets();

        window.addEventListener('resize', syncStickyOffsets);

        if (window.lucide) {
            lucide.createIcons();
        }
    });

    function syncStickyOffsets() {
        const table = document.getElementById('comparisonResizableTable');
        if (!table) return;

        const productHeader = table.querySelector('thead th.col-product');
        if (!productHeader) return;

        const productWidth = productHeader.offsetWidth;

        document.querySelectorAll('#comparisonResizableTable th.col-product, #comparisonResizableTable td.col-product').forEach(el => {
            el.style.left = '0px';
        });
    }

    function initResizableComparisonTable() {
        const table = document.getElementById('comparisonResizableTable');
        if (!table) return;

        const headers = table.querySelectorAll('thead th');
        if (!headers.length) return;

        const storageKey = 'comparison-resizable-widths-v4';

        headers.forEach((th, index) => {
            th.dataset.colIndex = index;

            if (th.classList.contains('col-toggle')) return;
            if (th.querySelector('.cmp-col-resize-handle')) return;

            const handle = document.createElement('span');
            handle.className = 'cmp-col-resize-handle';
            th.appendChild(handle);
        });

        function getAllRows() {
            return table.querySelectorAll('tr');
        }

        function getMinWidth(header) {
            if (header.classList.contains('col-product')) return 260;
            if (header.classList.contains('col-toggle')) return 52;
            if (
                header.classList.contains('col-technopolis_diff_euro') ||
                header.classList.contains('col-technopolis_diff_percent') ||
                header.classList.contains('col-technomarket_diff_euro') ||
                header.classList.contains('col-technomarket_diff_percent') ||
                header.classList.contains('col-techmart_diff_euro') ||
                header.classList.contains('col-techmart_diff_percent') ||
                header.classList.contains('col-tehnomix_diff_euro') ||
                header.classList.contains('col-tehnomix_diff_percent') ||
                header.classList.contains('col-zora_diff_euro') ||
                header.classList.contains('col-zora_diff_percent') ||
                header.classList.contains('col-diff') ||
                header.classList.contains('col-diff_percent')
            ) return 110;
            if (header.classList.contains('col-offers')) return 78;
            if (header.classList.contains('col-position')) return 90;
            return 110;
        }

        function setColumnWidth(index, width, skipSave = false) {
            const rows = getAllRows();
            const header = headers[index];
            if (!header) return;

            const finalWidth = Math.max(getMinWidth(header), width);

            rows.forEach(row => {
                const cell = row.children[index];
                if (cell) {
                    cell.style.width = finalWidth + 'px';
                    cell.style.minWidth = finalWidth + 'px';
                    cell.style.maxWidth = finalWidth + 'px';
                }
            });

            syncStickyOffsets();

            if (!skipSave) {
                saveWidths();
            }
        }

        function clearColumnWidths() {
            const rows = getAllRows();
            rows.forEach(row => {
                Array.from(row.children).forEach(cell => {
                    cell.style.width = '';
                    cell.style.minWidth = '';
                    cell.style.maxWidth = '';
                });
            });
            syncStickyOffsets();
        }

        function saveWidths() {
            const data = {};
            headers.forEach((th, index) => {
                if (th.classList.contains('cmp-col-hidden')) return;
                data[index] = th.offsetWidth;
            });
            localStorage.setItem(storageKey, JSON.stringify(data));
        }

        function loadWidths() {
            const saved = localStorage.getItem(storageKey);
            if (!saved) return;

            try {
                const data = JSON.parse(saved);
                Object.keys(data).forEach(index => {
                    const idx = parseInt(index);
                    if (!Number.isNaN(idx)) {
                        setColumnWidth(idx, parseInt(data[index]), true);
                    }
                });
            } catch (e) {
                console.warn('Failed to load column widths', e);
            }
        }

        let activeIndex = null;
        let startX = 0;
        let startWidth = 0;
        let activeHeader = null;

        function onMouseMove(e) {
            if (activeIndex === null) return;
            const diff = e.pageX - startX;
            setColumnWidth(activeIndex, startWidth + diff, true);
        }

        function onMouseUp() {
            if (activeHeader) {
                activeHeader.classList.remove('is-resizing');
            }

            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);

            saveWidths();
            syncStickyOffsets();

            activeIndex = null;
            activeHeader = null;
        }

        headers.forEach((th, index) => {
            const handle = th.querySelector('.cmp-col-resize-handle');
            if (!handle) return;

            handle.addEventListener('mousedown', function (e) {
                if (th.classList.contains('cmp-col-hidden')) return;

                e.preventDefault();
                e.stopPropagation();

                activeIndex = index;
                activeHeader = th;
                startX = e.pageX;
                startWidth = th.offsetWidth;

                th.classList.add('is-resizing');

                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            });
        });

        const resetBtn = document.getElementById('resetColumnWidthsBtn');
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                localStorage.removeItem(storageKey);

                document.querySelectorAll('#columnsMenu input[data-col]').forEach(cb => {
                    const col = cb.dataset.col;
                    cb.checked = true;
                    localStorage.removeItem('col-' + col);
                    setColumnVisibility(col, true);
                });

                clearColumnWidths();
                syncStickyOffsets();

                const menu = document.getElementById('columnsMenu');
                if (menu) {
                    menu.classList.remove('open');
                }
            });
        }

        loadWidths();
        syncStickyOffsets();
    }


    // ── Store Tooltip ─────────────────────────────────────────────────────────
    function showStoreTooltip(e, el) {
        e.stopPropagation();
        document.querySelectorAll('.store-tooltip-popup').forEach(t => t.remove());
        const store = el.dataset.store;
        if (!store) return;
        const tip = document.createElement('div');
        tip.className = 'store-tooltip-popup';
        tip.textContent = store;
        tip.style.cssText = 'position:fixed;background:#1e293b;color:#fff;padding:4px 10px;border-radius:6px;font-size:11px;z-index:9999;pointer-events:none;white-space:nowrap;';
        document.body.appendChild(tip);
        const rect = el.getBoundingClientRect();
        tip.style.left = (rect.left + rect.width/2 - tip.offsetWidth/2) + 'px';
        tip.style.top = (rect.top - tip.offsetHeight - 4) + 'px';
        setTimeout(() => tip.remove(), 2500);
    }
    document.addEventListener('click', () => {
        document.querySelectorAll('.store-tooltip-popup').forEach(t => t.remove());
    });

    // ── Drag & Drop Column Reorder ────────────────────────────────────────────
    let dragSrcIndex = null;

    function initColumnDragDrop() {
        const table = document.getElementById('comparisonResizableTable');
        if (!table) return;
        const headers = table.querySelectorAll('thead th');

        headers.forEach((th, index) => {
            if (th.classList.contains('col-toggle')) return;

            th.setAttribute('draggable', 'true');
            th.style.cursor = 'grab';

            th.addEventListener('dragstart', (e) => {
                dragSrcIndex = index;
                th.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            th.addEventListener('dragend', () => {
                th.classList.remove('dragging');
                headers.forEach(h => h.classList.remove('drag-over'));
            });

            th.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                headers.forEach(h => h.classList.remove('drag-over'));
                th.classList.add('drag-over');
            });

            th.addEventListener('drop', (e) => {
                e.preventDefault();
                if (dragSrcIndex === null || dragSrcIndex === index) return;

                const rows = table.querySelectorAll('tr');
                rows.forEach(row => {
                    const cells = Array.from(row.children);
                    if (cells.length <= Math.max(dragSrcIndex, index)) return;

                    const draggedCell = cells[dragSrcIndex];
                    const targetCell  = cells[index];

                    if (dragSrcIndex < index) {
                        row.insertBefore(draggedCell, targetCell.nextSibling);
                    } else {
                        row.insertBefore(draggedCell, targetCell);
                    }
                });

                dragSrcIndex = null;
                headers.forEach(h => h.classList.remove('drag-over'));
                syncStickyOffsets();
            });
        });
    }

</script>

</div>

<style>
.cmp-stat-card-link {
    text-decoration: none;
    color: inherit;
    display: flex;
    align-items: center;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.cmp-stat-card-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
    border-color: #c9d7eb;
}

.cmp-filter-badge-wrap {
    margin: 0 0 14px;
}

.cmp-filter-badge {
    display: inline-flex;
    align-items: center;
    padding: 10px 14px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
}

.cmp-filter-badge-red {
    background: #fee2e2;
    color: #991b1b;
}

.cmp-filter-badge-green {
    background: #dcfce7;
    color: #166534;
}

.comparison-page-only .cmp-table-wrap {
    width: 100%;
    overflow-x: auto;
    overflow-y: visible;
    position: relative;
    border: 1px solid #dbe3ef;
    border-radius: 20px;
    background: #fff;
}

.comparison-page-only .cmp-table {
    width: max-content;
    min-width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
    background: #fff;
}

.comparison-page-only .cmp-table thead th {
    position: sticky;
    top: 0;
    z-index: 50;
    background: #f8fbff;
    box-shadow: inset 0 -1px 0 #dbe3ef;
    font-size: 12px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    line-height: 1.05;
    padding: 14px 12px;
    white-space: nowrap;
}

.comparison-page-only .cmp-table tbody td {
    font-size: 13px;
    padding: 16px 12px;
    background: #fff;
    border-top: 1px solid #dbe3ef;
    vertical-align: middle;
}

.comparison-page-only .cmp-table th.col-product,
.comparison-page-only .cmp-table td.col-product {
    position: sticky;
    left: 0;
    z-index: 45;
    background: #fff;
    box-shadow: inset -1px 0 0 #dbe3ef;
}

.comparison-page-only .cmp-table thead th.col-product {
    z-index: 60;
    background: #f8fbff;
}

.comparison-page-only .col-product {
    width: 220px;
    min-width: 180px;
}

.comparison-page-only .cmp-product-link {
    display: block;
    font-size: 12px;
    line-height: 1.18;
    font-weight: 700;
    color: #4338ca;
    text-decoration: none;
    white-space: normal;
    word-break: break-word;
}

.comparison-page-only .col-our_price,
.comparison-page-only .col-technopolis,
.comparison-page-only .col-technomarket,
.comparison-page-only .col-techmart,
.comparison-page-only .col-tehnomix,
.comparison-page-only .col-zora,
.comparison-page-only .col-pazaruvaj,
.comparison-page-only .col-lowest {
    width: 120px;
    min-width: 120px;
}

.comparison-page-only .col-technopolis_diff_euro,
.comparison-page-only .col-technopolis_diff_percent,
.comparison-page-only .col-technomarket_diff_euro,
.comparison-page-only .col-technomarket_diff_percent,
.comparison-page-only .col-techmart_diff_euro,
.comparison-page-only .col-techmart_diff_percent,
.comparison-page-only .col-tehnomix_diff_euro,
.comparison-page-only .col-tehnomix_diff_percent,
.comparison-page-only .col-zora_diff_euro,
.comparison-page-only .col-zora_diff_percent,
.comparison-page-only .col-diff,
.comparison-page-only .col-diff_percent {
    width: 110px;
    min-width: 110px;
}

.comparison-page-only .col-offers {
    width: 90px;
    min-width: 90px;
}

.comparison-page-only .col-position {
    width: 100px;
    min-width: 100px;
}

.comparison-page-only .col-toggle {
    width: 70px;
    min-width: 70px;
}

.comparison-page-only .cmp-col-hidden {
    display: none !important;
}

.comparison-page-only .cmp-table tbody tr:hover td {
    background: #f8fbff;
}

.comparison-page-only .cmp-table tbody tr:hover td.col-product {
    background: #f8fbff;
}

.comparison-page-only .cmp-offers-row td {
    position: relative;
    z-index: 5;
    background: #fff;
}

.comparison-page-only .cmp-offers-panel {
    position: relative;
    z-index: 6;
}

.comparison-page-only .cmp-arrow-btn {
    width: 40px;
    height: 40px;
    font-size: 16px;
    position: relative;
    z-index: 10;
    border: 1px solid #dbe3ef;
    border-radius: 999px;
    background: #ffffff;
    color: #2563eb;
    cursor: pointer;
    transition: all 0.2s ease;
}

.comparison-page-only .cmp-arrow-btn:hover {
    background: #f8fbff;
    border-color: #c9d7eb;
}

.comparison-page-only .cmp-arrow-btn.active {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}

.comparison-page-only .cmp-toolbar-reset-icon {
    border: 1px solid #dbe3ef;
    background: #fff;
    cursor: pointer;
}

.comparison-page-only .cmp-toolbar-reset-icon:hover {
    background: #f8fbff;
    border-color: #c9d7eb;
}

.comparison-page-only .badge-green,
.comparison-page-only .badge-gray,
.comparison-page-only .cmp-diff-chip,
.comparison-page-only .cmp-offers-count-pill,
.comparison-page-only .cmp-lowest-price-text {
    font-size: 12px;
}

.comparison-page-only .cmp-resizable-table th,
.comparison-page-only .cmp-resizable-table td {
    position: relative;
    overflow: hidden;
}

.comparison-page-only .cmp-resizable-table thead th {
    user-select: none;
}

.comparison-page-only .cmp-resizable-table th.is-resizing {
    cursor: col-resize !important;
}

.comparison-page-only .cmp-col-resize-handle {
    position: absolute;
    top: 0;
    right: -3px;
    width: 10px;
    height: 100%;
    cursor: col-resize;
    z-index: 80;
}

.comparison-page-only .cmp-col-resize-handle::after {
    content: "";
    position: absolute;
    top: 18%;
    bottom: 18%;
    right: 3px;
    width: 2px;
    border-radius: 2px;
    background: rgba(59, 130, 246, 0.22);
    transition: background 0.2s ease;
}

.comparison-page-only .cmp-resizable-table thead th:hover .cmp-col-resize-handle::after,
.comparison-page-only .cmp-resizable-table th.is-resizing .cmp-col-resize-handle::after {
    background: rgba(59, 130, 246, 0.75);
}

/* DARK MODE */
.dark .comparison-page-only .cmp-table-wrap {
    border-color: #1f2a44 !important;
    background: #0f172a !important;
    scrollbar-color: #223a5e #0b1220;
    scrollbar-width: thin;
}

.dark .comparison-page-only .cmp-table-wrap::-webkit-scrollbar { height: 12px; width: 12px; }
.dark .comparison-page-only .cmp-table-wrap::-webkit-scrollbar-track { background: #0b1220; border-radius: 999px; }
.dark .comparison-page-only .cmp-table-wrap::-webkit-scrollbar-thumb { background: #223a5e; border-radius: 999px; border: 2px solid #0b1220; }
.dark .comparison-page-only .cmp-table-wrap::-webkit-scrollbar-thumb:hover { background: #2f5ea8; }
.dark .comparison-page-only .cmp-table { background: #0f172a !important; }
.dark .comparison-page-only .cmp-table thead th { background: #111c34 !important; color: #94a3b8 !important; box-shadow: inset 0 -1px 0 #22304d !important; }
.dark .comparison-page-only .cmp-table tbody td { background: #0f172a !important; border-top: 1px solid #22304d !important; color: #e5e7eb !important; }
.dark .comparison-page-only .cmp-table th.col-product,
.dark .comparison-page-only .cmp-table td.col-product { background: #0f172a !important; color: #e5e7eb !important; box-shadow: inset -1px 0 0 #22304d !important; }
.dark .comparison-page-only .cmp-table thead th.col-product { background: #111c34 !important; }
.dark .comparison-page-only .cmp-table tbody tr:hover td,
.dark .comparison-page-only .cmp-table tbody tr:hover td.col-product { background: #13203a !important; }
.dark .comparison-page-only .cmp-offers-row td,
.dark .comparison-page-only .cmp-offers-panel { background: #0f172a !important; color: #e5e7eb !important; }
.dark .comparison-page-only .cmp-product-link { color: #a5b4fc !important; }
.dark .comparison-page-only .cmp-toolbar-reset-icon { border-color: #22304d !important; background: #111c34 !important; color: #e5e7eb !important; }
.dark .comparison-page-only .cmp-toolbar-reset-icon:hover { background: #16233d !important; border-color: #2f4670 !important; }
.dark .comparison-page-only .cmp-arrow-btn { background: linear-gradient(135deg, #1d4ed8, #2563eb) !important; color: #ffffff !important; border: 1px solid rgba(255,255,255,0.08) !important; box-shadow: 0 8px 24px rgba(0,0,0,0.35) !important; }
.dark .comparison-page-only .cmp-arrow-btn:hover { background: linear-gradient(135deg, #2563eb, #3b82f6) !important; color: #ffffff !important; }
.dark .comparison-page-only .cmp-arrow-btn.active { background: linear-gradient(135deg, #3b82f6, #60a5fa) !important; color: #ffffff !important; border-color: transparent !important; }
.dark .comparison-page-only .cmp-col-resize-handle::after { background: rgba(96, 165, 250, 0.35) !important; }
.dark .comparison-page-only .cmp-resizable-table thead th:hover .cmp-col-resize-handle::after,
.dark .comparison-page-only .cmp-resizable-table th.is-resizing .cmp-col-resize-handle::after { background: rgba(96, 165, 250, 0.95) !important; }

/* FORCE DARK */
.dark .comparison-page-only .cmp-table-wrap,
.dark .comparison-page-only .cmp-table,
.dark .comparison-page-only .cmp-table thead th,
.dark .comparison-page-only .cmp-table tbody td,
.dark .comparison-page-only .cmp-offers-row td,
.dark .comparison-page-only .cmp-offers-panel { background-color: #0f172a !important; color: #e5e7eb !important; }
.dark .comparison-page-only .cmp-table thead th { background-color: #111c34 !important; color: #94a3b8 !important; }
.dark .comparison-page-only .cmp-table th.col-product,
.dark .comparison-page-only .cmp-table td.col-product { background-color: #0f172a !important; color: #e5e7eb !important; box-shadow: inset -1px 0 0 #22304d !important; }
.dark .comparison-page-only .cmp-table thead th.col-product { background-color: #111c34 !important; }

/* CENTER ALL TABLE CELLS */
.comparison-page-only .cmp-table th,
.comparison-page-only .cmp-table td { text-align: center; vertical-align: middle; }
.comparison-page-only .cmp-table th.col-product,
.comparison-page-only .cmp-table td.col-product { text-align: left; }
.comparison-page-only .cmp-price,
.comparison-page-only .cmp-diff-chip,
.comparison-page-only .cmp-offers-count-pill,
.comparison-page-only .cmp-lowest-price-text,
.comparison-page-only .badge-green,
.comparison-page-only .badge-gray { margin-left: auto; margin-right: auto; }
.comparison-page-only .col-toggle { text-align: center; }

/* FINAL DARK FIX */
.dark .comparison-page-only .cmp-table thead th.col-product { background: #111c34 !important; color: #94a3b8 !important; box-shadow: inset -1px 0 0 #22304d !important; }
.dark .comparison-page-only .cmp-table tbody td.col-product,
.dark .comparison-page-only .cmp-table tbody tr td.col-product,
.dark .comparison-page-only .cmp-main-row td.col-product { background: #0f172a !important; color: #e5e7eb !important; box-shadow: inset -1px 0 0 #22304d !important; }
.dark .comparison-page-only .cmp-table tbody tr:hover td.col-product,
.dark .comparison-page-only .cmp-table tbody tr.cmp-main-row:hover td.col-product { background: #13203a !important; }
.dark .comparison-page-only .cmp-product-link { color: #a5b4fc !important; }
.dark .comparison-page-only .col-toggle,
.dark .comparison-page-only .cmp-table thead th.col-toggle,
.dark .comparison-page-only .cmp-table tbody td.col-toggle { background: #0f172a !important; color: #e5e7eb !important; }
.dark .comparison-page-only .cmp-arrow-btn { width: 42px; height: 42px; border-radius: 999px; background: #111c34 !important; color: #60a5fa !important; border: 1px solid #22304d !important; box-shadow: none !important; }
.dark .comparison-page-only .cmp-arrow-btn:hover { background: #16233d !important; border-color: #2f4670 !important; color: #93c5fd !important; }
.dark .comparison-page-only .cmp-arrow-btn.active { background: #1d4ed8 !important; color: #ffffff !important; border-color: #2563eb !important; }

/* FORCE DARK MODE FIX */
.dark-mode .comparison-page-only .cmp-table th.col-product,
.dark-mode .comparison-page-only .cmp-table td.col-product { background: #0f172a !important; color: #e5e7eb !important; }
.dark-mode .comparison-page-only .cmp-table thead th.col-product { background: #111c34 !important; }
.dark-mode .comparison-page-only .cmp-table tbody tr:hover td.col-product { background: #13203a !important; }
.dark-mode .comparison-page-only .cmp-table td.col-product { z-index: 5 !important; }
.dark-mode .comparison-page-only .cmp-arrow-btn { background: #111c34 !important; color: #60a5fa !important; border: 1px solid #22304d !important; }
.dark-mode .comparison-page-only .cmp-arrow-btn:hover { background: #16233d !important; color: #93c5fd !important; }

/* GLOBAL DARK SCROLLBAR */
.dark-mode * { scrollbar-width: thin; scrollbar-color: #223a5e #0b1220; }
.dark-mode *::-webkit-scrollbar { width: 10px; height: 10px; }
.dark-mode *::-webkit-scrollbar-track { background: #0b1220; border-radius: 999px; }
.dark-mode *::-webkit-scrollbar-thumb { background: #223a5e; border-radius: 999px; border: 2px solid #0b1220; }
.dark-mode *::-webkit-scrollbar-thumb:hover { background: #2f5ea8; }
</style>
@endsection