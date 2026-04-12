<?php

namespace App\Console\Commands;

use App\Models\CompetitorLink;
use App\Models\Notification;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Services\OwnProductPriceService;
use App\Services\PazaruvajScraperService;
use App\Services\PriceExtractorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckCompetitorPrices extends Command
{
    protected $signature = 'prices:check
                            {product_id?   : ID на конкретен продукт}
                            {--link_id=    : ID на конкретен линк}
                            {--store=      : Само за конкретен магазин (напр. Techmart)}';

    protected $description = 'Check competitor prices';

    private string $scriptPath;

    private const PLAYWRIGHT_STORES = ['techmart', 'technopolis', 'technomarket', 'tehnomix', 'zora'];

    public function __construct()
    {
        parent::__construct();
        $this->scriptPath = base_path('scripts/scrape-price.js');
    }

    // ================================================================
    // HANDLE (Artisan команда)
    // ================================================================

    public function handle(): int
    {
        Log::info('Prices check started');

        $productId   = $this->argument('product_id');
        $linkId      = $this->option('link_id');
        $storeFilter = $this->option('store')
            ? mb_strtolower(trim($this->option('store')))
            : null;

        $extractor        = app(PriceExtractorService::class);
        $pazaruvajScraper = app(PazaruvajScraperService::class);
        $ownPriceService  = app(OwnProductPriceService::class);

        // Обновяване на собствени цени
        if ($linkId) {
            $linkProductId = CompetitorLink::find($linkId)?->product_id;
            if ($linkProductId) {
                $this->updateOwnProductPrices((int) $linkProductId, $ownPriceService);
            }
        } else {
            $this->updateOwnProductPrices($productId ? (int) $productId : null, $ownPriceService);
        }

        $query = CompetitorLink::with(['product', 'store'])->where('is_active', 1);

        if ($linkId) {
            $query->where('id', $linkId);
        } elseif ($productId) {
            $query->where('product_id', $productId);
        }

        if ($storeFilter) {
            $query->whereHas('store', fn ($q) => $q->whereRaw('LOWER(name) = ?', [$storeFilter]));
        }

        $total     = $query->count();
        $processed = 0;
        $failed    = 0;

        $this->info("Checking prices for {$total} links" . ($storeFilter ? " [store: {$storeFilter}]" : '') . '...');

        $query->chunkById(20, function ($links) use (
            $extractor, $pazaruvajScraper, $total, &$processed, &$failed
        ) {
            foreach ($links as $link) {
                $processed++;
                $this->line("[{$processed}/{$total}] {$link->store?->name} — {$link->product_url}");

                $start = microtime(true);

                try {
                    $this->handleLink($link, $extractor, $pazaruvajScraper);
                    $elapsed = round(microtime(true) - $start, 2);
                    $this->info("  ✓ Done in {$elapsed}s");
                } catch (\Throwable $e) {
                    $failed++;
                    $elapsed = round(microtime(true) - $start, 2);
                    $this->error("  ✗ Error in {$elapsed}s: " . $e->getMessage());

                    Log::error('Price check error', [
                        'url'     => $link->product_url,
                        'link_id' => $link->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->info("Finished. Processed: {$processed}, Failed: {$failed}.");
        Log::info('Prices check finished', ['processed' => $processed, 'failed' => $failed]);

        return self::SUCCESS;
    }

    // ================================================================
    // HANDLE LINK (използва се и от PriceCheckLinkJob)
    // ================================================================

    public function handleLink(
        CompetitorLink          $link,
        PriceExtractorService   $extractor,
        PazaruvajScraperService $pazaruvajScraper
    ): void {
        $url       = trim((string) $link->product_url);
        $storeName = mb_strtolower(trim((string) ($link->store->name ?? '')));

        if (empty($url) || $url === '#') {
            $link->update([
                'last_checked_at' => now(),
                'search_status'   => 'invalid_url',
                'last_error'      => 'Missing or placeholder URL.',
            ]);
            return;
        }

        // ── PAZARUVAJ (непроменен) ───────────────────────────────────────────
        if ($storeName === 'pazaruvaj') {
            $this->handlePazaruvaj($link, $pazaruvajScraper);
            return;
        }

        // ── PLAYWRIGHT магазини ──────────────────────────────────────────────
        if (in_array($storeName, self::PLAYWRIGHT_STORES, true)) {
            $result = $this->fetchPriceViaPlaywright($url);
        } else {
            // Zora и др. — HTTP
            $result = $this->fetchPriceViaHttp($url, $storeName, $extractor);
        }

        if (isset($result['blocked']) && $result['blocked']) {
            $link->update([
                'last_checked_at' => now(),
                'search_status'   => 'blocked',
                'last_error'      => 'HTTP 403/429',
            ]);
            return;
        }

        if (isset($result['error'])) {
            $link->update([
                'last_checked_at' => now(),
                'search_status'   => 'error',
                'last_error'      => $result['error'],
            ]);
            return;
        }

        $price        = $result['price']    ?? null;
        $matchedTitle = $result['title']    ?? null;
        $inStock      = $result['in_stock'] ?? null;

        // ── Out of stock проверка ────────────────────────────────────────────
        if ($inStock === false) {
            $link->update([
                'last_checked_at' => now(),
                'search_status'   => 'out_of_stock',
                'is_active'       => 0,
                'last_error'      => 'Product out of stock.',
                'matched_title'   => $matchedTitle,
            ]);

            Log::info('Link deactivated — out of stock', [
                'link_id'    => $link->id,
                'product_id' => $link->product_id,
                'store'      => $storeName,
                'url'        => $url,
            ]);
            return;
        }

        // ── Ако беше деактивиран и сега е в наличност → активирай ───────────
        if ($inStock === true && ! $link->is_active) {
            $link->update(['is_active' => 1]);
            Log::info('Link reactivated — back in stock', [
                'link_id'    => $link->id,
                'product_id' => $link->product_id,
                'store'      => $storeName,
            ]);
        }

        if ($matchedTitle && ! $this->pageLooksRelevantForProduct($link, $matchedTitle, $url)) {
            $link->update([
                'last_checked_at'         => now(),
                'search_status'           => 'mismatch',
                'last_error'              => 'Page does not match product.',
                'matched_title'           => $matchedTitle,
                'competitor_product_name' => $matchedTitle,
            ]);
            return;
        }

        if ($price !== null) {
            $this->savePriceUpdate($link, $price, $matchedTitle, $inStock);
        } else {
            $link->update([
                'last_checked_at'         => now(),
                'search_status'           => 'price_not_found',
                'last_error'              => 'Could not extract price.',
                'matched_title'           => $matchedTitle,
                'competitor_product_name' => $matchedTitle,
            ]);
        }
    }

    // ================================================================
    // PLAYWRIGHT FETCH
    // ================================================================

    protected function fetchPriceViaPlaywright(string $url): array
    {
        if (! file_exists($this->scriptPath)) {
            Log::error('scrape-price.js not found', ['path' => $this->scriptPath]);
            return ['error' => 'scrape-price.js not found'];
        }

        // Mac използва gtimeout (brew install coreutils), Linux използва timeout
        $timeoutCmd = strtoupper(substr(PHP_OS, 0, 3)) === 'DAR' ? 'gtimeout' : 'timeout';

        $cmd    = sprintf(
            '%s 60 node %s %s 2>/dev/null',
            $timeoutCmd,
            escapeshellarg($this->scriptPath),
            escapeshellarg($url)
        );

        $output = shell_exec($cmd);

        if (! $output) {
            return ['error' => 'No output from playwright'];
        }

        $data = json_decode(trim($output), true);

        if (! is_array($data)) {
            return ['error' => 'Invalid JSON from playwright'];
        }

        return $data;
    }

    // ================================================================
    // HTTP FETCH (Zora и др.)
    // ================================================================

    protected function fetchPriceViaHttp(string $url, string $storeName, PriceExtractorService $extractor): array
    {
        if ($storeName === 'zora') {
            usleep(rand(500000, 1200000));
        }

        $response = $this->fetchStorePage($url);
        $status   = (int) ($response['status'] ?? 0);

        if ($status === 403 || $status === 429) {
            return ['blocked' => true];
        }

        if ($status < 200 || $status >= 300) {
            return ['error' => "HTTP {$status}"];
        }

        $html = mb_substr((string) ($response['body'] ?? ''), 0, 350000);

        if ($html === '') {
            return ['error' => 'Empty response'];
        }

        return [
            'price' => $extractor->extractPriceFromUrl($url, $html),
            'title' => $extractor->extractTitleFromHtml($html),
        ];
    }

    // ================================================================
    // SAVE PRICE
    // ================================================================

    protected function savePriceUpdate(CompetitorLink $link, float $price, ?string $matchedTitle, ?bool $inStock): void
    {
        $oldPrice = $link->last_price;

        $link->update([
            'last_price'              => $price,
            'last_checked_at'         => now(),
            'search_status'           => 'found',
            'last_error'              => null,
            'matched_title'           => $matchedTitle,
            'competitor_product_name' => $matchedTitle,
        ]);

        $priceChanged = $oldPrice === null
            || round((float) $oldPrice, 2) !== round($price, 2);

        if (! $priceChanged) {
            return;
        }

        $ourPrice          = $link->product?->fresh()?->our_price;
        $difference        = null;
        $percentDifference = null;
        $position          = null;
        $status            = null;

        if ($ourPrice !== null) {
            $difference = round((float) $ourPrice - $price, 2);

            if ($price > 0) {
                $percentDifference = round((((float) $ourPrice - $price) / $price) * 100, 2);
            }

            if ((float) $ourPrice < $price) {
                $position = '#1 Best Price';
                $status   = 'Cheaper';
            } elseif ((float) $ourPrice > $price) {
                $position = 'Not Best Price';
                $status   = 'More Expensive';
            } else {
                $position = 'Same Price';
                $status   = 'Match';
            }
        }

        PriceHistory::create([
            'product_id'         => $link->product_id,
            'store_id'           => $link->store_id,
            'competitor_link_id' => $link->id,
            'our_price'          => $ourPrice,
            'competitor_price'   => $price,
            'difference'         => $difference,
            'percent_difference' => $percentDifference,
            'best_competitor'    => $link->store?->name,
            'position'           => $position,
            'status'             => $status,
            'checked_at'         => now(),
        ]);

        $this->createPriceNotification($link, $oldPrice, $price, $link->store?->name ?? 'магазин');

        Log::info('Price updated', [
            'url'     => $link->product_url,
            'price'   => $price,
            'link_id' => $link->id,
        ]);
    }

    // ================================================================
    // PAZARUVAJ (непроменен)
    // ================================================================

    protected function handlePazaruvaj(CompetitorLink $link, PazaruvajScraperService $pazaruvajScraper): void
    {
        Log::info('Scraping Pazaruvaj: ' . ($link->product->name ?? 'Unknown'));

        $result = $pazaruvajScraper->scrape($link->product, $link);

        if ($result['success']) {
            $lowestPrice     = $result['lowest_price'];
            $lowestStoreName = $result['lowest_store_name'] ?? 'Pazaruvaj';
            $oldPrice        = $link->last_price;

            $link->update([
                'last_price'      => $lowestPrice,
                'last_checked_at' => now(),
                'search_status'   => 'found',
                'last_error'      => null,
            ]);

            $priceChanged = $oldPrice === null
                || round((float) $oldPrice, 2) !== round((float) $lowestPrice, 2);

            if ($priceChanged) {
                $ourPrice          = $link->product?->fresh()?->our_price;
                $difference        = null;
                $percentDifference = null;
                $position          = null;
                $status            = null;

                if ($ourPrice !== null && $lowestPrice !== null && (float) $lowestPrice > 0) {
                    $difference        = round((float) $ourPrice - (float) $lowestPrice, 2);
                    $percentDifference = round((((float) $ourPrice - (float) $lowestPrice) / (float) $lowestPrice) * 100, 2);

                    if ((float) $ourPrice < (float) $lowestPrice) {
                        $position = '#1 Best Price';
                        $status   = 'Cheaper';
                    } elseif ((float) $ourPrice > (float) $lowestPrice) {
                        $position = 'Not Best Price';
                        $status   = 'More Expensive';
                    } else {
                        $position = 'Same Price';
                        $status   = 'Match';
                    }
                }

                PriceHistory::create([
                    'product_id'         => $link->product_id,
                    'store_id'           => $link->store_id,
                    'competitor_link_id' => $link->id,
                    'our_price'          => $ourPrice,
                    'competitor_price'   => $lowestPrice,
                    'difference'         => $difference,
                    'percent_difference' => $percentDifference,
                    'best_competitor'    => $lowestStoreName,
                    'position'           => $position,
                    'status'             => $status,
                    'checked_at'         => now(),
                ]);

                $this->createPriceNotification(
                    $link,
                    $oldPrice,
                    $lowestPrice,
                    $lowestStoreName . ' (чрез Pazaruvaj)'
                );
            }

            Log::info('Pazaruvaj updated', [
                'product_id'   => $link->product_id,
                'link_id'      => $link->id,
                'lowest_price' => $lowestPrice,
                'lowest_store' => $lowestStoreName,
            ]);
        } else {
            $link->update([
                'last_checked_at' => now(),
                'search_status'   => 'price_not_found',
                'last_error'      => 'Could not scrape Pazaruvaj offers.',
            ]);
        }
    }

    // ================================================================
    // HTTP HELPER
    // ================================================================

    protected function fetchStorePage(string $url): array
    {
        $isTechmart = str_contains($url, 'techmart.bg');

        $response = \Illuminate\Support\Facades\Http::timeout(20)
            ->connectTimeout(8)
            ->retry(2, 700)
            ->withOptions(['verify' => ! $isTechmart])
            ->withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
                'Accept-Language' => 'bg-BG,bg;q=0.9,en;q=0.8',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Cache-Control'   => 'no-cache',
                'Referer'         => $isTechmart ? 'https://techmart.bg/' : $url,
            ])
            ->get($url);

        return [
            'status' => $response->status(),
            'body'   => (string) $response->body(),
        ];
    }

    // ================================================================
    // OWN PRICES
    // ================================================================

    protected function updateOwnProductPrices(?int $productId, OwnProductPriceService $ownPriceService): void
    {
        $query = Product::query()->whereNotNull('product_url');
        if ($productId) {
            $query->where('id', $productId);
        }

        foreach ($query->get() as $product) {
            $productUrl = trim((string) $product->product_url);
            if ($productUrl === '' || $productUrl === '#') {
                continue;
            }

            try {
                $newOwnPrice = $ownPriceService->getPrice($productUrl);

                if ($newOwnPrice === null || (float) $newOwnPrice <= 0) {
                    continue;
                }

                $oldOwnPrice = $product->our_price !== null ? round((float) $product->our_price, 2) : null;
                $newOwnPrice = round((float) $newOwnPrice, 2);

                if ($oldOwnPrice === null || $oldOwnPrice !== $newOwnPrice) {
                    $product->our_price = $newOwnPrice;
                    $product->save();

                    Log::info('Own price updated', [
                        'product_id' => $product->id,
                        'old_price'  => $oldOwnPrice,
                        'new_price'  => $newOwnPrice,
                    ]);

                    $this->createOwnPriceNotification($product, $oldOwnPrice, $newOwnPrice);
                }
            } catch (\Throwable $e) {
                Log::error('Own price update error', [
                    'product_id' => $product->id,
                    'message'    => $e->getMessage(),
                ]);
            }
        }
    }

    // ================================================================
    // NOTIFICATIONS
    // ================================================================

    protected function createOwnPriceNotification(Product $product, ?float $oldPrice, ?float $newPrice): void
    {
        $message = null;

        if ($oldPrice === null && $newPrice !== null) {
            $message = 'Обновена наша цена: ' . $product->name . ' - ' . $this->formatPriceText($newPrice);
        } elseif ($oldPrice !== null && $newPrice !== null && $oldPrice !== $newPrice) {
            $message = 'Промяна в нашата цена: ' . $product->name . ' - ' . $this->formatPriceText($oldPrice) . ' -> ' . $this->formatPriceText($newPrice);
        }

        if (! $message) {
            return;
        }

        $exists = Notification::query()
            ->where('product_id', $product->id)
            ->where('type', 'own_price_changed')
            ->where('message', $message)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($exists) {
            return;
        }

        Notification::create([
            'product_id' => $product->id,
            'type'       => 'own_price_changed',
            'message'    => $message,
            'is_read'    => false,
        ]);
    }

    protected function createPriceNotification(CompetitorLink $link, $oldPrice, $newPrice, string $storeName): void
    {
        $oldPrice    = $oldPrice !== null ? round((float) $oldPrice, 2) : null;
        $newPrice    = $newPrice !== null ? round((float) $newPrice, 2) : null;
        $productName = $link->product->name ?? 'Продукт';
        $message     = null;

        if ($oldPrice === null && $newPrice !== null) {
            $message = 'Нова цена: ' . $productName . ' в ' . $storeName . ' - ' . $this->formatPriceText($newPrice);
        } elseif ($oldPrice !== null && $newPrice === null) {
            $message = 'Цена вече не е налична: ' . $productName . ' в ' . $storeName;
        } elseif ($oldPrice !== null && $newPrice !== null && $oldPrice !== $newPrice) {
            $message = 'Промяна в цена: ' . $productName . ' в ' . $storeName . ' - ' . $this->formatPriceText($oldPrice) . ' -> ' . $this->formatPriceText($newPrice);
        }

        if (! $message) {
            return;
        }

        $exists = Notification::query()
            ->where('product_id', $link->product_id)
            ->where('type', 'price_changed')
            ->where('message', $message)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($exists) {
            return;
        }

        Notification::create([
            'product_id' => $link->product_id,
            'type'       => 'price_changed',
            'message'    => $message,
            'is_read'    => false,
        ]);
    }

    protected function formatPriceText(?float $price): string
    {
        return $price !== null ? number_format($price, 2, '.', '') . ' €' : 'няма цена';
    }

    // ================================================================
    // RELEVANCE CHECK
    // ================================================================

    protected function pageLooksRelevantForProduct($link, ?string $matchedTitle, string $url): bool
    {
        $product = $link->product;
        if (! $product) {
            return true;
        }

        $titleRaw      = mb_strtolower(trim((string) ($matchedTitle ?? '')));
        $urlRaw        = mb_strtolower(trim($url));
        $haystackRaw   = $titleRaw . ' ' . $urlRaw;
        $haystackAscii = mb_strtolower(Str::ascii($haystackRaw));
        $flatRaw       = preg_replace('/[^a-z0-9\p{L}]/u', '', $haystackRaw);
        $flatAscii     = preg_replace('/[^a-z0-9]/', '', $haystackAscii);

        $checks = [];

        $model = trim((string) ($product->model ?? ''));
        if ($model !== '' && $model !== '-' && $model !== '—') {
            $modelFlat  = preg_replace('/[^a-z0-9]/i', '', mb_strtolower(Str::ascii($model)));
            $modelMatch = $modelFlat !== '' && (str_contains($flatAscii, $modelFlat) || str_contains($flatRaw, $modelFlat));
            if ($modelMatch) return true;
            $checks[] = false;
        }

        $sku = trim((string) ($product->sku ?? ''));
        if ($sku !== '' && $sku !== '-' && $sku !== '—') {
            $skuFlat  = preg_replace('/[^a-z0-9]/i', '', mb_strtolower(Str::ascii($sku)));
            $skuMatch = $skuFlat !== '' && (str_contains($flatAscii, $skuFlat) || str_contains($flatRaw, $skuFlat));
            if ($skuMatch) return true;
            $checks[] = false;
        }

        $ean = preg_replace('/\D+/', '', (string) ($product->ean ?? ''));
        if (strlen($ean) >= 8) {
            if (str_contains($flatAscii, $ean) || str_contains($flatRaw, $ean)) return true;
            $checks[] = false;
        }

        $brand = trim((string) ($product->brand ?? ''));
        if ($brand !== '' && mb_strlen($brand) >= 3) {
            $brandMatch = str_contains($haystackRaw, mb_strtolower($brand))
                || str_contains($haystackAscii, mb_strtolower(Str::ascii($brand)));
            $checks[]   = $brandMatch;
        }

        $name = trim((string) ($product->name ?? ''));
        if ($name !== '') {
            $nameHits = 0;
            foreach (preg_split('/\s+/u', mb_strtolower($name)) as $word) {
                $word = trim((string) $word);
                if (mb_strlen($word) < 4) continue;
                if (str_contains($haystackRaw, $word) || str_contains($haystackAscii, mb_strtolower(Str::ascii($word)))) {
                    $nameHits++;
                }
            }
            if ($nameHits >= 2) $checks[] = true;
        }

        $result = in_array(true, $checks, true);

        if (! $result) {
            Log::debug('Page relevance rejected', [
                'product_id'    => $product->id,
                'url'           => $url,
                'matched_title' => $matchedTitle,
            ]);
        }

        return $result;
    }
}