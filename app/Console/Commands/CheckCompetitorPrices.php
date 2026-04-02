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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckCompetitorPrices extends Command
{
    protected $signature = 'prices:check {product_id?} {--link_id=}';
    protected $description = 'Check competitor prices';

    public function handle()
    {
        Log::info('Prices check started');

        $productId = $this->argument('product_id');
        $linkId = $this->option('link_id');

        $linksQuery = CompetitorLink::with(['product', 'store'])->where('is_active', 1);

        if ($linkId) {
            $linksQuery->where('id', $linkId);
        } elseif ($productId) {
            $linksQuery->where('product_id', $productId);
        }

        $links = $linksQuery->get();

        $extractor = app(PriceExtractorService::class);
        $pazaruvajScraper = app(PazaruvajScraperService::class);
        $ownPriceService = app(OwnProductPriceService::class);

        if ($linkId) {
            $linkProductId = optional($links->first())->product_id;
            if ($linkProductId) {
                $this->updateOwnProductPrices((int) $linkProductId, $ownPriceService);
            }
        } else {
            $this->updateOwnProductPrices($productId, $ownPriceService);
        }

        foreach ($links as $link) {
            $url = trim((string) $link->product_url);

            if (empty($url) || $url === '#') {
                $link->update([
                    'last_checked_at' => now(),
                    'search_status' => 'invalid_url',
                    'last_error' => 'Missing or placeholder URL.',
                ]);
                continue;
            }

            $storeName = strtolower(trim((string) ($link->store->name ?? '')));

            // ── PAZARUVAJ ─────────────────────────────────────────────
            if ($storeName === 'pazaruvaj') {
                $this->info('Scraping Pazaruvaj offers for product: ' . ($link->product->name ?? 'Unknown'));

                try {
                    $result = $pazaruvajScraper->scrape($link->product, $link);

                    if ($result['success']) {
                        $lowestPrice = $result['lowest_price'];
                        $lowestStoreName = $result['lowest_store_name'] ?? 'Pazaruvaj';
                        $oldPrice = $link->last_price;

                        $link->update([
                            'last_price' => $lowestPrice,
                            'last_checked_at' => now(),
                            'search_status' => 'found',
                            'last_error' => null,
                        ]);

                        $priceChanged = $oldPrice === null
                            || round((float) $oldPrice, 2) !== round((float) $lowestPrice, 2);

                        if ($priceChanged) {
                            $ourPrice = $link->product?->fresh()?->our_price;
                            $difference = null;
                            $percentDifference = null;
                            $position = null;
                            $status = null;

                            if ($ourPrice !== null && $lowestPrice !== null && (float) $lowestPrice > 0) {
                                $difference = round((float) $ourPrice - (float) $lowestPrice, 2);
                                $percentDifference = round((((float) $ourPrice - (float) $lowestPrice) / (float) $lowestPrice) * 100, 2);

                                if ((float) $ourPrice < (float) $lowestPrice) {
                                    $position = '#1 Best Price';
                                    $status = 'Cheaper';
                                } elseif ((float) $ourPrice > (float) $lowestPrice) {
                                    $position = 'Not Best Price';
                                    $status = 'More Expensive';
                                } else {
                                    $position = 'Same Price';
                                    $status = 'Match';
                                }
                            }

                            PriceHistory::create([
                                'product_id' => $link->product_id,
                                'store_id' => $link->store_id,
                                'competitor_link_id' => $link->id,
                                'our_price' => $ourPrice,
                                'competitor_price' => $lowestPrice,
                                'difference' => $difference,
                                'percent_difference' => $percentDifference,
                                'best_competitor' => $lowestStoreName,
                                'position' => $position,
                                'status' => $status,
                                'checked_at' => now(),
                            ]);

                            $this->createPriceNotification(
                                $link,
                                $oldPrice,
                                $lowestPrice,
                                $lowestStoreName . ' (чрез Pazaruvaj)'
                            );
                        }

                        $this->info(
                            'Pazaruvaj updated: lowest=' . $lowestPrice .
                            ' €, best_store=' . $lowestStoreName .
                            ', offers=' . ($result['offers_count'] ?? 0) .
                            ', our position=' . ($result['our_position'] ?? '—')
                        );

                        Log::info('Pazaruvaj offers updated', [
                            'product_id' => $link->product_id,
                            'link_id' => $link->id,
                            'lowest_price' => $lowestPrice,
                            'lowest_store' => $lowestStoreName,
                        ]);
                    } else {
                        $this->warn('Pazaruvaj offers not found.');

                        $link->update([
                            'last_checked_at' => now(),
                            'search_status' => 'price_not_found',
                            'last_error' => 'Could not scrape Pazaruvaj offers.',
                        ]);
                    }
                } catch (\Throwable $e) {
                    $this->error('Pazaruvaj error: ' . $e->getMessage());

                    $link->update([
                        'last_checked_at' => now(),
                        'search_status' => 'error',
                        'last_error' => $e->getMessage(),
                    ]);

                    Log::error('Pazaruvaj scrape error', [
                        'url' => $url,
                        'link_id' => $link->id,
                        'message' => $e->getMessage(),
                    ]);
                }

                continue;
            }

            // ── ZORA пауза ────────────────────────────────────────────
            if ($storeName === 'zora') {
                usleep(rand(500000, 1200000));
            }

            $this->info('Checking: ' . $url);

            try {
                $response = $this->fetchStorePage($url);
                $statusCode = (int) ($response['status'] ?? 0);

                if (str_contains($url, 'zora.bg')) {
                    Log::info('Zora response debug', [
                        'url' => $url,
                        'status' => $statusCode,
                        'body_start' => mb_substr((string) ($response['body'] ?? ''), 0, 300),
                    ]);
                }

                if ($statusCode < 200 || $statusCode >= 300) {
                    $this->error('Could not load page. HTTP ' . $statusCode);

                    $link->update([
                        'last_checked_at' => now(),
                        'search_status' => $statusCode === 403 ? 'blocked' : 'request_failed',
                        'last_error' => 'HTTP status: ' . $statusCode,
                    ]);

                    Log::warning('Could not load page', [
                        'url' => $url,
                        'link_id' => $link->id,
                        'status' => $statusCode,
                    ]);

                    continue;
                }

                $html = (string) ($response['body'] ?? '');

                if ($html === '') {
                    $link->update([
                        'last_checked_at' => now(),
                        'search_status' => 'request_failed',
                        'last_error' => 'Empty HTML response.',
                    ]);
                    continue;
                }

                $html = mb_substr($html, 0, 350000);
                $price = $extractor->extractPriceFromUrl($url, $html);
                $matchedTitle = $extractor->extractTitleFromHtml($html);

                if (!$this->pageLooksRelevantForProduct($link, $matchedTitle, $url)) {
                    $this->warn('Skipped mismatched product page.');

                    $link->update([
                        'last_checked_at' => now(),
                        'search_status' => 'mismatch',
                        'last_error' => 'Page title/url does not match product.',
                        'matched_title' => $matchedTitle,
                        'competitor_product_name' => $matchedTitle,
                    ]);

                    Log::warning('Skipped mismatched product page', [
                        'url' => $url,
                        'link_id' => $link->id,
                        'matched_title' => $matchedTitle,
                        'store' => $link->store?->name,
                    ]);

                    continue;
                }

                if ($price !== null) {
                    $oldPrice = $link->last_price;

                    $link->update([
                        'last_price' => $price,
                        'last_checked_at' => now(),
                        'search_status' => 'found',
                        'last_error' => null,
                        'matched_title' => $matchedTitle,
                        'competitor_product_name' => $matchedTitle,
                    ]);

                    $priceChanged = $oldPrice === null
                        || round((float) $oldPrice, 2) !== round((float) $price, 2);

                    if ($priceChanged) {
                        $ourPrice = $link->product?->fresh()?->our_price;
                        $difference = null;
                        $percentDifference = null;
                        $position = null;
                        $status = null;

                        if ($ourPrice !== null) {
                            $difference = round((float) $ourPrice - (float) $price, 2);

                            if ((float) $price > 0) {
                                $percentDifference = round((((float) $ourPrice - (float) $price) / (float) $price) * 100, 2);
                            }

                            if ((float) $ourPrice < (float) $price) {
                                $position = '#1 Best Price';
                                $status = 'Cheaper';
                            } elseif ((float) $ourPrice > (float) $price) {
                                $position = 'Not Best Price';
                                $status = 'More Expensive';
                            } else {
                                $position = 'Same Price';
                                $status = 'Match';
                            }
                        }

                        PriceHistory::create([
                            'product_id' => $link->product_id,
                            'store_id' => $link->store_id,
                            'competitor_link_id' => $link->id,
                            'our_price' => $ourPrice,
                            'competitor_price' => $price,
                            'difference' => $difference,
                            'percent_difference' => $percentDifference,
                            'best_competitor' => $link->store?->name,
                            'position' => $position,
                            'status' => $status,
                            'checked_at' => now(),
                        ]);

                        $this->createPriceNotification($link, $oldPrice, $price, $link->store?->name ?? 'магазин');
                    }

                    $this->info('Price updated: ' . $price);

                    Log::info('Price updated', [
                        'url' => $url,
                        'price' => $price,
                        'link_id' => $link->id,
                    ]);
                } else {
                    $this->warn('Price not found.');

                    $link->update([
                        'last_checked_at' => now(),
                        'search_status' => 'price_not_found',
                        'last_error' => 'Could not extract price from HTML.',
                        'matched_title' => $matchedTitle,
                        'competitor_product_name' => $matchedTitle,
                    ]);

                    Log::warning('Price not found', [
                        'url' => $url,
                        'link_id' => $link->id,
                    ]);
                }
            } catch (\Throwable $e) {
                $this->error('Error: ' . $e->getMessage());

                $link->update([
                    'last_checked_at' => now(),
                    'search_status' => 'error',
                    'last_error' => $e->getMessage(),
                ]);

                Log::error('Price check error', [
                    'url' => $url,
                    'link_id' => $link->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Prices check finished');
        return Command::SUCCESS;
    }

    protected function updateOwnProductPrices(?int $productId, OwnProductPriceService $ownPriceService): void
    {
        $productsQuery = Product::query()->whereNotNull('product_url');

        if ($productId) {
            $productsQuery->where('id', $productId);
        }

        $products = $productsQuery->get();

        foreach ($products as $product) {
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
                        'old_price' => $oldOwnPrice,
                        'new_price' => $newOwnPrice,
                    ]);

                    $this->createOwnPriceNotification($product, $oldOwnPrice, $newOwnPrice);
                }
            } catch (\Throwable $e) {
                Log::error('Own price update error', [
                    'product_id' => $product->id,
                    'url' => $productUrl,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function createOwnPriceNotification(Product $product, ?float $oldPrice, ?float $newPrice): void
    {
        $productName = $product->name ?? 'Продукт';
        $message = null;

        if ($oldPrice === null && $newPrice !== null) {
            $message = 'Обновена наша цена: ' . $productName . ' - ' . $this->formatPriceText($newPrice);
        } elseif ($oldPrice !== null && $newPrice !== null && $oldPrice !== $newPrice) {
            $message = 'Промяна в нашата цена: ' . $productName . ' - ' . $this->formatPriceText($oldPrice) . ' → ' . $this->formatPriceText($newPrice);
        }

        if (!$message) {
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
            'type' => 'own_price_changed',
            'message' => $message,
            'is_read' => false,
        ]);
    }

    protected function createPriceNotification(CompetitorLink $link, $oldPrice, $newPrice, string $storeName): void
    {
        $oldPrice = $oldPrice !== null ? round((float) $oldPrice, 2) : null;
        $newPrice = $newPrice !== null ? round((float) $newPrice, 2) : null;
        $productName = $link->product->name ?? 'Продукт';
        $message = null;

        if ($oldPrice === null && $newPrice !== null) {
            $message = 'Нова цена: ' . $productName . ' в ' . $storeName . ' - ' . $this->formatPriceText($newPrice);
        } elseif ($oldPrice !== null && $newPrice === null) {
            $message = 'Цена вече не е налична: ' . $productName . ' в ' . $storeName;
        } elseif ($oldPrice !== null && $newPrice !== null && $oldPrice !== $newPrice) {
            $message = 'Промяна в цена: ' . $productName . ' в ' . $storeName . ' - ' . $this->formatPriceText($oldPrice) . ' → ' . $this->formatPriceText($newPrice);
        }

        if (!$message) {
            return;
        }

        $exists = Notification::query()
            ->where('product_id', $link->product_id)
            ->where('type', 'price_changed')
            ->where('message', $message)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($exists) {
            Log::info('Duplicate notification skipped', ['product_id' => $link->product_id]);
            return;
        }

        Notification::create([
            'product_id' => $link->product_id,
            'type' => 'price_changed',
            'message' => $message,
            'is_read' => false,
        ]);
    }

    protected function formatPriceText(?float $price): string
    {
        if ($price === null) {
            return 'няма цена';
        }

        return number_format($price, 2, '.', '') . ' €';
    }

    protected function fetchStorePage(string $url): array
    {
        $isTechmart = str_contains($url, 'techmart.bg');

        $response = Http::timeout(20)
            ->connectTimeout(8)
            ->retry(2, 700)
            ->withOptions([
                'verify' => $isTechmart ? false : true,
            ])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
                'Accept-Language' => 'bg-BG,bg;q=0.9,en;q=0.8',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
                'Referer' => $isTechmart ? 'https://techmart.bg/' : $url,
            ])
            ->get($url);

        return [
            'status' => $response->status(),
            'body' => (string) $response->body(),
        ];
    }

    protected function fetchZoraPageShell(string $url): array
    {
        $cmd = sprintf(
            '/usr/bin/curl -s -L --max-time 25 -H %s -H %s -w "HTTPSTATUS:%%{http_code}" %s',
            escapeshellarg('User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36'),
            escapeshellarg('Referer: https://zora.bg/'),
            escapeshellarg($url)
        );

        $output = (string) shell_exec($cmd);
        preg_match('/HTTPSTATUS:(\d+)$/', $output, $matches);
        $status = (int) ($matches[1] ?? 0);
        $body = (string) preg_replace('/HTTPSTATUS:\d+$/', '', $output);

        if ($status < 200 || $status >= 300 || trim($body) === '') {
            sleep(3);
            $output = (string) shell_exec($cmd);
            preg_match('/HTTPSTATUS:(\d+)$/', $output, $matches);
            $status = (int) ($matches[1] ?? 0);
            $body = (string) preg_replace('/HTTPSTATUS:\d+$/', '', $output);
        }

        Log::info('Zora shell curl result', [
            'url' => $url,
            'status' => $status,
            'body_len' => strlen($body),
        ]);

        return ['status' => $status, 'body' => $body];
    }

    protected function pageLooksRelevantForProduct($link, ?string $matchedTitle, string $url): bool
    {
        $product = $link->product;

        if (!$product) {
            return true;
        }

        $titleRaw = mb_strtolower(trim((string) ($matchedTitle ?? '')));
        $urlRaw = mb_strtolower(trim($url));
        $haystackRaw = $titleRaw . ' ' . $urlRaw;
        $haystackAscii = mb_strtolower(Str::ascii($haystackRaw));
        $flatRaw = preg_replace('/[^a-z0-9\p{L}]/u', '', $haystackRaw);
        $flatAscii = preg_replace('/[^a-z0-9]/', '', $haystackAscii);

        $checks = [];
        $debugInfo = [];

        $model = trim((string) ($product->model ?? ''));
        if ($model !== '' && $model !== '-' && $model !== '—') {
            $modelFlat = preg_replace('/[^a-z0-9]/i', '', mb_strtolower(Str::ascii($model)));
            $modelMatch = $modelFlat !== '' && (str_contains($flatAscii, $modelFlat) || str_contains($flatRaw, $modelFlat));
            $debugInfo['model'] = $modelMatch;

            if ($modelMatch) {
                return true;
            }

            $checks[] = false;
        }

        $sku = trim((string) ($product->sku ?? ''));
        if ($sku !== '' && $sku !== '-' && $sku !== '—') {
            $skuFlat = preg_replace('/[^a-z0-9]/i', '', mb_strtolower(Str::ascii($sku)));
            $skuMatch = $skuFlat !== '' && (str_contains($flatAscii, $skuFlat) || str_contains($flatRaw, $skuFlat));
            $debugInfo['sku'] = $skuMatch;

            if ($skuMatch) {
                return true;
            }

            $checks[] = false;
        }

        $ean = preg_replace('/\D+/', '', (string) ($product->ean ?? ''));
        if (strlen($ean) >= 8) {
            $eanMatch = str_contains($flatAscii, $ean) || str_contains($flatRaw, $ean);
            $debugInfo['ean'] = $eanMatch;

            if ($eanMatch) {
                return true;
            }

            $checks[] = false;
        }

        $brand = trim((string) ($product->brand ?? ''));
        if ($brand !== '' && $brand !== '-' && mb_strlen($brand) >= 3) {
            $brandMatch = str_contains($haystackRaw, mb_strtolower($brand))
                || str_contains($haystackAscii, mb_strtolower(Str::ascii($brand)));

            $debugInfo['brand'] = $brandMatch;
            $checks[] = $brandMatch;
        }

        $name = trim((string) ($product->name ?? ''));
        if ($name !== '') {
            $nameHits = 0;

            foreach (preg_split('/\s+/u', mb_strtolower($name)) as $word) {
                $word = trim((string) $word);

                if (mb_strlen($word) < 4) {
                    continue;
                }

                if (
                    str_contains($haystackRaw, $word)
                    || str_contains($haystackAscii, mb_strtolower(Str::ascii($word)))
                ) {
                    $nameHits++;
                }
            }

            $debugInfo['name_hits'] = $nameHits;

            if ($nameHits >= 2) {
                $checks[] = true;
            }
        }

        $result = in_array(true, $checks, true);

        if (!$result) {
            Log::debug('Page relevance: rejected', [
                'product_id' => $product->id,
                'url' => $url,
                'matched_title' => $matchedTitle,
                'checks' => $debugInfo,
            ]);
        }

        return $result;
    }
}