<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $products = Product::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('ean', 'like', "%{$search}%")
                      ->orWhere('brand', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('products.index', compact('products', 'search'));
    }

    public function create()
    {
        return view('products.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'ean' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'our_price' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        Product::create($request->only([
            'name',
            'sku',
            'ean',
            'brand',
            'our_price',
            'is_active',
        ]));

        return redirect()->route('products.index')
            ->with('success', 'Продуктът беше създаден успешно.');
    }

    public function show(Product $product)
    {
        $product->load(['competitorLinks.store']);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'ean' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'our_price' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        $product->update($request->only([
            'name',
            'sku',
            'ean',
            'brand',
            'our_price',
            'is_active',
        ]));

        return redirect()->route('products.index')
            ->with('success', 'Продуктът беше редактиран успешно.');
    }

    public function destroy(Product $product)
    {
        $product->competitorLinks()->delete();
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Продуктът беше изтрит успешно.');
    }
}