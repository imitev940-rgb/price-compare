<?php

namespace App\Http\Controllers;

use App\Models\PriceHistory;
use App\Models\Product;
use Illuminate\Http\Request;

class PriceHistoryController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

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

        $histories = $query
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $products = Product::orderBy('name')->get();

        return view('price-history.index', compact('histories', 'products', 'search'));
    }
}