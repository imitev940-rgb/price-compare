<?php

namespace App\Http\Controllers;

use App\Models\PriceHistory;
use App\Models\Product;
use Illuminate\Http\Request;

class PriceHistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = PriceHistory::with(['product', 'store', 'competitorLink']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $histories = $query
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $products = Product::orderBy('name')->get();

        return view('price-history.index', compact('histories', 'products'));
    }
}