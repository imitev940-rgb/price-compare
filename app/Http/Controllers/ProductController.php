<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
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

    public function store(Request $request)
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
            $price = $this->fetchOwnPriceFromUrl($validated['product_url']);

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
            Artisan::call('products:auto-search', [
                'product_id' => $product->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Auto search failed after create', [
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

    public function update(Request $request, Product $product)
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
            $price = $this->fetchOwnPriceFromUrl($validated['product_url']);

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
            Artisan::call('products:auto-search', [
                'product_id' => $product->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Auto search failed after update', [
                'product_id' => $product->id,
                'message' => $e->getMessage(),
            ]);
        }

        return redirect()->route('products.index')
            ->with('success', 'Продуктът беше редактиран успешно.');
    }

    public function fetchPriceAjax(Request $request)
    {
        $request->validate([
            'product_url' => 'required|url',
        ]);

        $price = $this->fetchOwnPriceFromUrl($request->product_url);

        if ($price === null) {
            return response()->json([
                'success' => false,
                'message' => 'Не успях да прочета цена от линка.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'price' => sprintf('%.2f', $price),
        ]);
    }

    public function autoSearch(Product $product)
    {
        return redirect()->route('products.show', $product)
            ->with('error', 'Auto Search е преместен към Artisan command. Пусни: php artisan products:auto-search ' . $product->id);
    }

    public function destroy(Product $product)
    {
        $product->competitorLinks()->delete();
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Продуктът беше изтрит успешно.');
    }

    private function fetchOwnPriceFromUrl(string $url): ?float
    {
        try {
            $response = Http::timeout(20)
                ->connectTimeout(8)
                ->retry(2, 700)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Accept-Language' => 'bg-BG,bg;q=0.9,en;q=0.8',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                    'Referer' => 'https://technika.bg/',
                ])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $html = mb_substr($response->body(), 0, 800000);

            if (str_contains(strtolower($url), 'technika.bg')) {
                return $this->extractTechnikaPrice($html);
            }

            return $this->extractGenericPrice($html);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function extractTechnikaPrice(string $html): ?float
    {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match('/Цена на продукта:\s*€\s*([0-9\.,\^\{\}\s]+)/u', $decoded, $m)) {
            $value = $this->normalizeTechnikaEuro($m[1]);
            if ($value !== null) {
                return $value;
            }
        }

        if (preg_match('/Цена:(.*?)ПЦД:/isu', $decoded, $block)) {
            $priceBlock = $block[1];

            if (preg_match('/([0-9\.,\^\{\}\s]+)\s*EUR\b/u', $priceBlock, $m)) {
                $value = $this->normalizeTechnikaEuro($m[1]);
                if ($value !== null) {
                    return $value;
                }
            }

            if (preg_match('/€\s*([0-9\.,\^\{\}\s]+)/u', $priceBlock, $m)) {
                $value = $this->normalizeTechnikaEuro($m[1]);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        $plain = strip_tags($decoded);
        $plain = preg_replace('/\s+/u', ' ', $plain);
        $plain = trim($plain);

        if (preg_match('/Цена на продукта:\s*€\s*([0-9\.,\s]+)/u', $plain, $m)) {
            $value = $this->normalizeTechnikaEuro($m[1]);
            if ($value !== null) {
                return $value;
            }
        }

        if (preg_match('/Цена:\s*€\s*([0-9\.,\s]+)/u', $plain, $m)) {
            $value = $this->normalizeTechnikaEuro($m[1]);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function extractGenericPrice(string $html): ?float
    {
        $patterns = [
            '/<meta[^>]+property=["\']product:price:amount["\'][^>]+content=["\']([^"\']+)["\']/iu',
            '/<meta[^>]+itemprop=["\']price["\'][^>]+content=["\']([^"\']+)["\']/iu',
            '/<meta[^>]+name=["\']price["\'][^>]+content=["\']([^"\']+)["\']/iu',
            '/"price"\s*:\s*"([^"]+)"/iu',
            '/"price"\s*:\s*([0-9\.,]+)/iu',
            '/([0-9]{1,6}[.,][0-9]{2})\s*(€|EUR|лв\.?|BGN)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $price = $this->normalizePriceWithCurrency($matches[1], $matches[2] ?? null);

                if ($price !== null && $price > 0) {
                    return $price;
                }
            }
        }

        return null;
    }

    private function normalizeTechnikaEuro(string $raw): ?float
    {
        $price = trim($raw);
        $price = str_replace(['^{', '}'], ['.', ''], $price);
        $price = preg_replace('/\s+/u', '', $price);
        $price = preg_replace('/[^\d\.,]/u', '', $price);

        if ($price === '') {
            return null;
        }

        if (str_contains($price, ',') && str_contains($price, '.')) {
            $lastComma = strrpos($price, ',');
            $lastDot = strrpos($price, '.');

            if ($lastComma < $lastDot) {
                $price = str_replace(',', '', $price);
            } else {
                $price = str_replace('.', '', $price);
                $price = str_replace(',', '.', $price);
            }
        } elseif (str_contains($price, ',')) {
            $parts = explode(',', $price);

            if (count($parts) > 1 && strlen(end($parts)) === 2) {
                $price = str_replace(',', '.', $price);
            } else {
                $price = str_replace(',', '', $price);
            }
        }

        $price = preg_replace('/[^\d\.]/u', '', $price);

        if ($price === '' || !is_numeric($price)) {
            return null;
        }

        $value = (float) $price;

        if ($value <= 0 || $value > 99999.99) {
            return null;
        }

        return round($value, 2);
    }

    private function normalizePriceWithCurrency(string $rawPrice, ?string $currency = null): ?float
    {
        $original = trim($rawPrice);

        if ($original === '') {
            return null;
        }

        $price = html_entity_decode($original, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $price = strip_tags($price);
        $price = str_replace(["\xc2\xa0", ' '], '', $price);

        $currencyText = mb_strtolower((string) $currency . ' ' . $original);
        $isBGN = str_contains($currencyText, 'лв') || str_contains($currencyText, 'bgn');
        $isEUR = str_contains($currencyText, '€') || str_contains($currencyText, 'eur');

        if (str_contains($price, ',') && str_contains($price, '.')) {
            $lastComma = strrpos($price, ',');
            $lastDot = strrpos($price, '.');

            if ($lastComma > $lastDot) {
                $price = str_replace('.', '', $price);
                $price = str_replace(',', '.', $price);
            } else {
                $price = str_replace(',', '', $price);
            }
        } elseif (str_contains($price, ',')) {
            $parts = explode(',', $price);

            if (count($parts) > 1 && strlen(end($parts)) === 2) {
                $price = str_replace(',', '.', $price);
            } else {
                $price = str_replace(',', '', $price);
            }
        }

        $price = preg_replace('/[^\d\.]/', '', $price);

        if ($price === '' || !is_numeric($price)) {
            return null;
        }

        $price = (float) $price;

        if ($isBGN && !$isEUR) {
            $price = $price / 1.95583;
        }

        return round($price, 2);
    }
}