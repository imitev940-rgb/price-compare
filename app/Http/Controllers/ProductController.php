<?php

namespace App\Http\Controllers;

use App\Jobs\AutoSearchProductJob;
use App\Models\Product;
use App\Services\OwnProductPriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    // ================================================================
    // INDEX
    // ================================================================

    public function index(Request $request)
    {
        $allowedPerPage = [10, 25, 50, 100];
        $perPage = (int) $request->get('per_page', 10);
        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $search = trim((string) $request->get('search', ''));
        $status = $request->get('status', 'all');

        $products = Product::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('ean', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")
            ))
            ->when($status === 'active',   fn ($q) => $q->where('is_active', 1))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', 0))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('products.index', compact('products', 'search', 'perPage', 'status'));
    }

    // ================================================================
    // CREATE / STORE
    // ================================================================

    public function create()
    {
        return view('products.create');
    }

    public function store(Request $request, OwnProductPriceService $ownProductPriceService)
    {
        $validated = $this->validateProduct($request);

        if (! empty($validated['product_url'])) {
            $fetchedPrice = $ownProductPriceService->getPrice($validated['product_url']);
            if ($fetchedPrice !== null) {
                $validated['our_price'] = round((float) $fetchedPrice, 2);
            }
        }

        if (empty($validated['our_price'])) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Не можа да се вземе цена от линка. Въведи цената ръчно.');
        }

        $product = Product::create($validated);
        $this->dispatchJobs($product, 'create');

        return redirect()
            ->route('products.index')
            ->with('success', 'Продуктът беше създаден успешно.');
    }

    // ================================================================
    // SHOW
    // ================================================================

    public function show(Product $product)
    {
        $product->load(['competitorLinks.store:id,name']);
        return view('products.show', compact('product'));
    }

    // ================================================================
    // EDIT / UPDATE
    // ================================================================

    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product, OwnProductPriceService $ownProductPriceService)
    {
        $validated = $this->validateProduct($request);

        if (! empty($validated['product_url'])) {
            $fetchedPrice = $ownProductPriceService->getPrice($validated['product_url']);
            if ($fetchedPrice !== null) {
                $validated['our_price'] = round((float) $fetchedPrice, 2);
            }
        }

        if (empty($validated['our_price'])) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Не можа да се вземе цена от линка. Въведи цената ръчно.');
        }

        $product->update($validated);
        $this->dispatchJobs($product, 'update');

        return redirect()
            ->route('products.index')
            ->with('success', 'Продуктът беше редактиран успешно.');
    }

    // ================================================================
    // DESTROY
    // ================================================================

    public function destroy(Product $product)
    {
        $product->competitorLinks()->delete();
        $product->priceHistories()->delete();
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', 'Продуктът беше изтрит успешно.');
    }

    // ================================================================
    // FETCH PRICE (AJAX)
    // ================================================================

    public function fetchPriceAjax(Request $request, OwnProductPriceService $ownProductPriceService)
    {
        $request->validate(['product_url' => 'required|url']);

        $price = $ownProductPriceService->getPrice($request->product_url);

        if ($price === null) {
            return response()->json([
                'success' => false,
                'message' => 'Не успях да прочета цена от линка.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'price'   => number_format((float) $price, 2, '.', ''),
        ]);
    }

    // ================================================================
    // HELPERS
    // ================================================================

    private function validateProduct(Request $request): array
    {
        return $request->validate([
            'name'          => 'required|string|max:255',
            'brand'         => 'nullable|string|max:255',
            'sku'           => 'nullable|string|max:255',
            'ean'           => 'nullable|string|max:255',
            'model'         => 'nullable|string|max:255',
            'product_url'   => 'nullable|url',
            'our_price'     => 'nullable|numeric|min:0',
            'is_active'     => 'required|boolean',
            'scan_priority' => 'nullable|in:normal,top',
        ]);
    }

    private function dispatchJobs(Product $product, string $action): void
    {
        try {
            // PriceCheckProductJob се пуска от AutoSearchProductJob
            // след като всички линкове са намерени (включително Pazaruvaj)
            AutoSearchProductJob::dispatch($product->id)
                ->onQueue('search');

        } catch (\Throwable $e) {
            Log::error('Job dispatch failed after product ' . $action, [
                'product_id' => $product->id,
                'message'    => $e->getMessage(),
            ]);
        }
    }
}