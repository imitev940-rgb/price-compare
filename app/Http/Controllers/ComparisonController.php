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

        $allowedPerPage = [10, 25, 50, 100];
        $perPage = (int) $request->get('per_page', 10);

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $allProducts = $this->buildProcessedProductsCollection($search, $sort, $status);

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

        // Best Price Wins (#1)
        $bestPriceWins = $allProducts->filter(function ($product) {
            return ($product->pazaruvaj_offers_count ?? 0) > 0
                && $product->pazaruvaj_our_position !== null
                && (int) $product->pazaruvaj_our_position === 1;
        })->count();

        // ❗ НОВО: Not #1 (тук беше проблема)
        $notBestPriceCount = $allProducts->filter(function ($product) {
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
            'notBestPriceCount' => $notBestPriceCount, // 🔥 важно
            'search' => $search,
            'sort' => $sort,
            'status' => $status,
            'perPage' => $perPage,
        ]);
    }

    public function exportCsv(Request $request)
    {
        $products = $this->buildProcessedProductsCollection(
            trim((string) $request->get('search', '')),
            $request->get('sort'),
            $request->get('status', 'all')
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
            $request->get('status', 'all')
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
            $request->get('status', 'all')
        );

        $pdf = Pdf::loadView('comparison.pdf', [
            'products' => $products,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('comparison-export-' . now()->format('Y-m-d_H-i-s') . '.pdf');
    }

    private function buildProcessedProductsCollection(string $search = '', ?string $sort = null, string $status = 'all')
    {
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

            $technopolis = $linksByStore['technopolis'] ?? null;
            $technomarket = $linksByStore['technomarket'] ?? null;
            $zora = $linksByStore['zora'] ?? null;
            $pazaruvaj = $linksByStore['pazaruvaj'] ?? null;

            $technopolisPrice = $this->normalizePrice($technopolis?->last_price);
            $technomarketPrice = $this->normalizePrice($technomarket?->last_price);
            $zoraPrice = $this->normalizePrice($zora?->last_price);
            $ourPrice = $this->normalizePrice($product->our_price);

            $directCompetitorPrices = collect([
                $technopolisPrice,
                $technomarketPrice,
                $zoraPrice,
            ])->filter(fn ($price) => $price !== null && $price > 0)->values();

            $lowestDirectPrice = $directCompetitorPrices->count() > 0
                ? (float) $directCompetitorPrices->min()
                : null;

            $pazaruvajOffers = $product->pazaruvajOffers
                ->filter(fn ($offer) => $offer->price !== null && (float) $offer->price > 0)
                ->sortBy(fn ($offer) => $offer->position ?? PHP_INT_MAX)
                ->values();

            $pazaruvajLowest = $pazaruvajOffers->count() > 0
                ? (float) $pazaruvajOffers->min('price')
                : null;

            $pazaruvajOffersCount = $pazaruvajOffers->count();

            $ourPosition = null;

            if ($ourPrice !== null && $ourPrice > 0 && $pazaruvajOffersCount > 0) {
                foreach ($pazaruvajOffers as $offer) {
                    if ($offer->price !== null && (float) $ourPrice <= (float) $offer->price) {
                        $ourPosition = (int) ($offer->position ?? 0);
                        break;
                    }
                }

                if ($ourPosition === null) {
                    $ourPosition = $pazaruvajOffersCount + 1;
                }
            }

            $allMarketPrices = collect([
                $pazaruvajLowest,
                $technopolisPrice,
                $technomarketPrice,
                $zoraPrice,
            ])->filter(fn ($price) => $price !== null && $price > 0);

            $lowestMarketPrice = $allMarketPrices->count() > 0
                ? (float) $allMarketPrices->min()
                : null;

            $differenceAmount = null;
            $differencePercent = null;

            if ($ourPrice !== null && $ourPrice > 0 && $pazaruvajLowest !== null && $pazaruvajLowest > 0) {
                $differenceAmount = round($ourPrice - $pazaruvajLowest, 2);
                $differencePercent = round((($ourPrice - $pazaruvajLowest) / $pazaruvajLowest) * 100, 2);
            }

            $product->technopolis_price = $technopolisPrice;
            $product->technomarket_price = $technomarketPrice;
            $product->zora_price = $zoraPrice;

            $product->lowest_market_price = $lowestMarketPrice;

            $product->pazaruvaj_lowest_price = $pazaruvajLowest;
            $product->pazaruvaj_offers_count = $pazaruvajOffersCount;
            $product->pazaruvaj_our_position = $ourPosition;
            $product->pazaruvaj_offers_list = $pazaruvajOffers;

            $product->offers_count = $pazaruvajOffersCount > 0
                ? $pazaruvajOffersCount
                : $directCompetitorPrices->count();

            $product->difference_amount = $differenceAmount;
            $product->difference_percent = $differencePercent;

            return $product;
        });

        return $this->sortProductsCollection($products, $sort);
    }

    private function sortProductsCollection($products, ?string $sort)
    {
        return match ($sort) {
            'name_asc' => $products->sortBy(fn ($p) => mb_strtolower((string) $p->name))->values(),
            'name_desc' => $products->sortByDesc(fn ($p) => mb_strtolower((string) $p->name))->values(),
            'price_asc' => $products->sortBy(fn ($p) => $p->our_price ?? PHP_FLOAT_MAX)->values(),
            'price_desc' => $products->sortByDesc(fn ($p) => $p->our_price ?? -1)->values(),
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
}