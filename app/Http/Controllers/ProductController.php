<?php

namespace App\Http\Controllers;

use App\Jobs\AutoSearchProductJob;
use App\Jobs\PriceCheckProductJob;
use App\Models\Product;
use App\Services\OwnProductPriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $allowedPerPage = [10, 25, 50, 100];
        $perPage = (int) $request->get('per_page', 10);

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $search = trim((string) $request->get('search', ''));

        $products = Product::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('ean', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('products.index', compact('products', 'search', 'perPage'));
    }

    public function create()
    {
        return view('products.create');
    }

    public function store(Request $request, OwnProductPriceService $ownProductPriceService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'ean' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'product_url' => 'nullable|url',
            'our_price' => 'nullable|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        if (!empty($validated['product_url'])) {
            $price = $ownProductPriceService->getPrice($validated['product_url']);

            if ($price !== null) {
                $validated['our_price'] = round((float) $price, 2);
            }
        }

        if (!isset($validated['our_price']) || $validated['our_price'] === null || $validated['our_price'] === '') {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Не можа да се вземе цена от линка. Моля провери Product URL.');
        }

        $product = Product::create($validated);

        try {
            AutoSearchProductJob::dispatch($product->id)
                ->onQueue('search');

            PriceCheckProductJob::dispatch($product->id)
                ->delay(now()->addMinutes(2))
                ->onQueue('price');
        } catch (\Throwable $e) {
            Log::error('Auto search / price check dispatch failed after create', [
                'product_id' => $product->id,
                'message' => $e->getMessage(),
            ]);
        }

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

    public function update(Request $request, Product $product, OwnProductPriceService $ownProductPriceService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'ean' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'product_url' => 'nullable|url',
            'our_price' => 'nullable|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        if (!empty($validated['product_url'])) {
            $price = $ownProductPriceService->getPrice($validated['product_url']);

            if ($price !== null) {
                $validated['our_price'] = round((float) $price, 2);
            }
        }

        if (!isset($validated['our_price']) || $validated['our_price'] === null || $validated['our_price'] === '') {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Не можа да се вземе цена от линка. Моля провери Product URL.');
        }

        $product->update($validated);

        try {
            AutoSearchProductJob::dispatch($product->id)
                ->onQueue('search');

            PriceCheckProductJob::dispatch($product->id)
                ->delay(now()->addMinutes(2))
                ->onQueue('price');
        } catch (\Throwable $e) {
            Log::error('Auto search / price check dispatch failed after update', [
                'product_id' => $product->id,
                'message' => $e->getMessage(),
            ]);
        }

        return redirect()->route('products.index')
            ->with('success', 'Продуктът беше редактиран успешно.');
    }

    public function fetchPriceAjax(Request $request, OwnProductPriceService $ownProductPriceService)
    {
        $request->validate([
            'product_url' => 'required|url',
        ]);

        $price = $ownProductPriceService->getPrice($request->product_url);

        if ($price === null) {
            return response()->json([
                'success' => false,
                'message' => 'Не успях да прочета цена от линка.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'price' => number_format((float) $price, 2, '.', ''),
        ]);
    }

    public function autoSearch(Product $product)
    {
        return redirect()->route('products.show', $product)
            ->with('error', 'Auto Search е преместен към queue job / Artisan command.');
    }

    public function destroy(Product $product)
    {
        $product->competitorLinks()->delete();
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Продуктът беше изтрит успешно.');
    }
}