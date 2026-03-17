<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CompetitorLink;
use App\Models\Product;
use App\Models\Store;

class CompetitorLinkController extends Controller
{
    public function index()
    {
        $links = CompetitorLink::with(['product','store'])
            ->latest()
            ->paginate(15);

        return view('links.index', compact('links'));
    }

    public function create()
    {
        $products = Product::orderBy('name')->get();
        $stores = Store::orderBy('name')->get();

        return view('links.create', compact('products','stores'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'store_id' => 'required|exists:stores,id',
            'product_url' => 'required|string|max:1000',
            'last_price' => 'nullable|numeric',
        ]);

        CompetitorLink::create([
            'product_id' => $request->product_id,
            'store_id' => $request->store_id,
            'product_url' => $request->product_url,
            'last_price' => $request->last_price,
        ]);

        return redirect()
            ->route('links.index')
            ->with('success','Competitor link created successfully.');
    }

    public function edit(CompetitorLink $link)
    {
        $products = Product::orderBy('name')->get();
        $stores = Store::orderBy('name')->get();

        return view('links.edit', compact('link','products','stores'));
    }

    public function update(Request $request, CompetitorLink $link)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'store_id' => 'required|exists:stores,id',
            'product_url' => 'required|string|max:1000',
            'last_price' => 'nullable|numeric',
        ]);

        $link->update([
            'product_id' => $request->product_id,
            'store_id' => $request->store_id,
            'product_url' => $request->product_url,
            'last_price' => $request->last_price,
        ]);

        return redirect()
            ->route('links.index')
            ->with('success','Competitor link updated successfully.');
    }

    public function destroy(CompetitorLink $link)
    {
        $link->delete();

        return redirect()
            ->route('links.index')
            ->with('success','Competitor link deleted successfully.');
    }
}