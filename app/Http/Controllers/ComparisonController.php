<?php

namespace App\Http\Controllers;

use App\Exports\ComparisonSummaryExport;
use App\Exports\ComparisonWorkbookExport;
use App\Models\CompetitorLink;
use App\Models\Product;
use App\Models\Store;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

class ComparisonController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $sort = $request->get('sort');
        $status = $request->get('status', 'all');
        $pazaruvajRank = $request->get('pazaruvaj_rank');

        $allowedPerPage = [10, 25, 50, 100];
        $perPage = (int) $request->get('per_page', 10);

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $allProducts = $this->buildProcessedProductsCollection($search, $sort, $status, $pazaruvajRank);

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $allProducts->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $products = new LengthAwarePaginator(
            $currentItems,
            $allProducts->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $productsCount = Product::count();
        $storesCount = Store::count();
        $linksCount = CompetitorLink::count();

        $bestPriceWins = $this->buildProcessedProductsCollection($search, $sort, $status)
            ->filter(function ($product) {
                return ($product->pazaruvaj_offers_count ?? 0) > 0
                    && $product->pazaruvaj_our_position !== null
                    && (int) $product->pazaruvaj_our_position === 1;
            })->count();

        $notBestPriceCount = $this->buildProcessedProductsCollection($search, $sort, $status)
            ->filter(function ($product) {
                return ($product->pazaruvaj_offers_count ?? 0) > 0
                    && $product->pazaruvaj_our_position !== null
                    && (int) $product->pazaruvaj_our_position > 1;
            })->count();

        return view('comparison.index', [
            'products' => $products,
            'productsCount' => $productsCount,
            'storesCount' => $storesCount,
            'linksCount' => $linksCount,
            'bestPriceWins' => $bestPriceWins,
            'notBestPriceCount' => $notBestPriceCount,
            'search' => $search,
            'sort' => $sort,
            'status' => $status,
            'perPage' => $perPage,
            'pazaruvajRank' => $pazaruvajRank,
        ]);
    }

    public function exportCsv(Request $request)
    {
        $products = $this->buildProcessedProductsCollection(
            trim((string) $request->get('search', '')),
            $request->get('sort'),
            $request->get('status', 'all'),
            $request->get('pazaruvaj_rank')
        );

        $filename = 'comparison-summary-' . now()->format('Y-m-d_H-i-s') . '.csv';

        return Excel::download(
            new ComparisonSummaryExport($products),
            $filename,
            ExcelWriter::CSV,
            ['Content-Type' => 'text/csv']
        );
    }

    public function exportExcel(Request $request)
    {
        $products = $this->buildProcessedProductsCollection(
            trim((string) $request->get('search', '')),
            $request->get('sort'),
            $request->get('status', 'all'),
            $request->get('pazaruvaj_rank')
        );

        $filename = 'comparison-export-' . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(
            new ComparisonWorkbookExport($products),
            $filename,
            ExcelWriter::XLSX
        );
    }

    public function exportPdf(Request $request)
    {
        $products = $this->buildProcessedProductsCollection(
            trim((string) $request->get('search', '')),
            $request->get('sort'),
            $request->get('status', 'all'),
            $request->get('pazaruvaj_rank')
        );

        $pdf = Pdf::loadView('comparison.pdf', [
            'products' => $products,
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download('comparison-export-' . now()->format('Y-m-d_H-i-s') . '.pdf');
    }

    private function buildProcessedProductsCollection(
        string $search = '',
        ?string $sort = null,
        string $status = 'all',
        ?string $pazaruvajRank = null
    ) {
        $productsQuery = Product::with([
            'competitorLinks.store',
            'pazaruvajOffers',
        ]);

        if ($search !== '') {
            $productsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('ean', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $productsQuery->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $productsQuery->where('is_active', 0);
        }

        $products = $productsQuery->latest()->get()->map(function ($product) {
            $linksByStore = [];

            foreach ($product->competitorLinks as $link) {
                $storeName = strtolower(trim((string) ($link->store->name ?? '')));

                if ($storeName !== '') {
                    $linksByStore[$storeName] = $link;
                }
            }

            $technopolis  = $linksByStore['technopolis']  ?? null;
            $technomarket = $linksByStore['technomarket']  ?? null;
            $techmart     = $linksByStore['techmart']      ?? null;
            $tehnomix     = $linksByStore['tehnomix']      ?? null;
            $zora         = $linksByStore['zora']          ?? null;
            $pazaruvaj    = $linksByStore['pazaruvaj']     ?? null;

            $technopolisPrice  = $this->normalizePrice($technopolis?->last_price);
            $technomarketPrice = $this->normalizePrice($technomarket?->last_price);
            $techmartPrice     = $this->normalizePrice($techmart?->last_price);
            $tehnomixPrice     = $this->normalizePrice($tehnomix?->last_price);
            $zoraPrice         = $this->normalizePrice($zora?->last_price);
            $ourPrice          = $this->normalizePrice($product->our_price);

            $calcStoreDiff = function (?float $our, ?float $competitor): array {
                if ($our === null || $competitor === null || $competitor <= 0) {
                    return [null, null];
                }

                $diffEuro    = round($our - $competitor, 2);
                $diffPercent = round((($our - $competitor) / $competitor) * 100, 2);

                return [$diffEuro, $diffPercent];
            };

            [$technopolisDiffEuro,  $technopolisDiffPercent]  = $calcStoreDiff($ourPrice, $technopolisPrice);
            [$technomarketDiffEuro, $technomarketDiffPercent] = $calcStoreDiff($ourPrice, $technomarketPrice);
            [$techmartDiffEuro,     $techmartDiffPercent]     = $calcStoreDiff($ourPrice, $techmartPrice);
            [$tehnomixDiffEuro,     $tehnomixDiffPercent]     = $calcStoreDiff($ourPrice, $tehnomixPrice);
            [$zoraDiffEuro,         $zoraDiffPercent]         = $calcStoreDiff($ourPrice, $zoraPrice);

            $lowestDirectPrice = collect([
                $technopolisPrice,
                $technomarketPrice,
                $techmartPrice,
                $tehnomixPrice,
                $zoraPrice,
            ])->filter(fn ($price) => $price !== null && $price > 0)->min();

            $pazaruvajOffers = $product->pazaruvajOffers
                ->filter(fn ($offer) => $offer->price !== null && (float) $offer->price > 0)
                ->sortBy(fn ($offer) => $offer->position ?? PHP_INT_MAX)
                ->values();

            $lowestOffer = $pazaruvajOffers
                ->sortBy(fn ($offer) => (float) $offer->price)
                ->first();

            $pazaruvajLowest      = $lowestOffer ? (float) $lowestOffer->price : $this->normalizePrice($pazaruvaj?->last_price);
            $pazaruvajLowestStore = $lowestOffer?->store_name ?? '—';
            $pazaruvajOffersCount = $pazaruvajOffers->count();

            $ourPosition = null;

            if ($ourPrice !== null && $ourPrice > 0 && $pazaruvajOffersCount > 0) {
                $sortedByPrice = $pazaruvajOffers
                    ->sortBy(fn ($offer) => (float) $offer->price)
                    ->values();

                foreach ($sortedByPrice as $index => $offer) {
                    if ((float) $ourPrice <= (float) $offer->price) {
                        $ourPosition = $index + 1;
                        break;
                    }
                }

                if ($ourPosition === null) {
                    $ourPosition = $pazaruvajOffersCount + 1;
                }
            }

            $lowestMarketPrice = collect([
                $pazaruvajLowest,
                $technopolisPrice,
                $technomarketPrice,
                $techmartPrice,
                $tehnomixPrice,
                $zoraPrice,
            ])->filter(fn ($price) => $price !== null && $price > 0)->min();

            $differenceAmount  = null;
            $differencePercent = null;

            if ($ourPrice !== null && $ourPrice > 0 && $pazaruvajLowest !== null && $pazaruvajLowest > 0) {
                $differenceAmount  = round($ourPrice - $pazaruvajLowest, 2);
                $differencePercent = round((($ourPrice - $pazaruvajLowest) / $pazaruvajLowest) * 100, 2);
            }

            $product->technopolis_price  = $technopolisPrice;
            $product->technomarket_price = $technomarketPrice;
            $product->techmart_price     = $techmartPrice;
            $product->tehnomix_price     = $tehnomixPrice;
            $product->zora_price         = $zoraPrice;

            $product->technopolis_diff_euro    = $technopolisDiffEuro;
            $product->technopolis_diff_percent = $technopolisDiffPercent;

            $product->technomarket_diff_euro    = $technomarketDiffEuro;
            $product->technomarket_diff_percent = $technomarketDiffPercent;

            $product->techmart_diff_euro    = $techmartDiffEuro;
            $product->techmart_diff_percent = $techmartDiffPercent;

            $product->tehnomix_diff_euro    = $tehnomixDiffEuro;
            $product->tehnomix_diff_percent = $tehnomixDiffPercent;

            $product->zora_diff_euro    = $zoraDiffEuro;
            $product->zora_diff_percent = $zoraDiffPercent;

            $product->lowest_direct_price = $lowestDirectPrice;
            $product->lowest_market_price = $lowestMarketPrice;

            $product->pazaruvaj_lowest_price  = $pazaruvajLowest;
            $product->pazaruvaj_lowest_store  = $pazaruvajLowestStore;
            $product->pazaruvaj_offers_count  = $pazaruvajOffersCount;
            $product->pazaruvaj_our_position  = $ourPosition;
            $product->pazaruvaj_offers_list   = $pazaruvajOffers;

            $product->offers_count      = $pazaruvajOffersCount;
            $product->difference_amount  = $differenceAmount;
            $product->difference_percent = $differencePercent;

            return $product;
        });

        if ($pazaruvajRank === 'not_top') {
            $products = $products->filter(function ($product) {
                return ($product->pazaruvaj_offers_count ?? 0) > 0
                    && $product->pazaruvaj_our_position !== null
                    && (int) $product->pazaruvaj_our_position > 1;
            })->values();
        }

        if ($pazaruvajRank === 'top') {
            $products = $products->filter(function ($product) {
                return ($product->pazaruvaj_offers_count ?? 0) > 0
                    && $product->pazaruvaj_our_position !== null
                    && (int) $product->pazaruvaj_our_position === 1;
            })->values();
        }

        return $this->sortProductsCollection($products, $sort);
    }

    private function sortProductsCollection($products, ?string $sort)
    {
        return match ($sort) {
            'name_asc'  => $products->sortBy(fn ($p) => mb_strtolower((string) $p->name))->values(),
            'name_desc' => $products->sortByDesc(fn ($p) => mb_strtolower((string) $p->name))->values(),

            'price_asc'  => $products->sortBy(fn ($p) => $p->our_price ?? PHP_FLOAT_MAX)->values(),
            'price_desc' => $products->sortByDesc(fn ($p) => $p->our_price ?? -1)->values(),

            'lowest_asc'  => $products->sortBy(fn ($p) => $p->pazaruvaj_lowest_price ?? PHP_FLOAT_MAX)->values(),
            'lowest_desc' => $products->sortByDesc(fn ($p) => $p->pazaruvaj_lowest_price ?? -1)->values(),

            'market_asc'  => $products->sortBy(fn ($p) => $p->lowest_market_price ?? PHP_FLOAT_MAX)->values(),
            'market_desc' => $products->sortByDesc(fn ($p) => $p->lowest_market_price ?? -1)->values(),

            'offers_asc'  => $products->sortBy(fn ($p) => $p->pazaruvaj_offers_count ?? PHP_INT_MAX)->values(),
            'offers_desc' => $products->sortByDesc(fn ($p) => $p->pazaruvaj_offers_count ?? -1)->values(),

            'position_asc'  => $products->sortBy(fn ($p) => $p->pazaruvaj_our_position ?? PHP_INT_MAX)->values(),
            'position_desc' => $products->sortByDesc(fn ($p) => $p->pazaruvaj_our_position ?? -1)->values(),

            'diff_asc'  => $products->sortBy(fn ($p) => $p->difference_amount ?? PHP_FLOAT_MAX)->values(),
            'diff_desc' => $products->sortByDesc(fn ($p) => $p->difference_amount ?? -999999)->values(),

            'diff_percent_asc'  => $products->sortBy(fn ($p) => $p->difference_percent ?? PHP_FLOAT_MAX)->values(),
            'diff_percent_desc' => $products->sortByDesc(fn ($p) => $p->difference_percent ?? -999999)->values(),

            'tp_diff_asc'  => $products->sortBy(fn ($p) => $p->technopolis_diff_euro ?? PHP_FLOAT_MAX)->values(),
            'tp_diff_desc' => $products->sortByDesc(fn ($p) => $p->technopolis_diff_euro ?? -999999)->values(),

            'tp_diff_percent_asc'  => $products->sortBy(fn ($p) => $p->technopolis_diff_percent ?? PHP_FLOAT_MAX)->values(),
            'tp_diff_percent_desc' => $products->sortByDesc(fn ($p) => $p->technopolis_diff_percent ?? -999999)->values(),

            'tm_diff_asc'  => $products->sortBy(fn ($p) => $p->technomarket_diff_euro ?? PHP_FLOAT_MAX)->values(),
            'tm_diff_desc' => $products->sortByDesc(fn ($p) => $p->technomarket_diff_euro ?? -999999)->values(),

            'tm_diff_percent_asc'  => $products->sortBy(fn ($p) => $p->technomarket_diff_percent ?? PHP_FLOAT_MAX)->values(),
            'tm_diff_percent_desc' => $products->sortByDesc(fn ($p) => $p->technomarket_diff_percent ?? -999999)->values(),

            'techmart_diff_asc'  => $products->sortBy(fn ($p) => $p->techmart_diff_euro ?? PHP_FLOAT_MAX)->values(),
            'techmart_diff_desc' => $products->sortByDesc(fn ($p) => $p->techmart_diff_euro ?? -999999)->values(),

            'techmart_diff_percent_asc'  => $products->sortBy(fn ($p) => $p->techmart_diff_percent ?? PHP_FLOAT_MAX)->values(),
            'techmart_diff_percent_desc' => $products->sortByDesc(fn ($p) => $p->techmart_diff_percent ?? -999999)->values(),

            'tehnomix_diff_asc'  => $products->sortBy(fn ($p) => $p->tehnomix_diff_euro ?? PHP_FLOAT_MAX)->values(),
            'tehnomix_diff_desc' => $products->sortByDesc(fn ($p) => $p->tehnomix_diff_euro ?? -999999)->values(),

            'tehnomix_diff_percent_asc'  => $products->sortBy(fn ($p) => $p->tehnomix_diff_percent ?? PHP_FLOAT_MAX)->values(),
            'tehnomix_diff_percent_desc' => $products->sortByDesc(fn ($p) => $p->tehnomix_diff_percent ?? -999999)->values(),

            'zora_diff_asc'  => $products->sortBy(fn ($p) => $p->zora_diff_euro ?? PHP_FLOAT_MAX)->values(),
            'zora_diff_desc' => $products->sortByDesc(fn ($p) => $p->zora_diff_euro ?? -999999)->values(),

            'zora_diff_percent_asc'  => $products->sortBy(fn ($p) => $p->zora_diff_percent ?? PHP_FLOAT_MAX)->values(),
            'zora_diff_percent_desc' => $products->sortByDesc(fn ($p) => $p->zora_diff_percent ?? -999999)->values(),

            default => $products->values(),
        };
    }

    private function normalizePrice($price): ?float
    {
        if ($price === null || $price === '') {
            return null;
        }

        return round((float) $price, 2);
    }

    public function tvDashboard()
    {
        $products = $this->buildProcessedProductsCollection('', null, 'all');

        $notTop = $products->filter(function ($product) {
            $position = (int) ($product->pazaruvaj_our_position ?? 0);
            $diff     = (float) ($product->difference_amount ?? 0);
            $offers   = (int) ($product->pazaruvaj_offers_count ?? 0);

            return $offers > 0 && $position > 1 && $diff > 5;
        })->values();

        $top = $products->filter(function ($product) {
            if (($product->pazaruvaj_offers_count ?? 0) < 2) return false;
            if ((int) ($product->pazaruvaj_our_position ?? 0) !== 1) return false;

            $ourPrice = (float) ($product->our_price ?? 0);
            if ($ourPrice <= 0) return false;

            $sortedOffers = collect($product->pazaruvaj_offers_list ?? [])
                ->filter(fn ($offer) => $offer->price !== null && (float) $offer->price > 0)
                ->sortBy('price')
                ->values();

            if ($sortedOffers->count() < 2) return false;

            $nextCompetitor = $sortedOffers->first(fn ($offer) => (float) $offer->price > $ourPrice);
            if (!$nextCompetitor) return false;

            return ((float) $nextCompetitor->price - $ourPrice) > 5;
        })->values();

        return view('comparison.tv-dashboard', [
            'notTop' => $notTop,
            'top'    => $top,
        ]);
    }

    public function tvDashboardData(Request $request)
    {
        $page    = max(1, (int) $request->get('page', 1));
        $perPage = 999; // TV dashboard handles pagination client-side

        $products = $this->buildProcessedProductsCollection('', null, 'all');

        $notTopAll = $products->filter(function ($product) {
            $position = (int) ($product->pazaruvaj_our_position ?? 0);
            $diff     = (float) ($product->difference_amount ?? 0);
            $offers   = (int) ($product->pazaruvaj_offers_count ?? 0);

            return $offers > 0 && $position > 1 && $diff > 5;
        })->sortByDesc(function ($product) {
            return (int) ($product->pazaruvaj_our_position ?? 0) * 1000
                + abs((float) ($product->difference_amount ?? 0)) * 100
                + (int) ($product->pazaruvaj_offers_count ?? 0) * 10;
        })->values();

        $topAll = $products->filter(function ($product) {
            if (($product->pazaruvaj_offers_count ?? 0) < 2) return false;
            if ((int) ($product->pazaruvaj_our_position ?? 0) !== 1) return false;

            $ourPrice = (float) ($product->our_price ?? 0);
            if ($ourPrice <= 0) return false;

            $sortedOffers = collect($product->pazaruvaj_offers_list ?? [])
                ->filter(fn ($offer) => $offer->price !== null && (float) $offer->price > 0)
                ->sortBy('price')
                ->values();

            if ($sortedOffers->count() < 2) return false;

            $nextCompetitor = $sortedOffers->first(fn ($offer) => (float) $offer->price > $ourPrice);
            if (!$nextCompetitor) return false;

            return ((float) $nextCompetitor->price - $ourPrice) > 5;
        })->map(function ($product) {
            $ourPrice = (float) ($product->our_price ?? 0);

            $sortedOffers = collect($product->pazaruvaj_offers_list ?? [])
                ->filter(fn ($offer) => $offer->price !== null && (float) $offer->price > 0)
                ->sortBy('price')
                ->values();

            $nextCompetitor = $sortedOffers->first(fn ($offer) => (float) $offer->price > $ourPrice);
            $nextPrice      = $nextCompetitor ? (float) $nextCompetitor->price : null;
            $leadEuro       = $nextPrice !== null ? round($nextPrice - $ourPrice, 2) : null;
            $leadPercent    = ($nextPrice !== null && $nextPrice > 0)
                ? round((($nextPrice - $ourPrice) / $nextPrice) * 100, 2)
                : null;

            $product->next_competitor_store = $nextCompetitor->store_name ?? null;
            $product->next_competitor_price = $nextPrice;
            $product->lead_euro             = $leadEuro;
            $product->lead_percent          = $leadPercent;

            return $product;
        })->sortByDesc(function ($product) {
            return (float) ($product->lead_euro ?? 0) * 100
                + (int) ($product->pazaruvaj_offers_count ?? 0) * 10;
        })->values();

        $notTopPages = max(1, (int) ceil($notTopAll->count() / $perPage));
        $topPages    = max(1, (int) ceil($topAll->count() / $perPage));
        $totalPages  = max($notTopPages, $topPages);

        if ($page > $totalPages) {
            $page = 1;
        }

        $notTop = $notTopAll->slice(($page - 1) * $perPage, $perPage)->values()->map(function ($product) {
            return [
                'id'           => $product->id,
                'name'         => $product->name,
                'our_price'    => $product->our_price !== null ? number_format((float) $product->our_price, 2) : '—',
                'lowest_price' => $product->pazaruvaj_lowest_price !== null ? number_format((float) $product->pazaruvaj_lowest_price, 2) : '—',
                'lowest_store' => $product->pazaruvaj_lowest_store ?? '—',
                'position'     => $product->pazaruvaj_our_position,
                'diff_amount'  => $product->difference_amount !== null ? number_format((float) $product->difference_amount, 2) : '—',
                'diff_percent' => $product->difference_percent !== null ? number_format((float) $product->difference_percent, 1) : '—',
                'offers_count' => $product->pazaruvaj_offers_count ?? 0,
            ];
        })->values();

        $top = $topAll->slice(($page - 1) * $perPage, $perPage)->values()->map(function ($product) {
            return [
                'id'                    => $product->id,
                'name'                  => $product->name,
                'our_price'             => $product->our_price !== null ? number_format((float) $product->our_price, 2) : '—',
                'lowest_price'          => $product->pazaruvaj_lowest_price !== null ? number_format((float) $product->pazaruvaj_lowest_price, 2) : '—',
                'position'              => $product->pazaruvaj_our_position,
                'offers_count'          => $product->pazaruvaj_offers_count ?? 0,
                'next_competitor_store' => $product->next_competitor_store ?? '—',
                'next_competitor_price' => $product->next_competitor_price !== null ? number_format((float) $product->next_competitor_price, 2) : '—',
                'lead_euro'             => $product->lead_euro !== null ? number_format((float) $product->lead_euro, 2) : '—',
                'lead_percent'          => $product->lead_percent !== null ? number_format((float) $product->lead_percent, 1) : '—',
            ];
        })->values();

        return response()->json([
            'updated_at'    => now()->format('d.m.Y H:i:s'),
            'page'          => $page,
            'per_page'      => $perPage,
            'total_pages'   => $totalPages,
            'not_top_total' => $notTopAll->count(),
            'top_total'     => $topAll->count(),
            'not_top'       => $notTop,
            'top'           => $top,
        ]);
    }
}