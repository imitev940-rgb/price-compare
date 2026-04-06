<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Price Comparison PDF</title>

    <style>
        @page {
            margin: 20px 18px 18px 18px;
        }

        html, body {
            font-family: "DejaVu Sans";
            font-size: 10px;
            color: #1f2937;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        * {
            font-family: "DejaVu Sans";
            box-sizing: border-box;
        }

        .pdf-shell {
            width: 100%;
        }

        .pdf-header {
            width: 100%;
            margin-bottom: 14px;
        }

        .pdf-header-table,
        .pdf-stats-table,
        .pdf-footer-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pdf-header-table td,
        .pdf-footer-table td {
            vertical-align: middle;
        }

        .pdf-brand-wrap {
            width: 70%;
        }

        .pdf-meta-wrap {
            width: 30%;
            text-align: right;
        }

        .pdf-brand {
            display: inline-block;
        }

        .pdf-logo {
            height: 34px;
            vertical-align: middle;
            margin-right: 10px;
        }

        .pdf-brand-text {
            display: inline-block;
            vertical-align: middle;
        }

        .pdf-brand-title {
            font-size: 18px;
            font-weight: 700;
            color: #1d4ed8;
            line-height: 1.1;
        }

        .pdf-brand-subtitle {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
        }

        .pdf-meta-label {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .pdf-meta-value {
            font-size: 11px;
            font-weight: 700;
            color: #111827;
        }

        .pdf-hero {
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }

        .pdf-hero-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 3px;
        }

        .pdf-hero-subtitle {
            font-size: 10px;
            color: #475569;
        }

        .pdf-stats {
            width: 100%;
            margin-bottom: 12px;
        }

        .pdf-stats-table td {
            width: 25%;
            padding-right: 8px;
        }

        .pdf-stats-table td:last-child {
            padding-right: 0;
        }

        .pdf-stat-card {
            border: 1px solid #dbe3ef;
            background: #f8fafc;
            border-radius: 10px;
            padding: 10px 12px;
        }

        .pdf-stat-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 4px;
        }

        .pdf-stat-value {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }

        .pdf-stat-small {
            font-size: 12px;
        }

        .pdf-table-wrap {
            margin-top: 8px;
        }

        .pdf-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #dbe3ef;
        }

        .pdf-table thead th {
            background: #eff6ff;
            color: #334155;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 7px 5px;
            border: 1px solid #dbe3ef;
            text-align: center;
            line-height: 1.15;
        }

        .pdf-table tbody td {
            border: 1px solid #e5e7eb;
            padding: 6px 5px;
            font-size: 9px;
            vertical-align: middle;
        }

        .product-cell {
            width: 24%;
            font-size: 8.8px;
            line-height: 1.2;
            font-weight: 600;
            color: #0f172a;
            word-wrap: break-word;
        }

        .num {
            text-align: center;
            white-space: nowrap;
        }

        .row-best {
            background: #ecfdf5;
        }

        .row-over {
            background: #fff7ed;
        }

        .row-same {
            background: #f8fafc;
        }

        .pill {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 999px;
            font-size: 8px;
            font-weight: 700;
            line-height: 1.2;
            white-space: nowrap;
        }

        .pill-lowest {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .pill-offers {
            background: #ede9fe;
            color: #6d28d9;
        }

        .pill-best {
            background: #dcfce7;
            color: #166534;
        }

        .pill-gray {
            background: #e5e7eb;
            color: #374151;
        }

        .pill-up {
            background: #dcfce7;
            color: #166534;
        }

        .pill-down {
            background: #fee2e2;
            color: #991b1b;
        }

        .pdf-footer {
            margin-top: 12px;
            font-size: 8.5px;
            color: #64748b;
            width: 100%;
        }

        .pdf-footer-left {
            text-align: left;
        }

        .pdf-footer-right {
            text-align: right;
        }
    </style>
</head>
<body>
<div class="pdf-shell">
    <div class="pdf-header">
        <table class="pdf-header-table">
            <tr>
                <td class="pdf-brand-wrap">
                    <div class="pdf-brand">
                        @if(file_exists(public_path('images/logo.png')))
                            <img src="{{ public_path('images/logo.png') }}" alt="PriceHunterPro" class="pdf-logo">
                        @endif

                        <div class="pdf-brand-text">
                            <div class="pdf-brand-title">PriceHunterPro</div>
                            <div class="pdf-brand-subtitle">Компактен отчет за конкурентни цени</div>
                        </div>
                    </div>
                </td>
                <td class="pdf-meta-wrap">
                    <div class="pdf-meta-label">Дата</div>
                    <div class="pdf-meta-value">{{ now()->format('d.m.Y H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="pdf-hero">
        <div class="pdf-hero-title">Отчет за ценово сравнение</div>
        <div class="pdf-hero-subtitle">Компактен изглед с основните ценови показатели и позицията в Pazaruvaj</div>
    </div>

    <div class="pdf-stats">
        <table class="pdf-stats-table">
            <tr>
                <td>
                    <div class="pdf-stat-card">
                        <div class="pdf-stat-label">Продукти</div>
                        <div class="pdf-stat-value">{{ $products->count() }}</div>
                    </div>
                </td>
                <td>
                    <div class="pdf-stat-card">
                        <div class="pdf-stat-label">Best Price Wins</div>
                        <div class="pdf-stat-value">
                            {{ $products->filter(fn($p) => ($p->pazaruvaj_offers_count ?? 0) > 0 && $p->pazaruvaj_our_position !== null && (int)$p->pazaruvaj_our_position === 1)->count() }}
                        </div>
                    </div>
                </td>
                <td>
                    <div class="pdf-stat-card">
                        <div class="pdf-stat-label">Not #1</div>
                        <div class="pdf-stat-value">
                            {{ $products->filter(fn($p) => ($p->pazaruvaj_offers_count ?? 0) > 0 && $p->pazaruvaj_our_position !== null && (int)$p->pazaruvaj_our_position > 1)->count() }}
                        </div>
                    </div>
                </td>
                <td>
                    <div class="pdf-stat-card">
                        <div class="pdf-stat-label">Генериран</div>
                        <div class="pdf-stat-value pdf-stat-small">{{ now()->format('d.m.Y') }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="pdf-table-wrap">
        <table class="pdf-table">
            <thead>
                <tr>
                    <th style="width: 24%;">Продукт</th>
                    <th>Наша цена</th>
                    <th>Technopolis</th>
                    <th>Technomarket</th>
                    <th>Techmart</th>
                    <th>Tehnomix</th>
                    <th>Zora</th>
                    <th>Pazaruvaj</th>
                    <th>Най-ниска</th>
                    <th>Оферти</th>
                    <th>Позиция</th>
                    <th>Разлика €</th>
                    <th>Разлика %</th>
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
                            {{ $product->techmart_price !== null ? number_format((float) $product->techmart_price, 2) : '—' }}
                        </td>

                        <td class="num">
                            {{ $product->tehnomix_price !== null ? number_format((float) $product->tehnomix_price, 2) : '—' }}
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
        <table class="pdf-footer-table">
            <tr>
                <td class="pdf-footer-left">© {{ now()->format('Y') }} PriceHunterPro</td>
                <td class="pdf-footer-right">Generated by SITEZZY – Ivan Mitev</td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>