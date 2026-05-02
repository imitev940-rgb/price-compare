<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PriceChangeSnapshot;
use App\Models\PazaruvajOffer;
use App\Exports\PriceChangesExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class PriceControlController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $perPage = (int) $request->input('per_page', 25);

        $query = Product::query()
            ->where('is_active', 1)
            ->where(function ($q) {
                $q->whereNotNull('new_price')
                  ->orWhereNotNull('discount_percent')
                  ->orWhereNotNull('delivery_price');
            })
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        $products = $query->paginate($perPage)->withQueryString();

        $productIds = $products->pluck('id')->toArray();
        $lowestData = $this->getLowestPrices($productIds);

        foreach ($products as $product) {
            $info = $lowestData[$product->id] ?? null;
            $product->lowest_price = $info['price'] ?? null;
            $product->lowest_store = $info['store'] ?? null;
        }

        return view('price-control.index', [
            'products' => $products,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function updateProduct(Request $request, Product $product)
    {
        $data = $request->validate([
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'delivery_price' => 'nullable|numeric|min:0',
            'new_price' => 'nullable|numeric|min:0',
        ]);

        $product->fill($data);
        if (array_key_exists('new_price', $data) && $data['new_price'] !== null) {
            $product->new_price_updated_at = now();
        }
        $product->save();

        return response()->json([
            'success' => true,
            'product' => [
                'id' => $product->id,
                'discount_percent' => $product->discount_percent,
                'delivery_price' => $product->delivery_price,
                'new_price' => $product->new_price,
            ],
        ]);
    }

    public function save(Request $request)
    {
        $changes = $request->input('changes', []);
        if (empty($changes)) {
            return response()->json(['error' => 'No changes provided'], 422);
        }

        $snapshotData = [];
        $count = 0;

        DB::transaction(function () use ($changes, &$snapshotData, &$count) {
            foreach ($changes as $change) {
                $productId = (int) ($change['product_id'] ?? 0);
                if (!$productId) continue;

                $product = Product::find($productId);
                if (!$product) continue;

                $oldPrice = $product->our_price;
                $newPrice = isset($change['new_price']) ? (float) $change['new_price'] : null;
                $discount = isset($change['discount_percent']) ? (float) $change['discount_percent'] : null;
                $delivery = isset($change['delivery_price']) ? (float) $change['delivery_price'] : null;

                $lowest = $this->getLowestPrices([$productId])[$productId] ?? null;

                $snapshotData[] = [
                    'product_id' => $productId,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'discount_percent' => $discount,
                    'delivery_price' => $delivery,
                    'lowest_price' => $lowest['price'] ?? null,
                    'lowest_store' => $lowest['store'] ?? null,
                ];

                if ($discount !== null) $product->discount_percent = $discount;
                if ($delivery !== null) $product->delivery_price = $delivery;
                if ($newPrice !== null) {
                    $product->new_price = $newPrice;
                    $product->new_price_updated_at = now();
                }
                $product->save();
                $count++;
            }

            PriceChangeSnapshot::create([
                'user_id' => Auth::id(),
                'product_count' => $count,
                'data' => $snapshotData,
            ]);
        });

        return response()->json([
            'success' => true,
            'count' => $count,
            'message' => "Запазени {$count} промени",
        ]);
    }

    public function history()
    {
        $snapshots = PriceChangeSnapshot::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('price-control.history', [
            'snapshots' => $snapshots,
        ]);
    }

    public function downloadSnapshot($id)
    {
        $snapshot = PriceChangeSnapshot::findOrFail($id);
        $filename = 'price-changes-' . $snapshot->created_at->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new PriceChangesExport($snapshot), $filename);
    }

    /**
     * За даден списък от product IDs връща lowest price + store за всеки.
     */
    protected function getLowestPrices(array $productIds): array
    {
        if (empty($productIds)) return [];

        $offers = PazaruvajOffer::whereIn('product_id', $productIds)
            ->where('price', '>', 0)
            ->orderBy('price', 'asc')
            ->get(['product_id', 'price', 'store_name']);

        $result = [];
        foreach ($offers as $offer) {
            if (!isset($result[$offer->product_id])) {
                $result[$offer->product_id] = [
                    'price' => (float) $offer->price,
                    'store' => $offer->store_name,
                ];
            }
        }
        return $result;
    }
}
