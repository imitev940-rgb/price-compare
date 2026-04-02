<?php

namespace App\Http\Controllers;

use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;

class PriceHistoryController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $productId = trim((string) $request->get('product_id', ''));
        $storeId = trim((string) $request->get('store_id', ''));
        $status = trim((string) $request->get('status', ''));
        $dateFrom = trim((string) $request->get('date_from', ''));
        $dateTo = trim((string) $request->get('date_to', ''));

        $query = PriceHistory::with(['product', 'store', 'competitorLink']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', function ($productQuery) use ($search) {
                    $productQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('ean', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%");
                })->orWhereHas('store', function ($storeQuery) use ($search) {
                    $storeQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('base_url', 'like', "%{$search}%");
                });
            });
        }

        if ($productId !== '' && ctype_digit($productId)) {
            $query->where('product_id', (int) $productId);
        }

        if ($storeId !== '' && ctype_digit($storeId)) {
            $query->where('store_id', (int) $storeId);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($dateFrom !== '') {
            $query->whereDate('checked_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('checked_at', '<=', $dateTo);
        }

        $histories = $query
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $products = Product::orderBy('name')->get(['id', 'name']);
        $stores = Store::orderBy('name')->get(['id', 'name']);

        $statuses = [
            'Cheaper',
            'More Expensive',
            'Match',
        ];

        return view('price-history.index', compact(
            'histories',
            'products',
            'stores',
            'statuses',
            'search',
            'productId',
            'storeId',
            'status',
            'dateFrom',
            'dateTo'
        ));
    }
}