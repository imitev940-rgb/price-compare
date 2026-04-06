<?php

namespace App\Http\Controllers;

use App\Jobs\AutoSearchProductLinkJob;
use App\Models\CompetitorLink;
use App\Models\Product;
use App\Models\Store;
use App\Services\AutoProductSearchService;
use Illuminate\Http\Request;

class CompetitorLinkController extends Controller
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

        $search      = trim((string) $request->get('search', ''));
        $storeFilter = $request->get('store_id');
        $status      = $request->get('status', 'all');

        $query = CompetitorLink::with(['product:id,name,sku,brand', 'store:id,name'])
            ->latest();

        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }

        if ($storeFilter) {
            $query->where('store_id', $storeFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('product_url', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($pq) => $pq
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('ean', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                    )
                    ->orWhereHas('store', fn ($sq) => $sq
                        ->where('name', 'like', "%{$search}%")
                    );
            });
        }

        $links  = $query->paginate($perPage)->withQueryString();
        $stores = Store::orderBy('name')->get(['id', 'name']);

        return view('links.index', compact('links', 'search', 'perPage', 'stores', 'storeFilter', 'status'));
    }

    // ================================================================
    // CREATE / STORE
    // ================================================================

    public function create()
    {
        $stores   = Store::orderBy('name')->get(['id', 'name']);
        $products = Product::orderBy('name')->get(['id', 'name']);

        return view('links.create', compact('stores', 'products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id'  => 'required|exists:products,id',
            'store_id'    => 'required|exists:stores,id',
            'product_url' => 'required|url|max:1000',
            'last_price'  => 'nullable|numeric|min:0',
            'is_active'   => 'boolean',
        ]);

        $link = CompetitorLink::create([
            'product_id'  => $validated['product_id'],
            'store_id'    => $validated['store_id'],
            'product_url' => $validated['product_url'],
            'last_price'  => $validated['last_price'] ?? null,
            'is_active'   => $validated['is_active'] ?? true,
        ]);

        return redirect()
            ->route('links.index')
            ->with('success', 'Линкът беше добавен успешно. (ID: ' . $link->id . ')');
    }

    // ================================================================
    // EDIT / UPDATE
    // ================================================================

public function edit(CompetitorLink $link)
    {
        $stores   = Store::orderBy('name')->get(['id', 'name']);
        $products = Product::orderBy('name')->get(['id', 'name', 'sku']);

        return view('links.edit', compact('link', 'stores', 'products'));
    }
    public function update(Request $request, CompetitorLink $link)
    {
        $validated = $request->validate([
            'product_id'  => 'required|exists:products,id',
            'store_id'    => 'required|exists:stores,id',
            'product_url' => 'required|url|max:1000',
            'last_price'  => 'nullable|numeric|min:0',
            'is_active'   => 'boolean',
        ]);

        $link->update([
            'product_id'  => $validated['product_id'],
            'store_id'    => $validated['store_id'],
            'product_url' => $validated['product_url'],
            'last_price'  => $validated['last_price'] ?? null,
            'is_active'   => $validated['is_active'] ?? $link->is_active,
        ]);

        return redirect()
            ->route('links.index')
            ->with('success', 'Линкът беше обновен успешно.');
    }

    // ================================================================
    // DESTROY
    // ================================================================

    public function destroy(CompetitorLink $link)
    {
        $link->delete();

        return redirect()
            ->route('links.index')
            ->with('success', 'Линкът беше изтрит.');
    }

    // ================================================================
    // AUTO SEARCH
    // ================================================================

    public function autoSearch(Request $request, Product $product)
    {
        $onlyStore = $request->get('store');

        if (class_exists(AutoSearchProductLinkJob::class)) {
            dispatch(new AutoSearchProductLinkJob($product->id, true, $onlyStore));

            return back()->with('success', 'Търсенето е пуснато на опашката за "' . $product->name . '".');
        }

        try {
            app(AutoProductSearchService::class)->handle($product, true, $onlyStore);

            return back()->with('success', 'Линковете за "' . $product->name . '" бяха обновени.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Грешка: ' . $e->getMessage());
        }
    }

    // ================================================================
    // TOGGLE ACTIVE (AJAX)
    // ================================================================

    public function toggleActive(CompetitorLink $link)
    {
        $link->update(['is_active' => ! $link->is_active]);

        return response()->json([
            'is_active' => $link->is_active,
            'message'   => $link->is_active ? 'Линкът е активиран.' : 'Линкът е деактивиран.',
        ]);
    }

    // ================================================================
    // PRODUCT SEARCH API (Select2)
    // ================================================================

    public function searchProducts(Request $request)
    {
        $search = trim((string) $request->get('q', ''));

        $products = Product::query()
            ->when($search !== '', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")
            )
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'sku', 'brand'])
            ->map(fn ($p) => [
                'id'   => $p->id,
                'text' => $p->brand . ' ' . $p->name . ($p->sku ? ' (' . $p->sku . ')' : ''),
            ]);

        return response()->json(['results' => $products]);
    }
}