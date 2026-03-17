<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Store;
use App\Models\CompetitorLink;

class ComparisonController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $sort = $request->get('sort');

        $productsQuery = Product::with(['competitorLinks.store']);

        if ($search) {
            $productsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('ean', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        if ($sort === 'name_asc') {
            $productsQuery->orderBy('name', 'asc');
        } elseif ($sort === 'name_desc') {
            $productsQuery->orderBy('name', 'desc');
        } elseif ($sort === 'price_asc') {
            $productsQuery->orderBy('our_price', 'asc');
        } elseif ($sort === 'price_desc') {
            $productsQuery->orderBy('our_price', 'desc');
        } else {
            $productsQuery->latest();
        }

        $products = $productsQuery->get();

        $productsCount = Product::count();
        $storesCount = Store::count();
        $linksCount = CompetitorLink::count();

        $bestPriceWins = 0;

        foreach ($products as $product) {
            foreach ($product->competitorLinks as $link) {
                if ($link->last_price !== null && $product->our_price < $link->last_price) {
                    $bestPriceWins++;
                }
            }
        }

        return view('comparison.index', [
            'products' => $products,
            'productsCount' => $productsCount,
            'storesCount' => $storesCount,
            'linksCount' => $linksCount,
            'bestPriceWins' => $bestPriceWins,
        ]);
    }
}