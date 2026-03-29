<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <title>Price Comparison PDF</title>
    <link rel="stylesheet" href="{{ public_path('css/pdf.css') }}">
</head>
<body>

<div class="pdf-shell">
    <div class="pdf-header">
        <div class="pdf-brand">
            <img src="{{ public_path('images/logo.png') }}" alt="PriceHunterPro" class="pdf-logo">
            <div class="pdf-brand-text">
                <div class="pdf-brand-title">PriceHunterPro</div>
                <div class="pdf-brand-subtitle">Competitor pricing dashboard</div>
            </div>
        </div>

        <div class="pdf-meta">
            <div class="pdf-meta-label">Дата</div>
            <div class="pdf-meta-value">{{ now()->format('d.m.Y H:i') }}</div>
        </div>
    </div>

    <div class="pdf-hero">
        <div class="pdf-hero-title">Price Comparison Report</div>
        <div class="pdf-hero-subtitle">Market overview and competitor pricing summary</div>
    </div>

    <div class="pdf-stats">
        <div class="pdf-stat-card">
            <div class="pdf-stat-label">Products</div>
            <div class="pdf-stat-value">{{ $products->count() }}</div>
        </div>

        <div class="pdf-stat-card">
            <div class="pdf-stat-label">Best Price Wins</div>
            <div class="pdf-stat-value">
                {{ $products->filter(fn($p) => $p->our_price !== null && $p->lowest_market_price !== null && (float)$p->our_price <= (float)$p->lowest_market_price)->count() }}
            </div>
        </div>

        <div class="pdf-stat-card">
            <div class="pdf-stat-label">Generated</div>
            <div class="pdf-stat-value pdf-stat-small">{{ now()->format('d.m.Y') }}</div>
        </div>
    </div>

    <div class="pdf-table-wrap">
        <table class="pdf-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Our Price</th>
                    <th>Technopolis</th>
                    <th>Technomarket</th>
                    <th>Zora</th>
                    <th>Pazaruvaj</th>
                    <th>Lowest</th>
                    <th>Offers</th>
                    <th>Position</th>
                    <th>Diff €</th>
                    <th>Diff %</th>
                </tr>
            </thead>

            <tbody>
                @foreach($products as $product)
                    @php
                        $diffAmount = $product->difference_amount !== null ? (float) $product->difference_amount : null;
                        $diffPercent = $product->difference_percent !== null ? (float) $product->difference_percent : null;

                        $rowClass = '';
                        if ($diffAmount !== null) {
                            if ($diffAmount < 0) {
                                $rowClass = 'row-best';
                            } elseif ($diffAmount > 0) {
                                $rowClass = 'row-over';
                            } else {
                                $rowClass = 'row-same';
                            }
                        }
                    @endphp

                    <tr class="{{ $rowClass }}">
                        <td class="product-cell">{{ $product->name }}</td>

                        <td class="num">
                            {{ $product->our_price !== null ? number_format((float) $product->our_price, 2) : '—' }}
                        </td>

                        <td class="num">
                            {{ $product->technopolis_price !== null ? number_format((float) $product->technopolis_price, 2) : '—' }}
                        </td>

                        <td class="num">
                            {{ $product->technomarket_price !== null ? number_format((float) $product->technomarket_price, 2) : '—' }}
                        </td>

                        <td class="num">
                            {{ $product->zora_price !== null ? number_format((float) $product->zora_price, 2) : '—' }}
                        </td>

                        <td class="num">
                            {{ $product->pazaruvaj_lowest_price !== null ? number_format((float) $product->pazaruvaj_lowest_price, 2) : '—' }}
                        </td>

                        <td class="num">
                            @if($product->lowest_market_price !== null)
                                <span class="pill pill-lowest">
                                    {{ number_format((float) $product->lowest_market_price, 2) }}
                                </span>
                            @else
                                —
                            @endif
                        </td>

                        <td class="num">
                            <span class="pill pill-offers">{{ $product->offers_count ?? 0 }}</span>
                        </td>

                        <td class="num">
                            @if($product->pazaruvaj_our_position == 1)
                                <span class="pill pill-best">#1</span>
                            @elseif($product->pazaruvaj_our_position)
                                <span class="pill pill-gray">#{{ $product->pazaruvaj_our_position }}</span>
                            @else
                                —
                            @endif
                        </td>

                        <td class="num">
                            @if($diffAmount !== null)
                                <span class="pill {{ $diffAmount < 0 ? 'pill-up' : ($diffAmount > 0 ? 'pill-down' : 'pill-gray') }}">
                                    {{ $diffAmount < 0 ? '↑' : ($diffAmount > 0 ? '↓' : '•') }}
                                    {{ number_format(abs($diffAmount), 2) }}
                                </span>
                            @else
                                —
                            @endif
                        </td>

                        <td class="num">
                            @if($diffPercent !== null)
                                <span class="pill {{ $diffPercent < 0 ? 'pill-up' : ($diffPercent > 0 ? 'pill-down' : 'pill-gray') }}">
                                    {{ $diffPercent < 0 ? '↑' : ($diffPercent > 0 ? '↓' : '•') }}
                                    {{ number_format(abs($diffPercent), 1) }}%
                                </span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="pdf-footer">
        <div>© {{ now()->format('Y') }} PriceHunterPro</div>
        <div>Generated by SITEZZY – Ivan Mitev</div>
    </div>
</div>

</body>
</html>