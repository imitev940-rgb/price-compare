<?php

namespace App\Services;

use App\Models\CompetitorLink;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AutoProductSearchService
{
    protected static bool $zoraWarmedUp     = false;
    protected static ?array $zoraSitemapUrls = null;

    private const SCORE_ACCEPT   = 80;
    private const SCORE_FALLBACK = 55;
    private const SCORE_URL_HINT = 20;

    // ================================================================
    // HANDLE
    // ================================================================

    public function handle(Product $product, bool $overwrite = false, ?string $onlyStore = null): void
    {
        $onlyStoreNorm = $onlyStore ? mb_strtolower(trim($onlyStore)) : null;

        $stores = [
            'Pazaruvaj'    => fn () => $this->searchPazaruvajUrl($product),
            'Technopolis'  => fn () => $this->searchTechnopolisUrl($product),
            'Technomarket' => fn () => $this->searchTechnomarketUrl($product),
            'Zora'         => fn () => $this->searchZoraUrl($product),
        ];

        foreach ($stores as $storeName => $resolver) {
            if ($onlyStoreNorm !== null && mb_strtolower($storeName) !== $onlyStoreNorm) {
                continue;
            }
            try {
                $store = Store::firstOrCreate(
                    ['name' => $storeName],
                    ['url'  => $this->defaultStoreUrl($storeName)]
                );

                $existing = CompetitorLink::where('product_id', $product->id)
                    ->where('store_id', $store->id)->first();

                if ($existing && !$overwrite) {
                    continue;
                }

                $url = $resolver();

                if (!$url) {
                    Log::info('Auto search no url found', [
                        'product_id' => $product->id,
                        'store'      => $storeName,
                    ]);
                    continue;
                }

                CompetitorLink::updateOrCreate(
                    ['product_id' => $product->id, 'store_id' => $store->id],
                    ['product_url' => $url, 'is_active' => 1]
                );

                Log::info('Auto search saved link', [
                    'product_id' => $product->id,
                    'store'      => $storeName,
                    'url'        => $url,
                ]);
            } catch (\Throwable $e) {
                Log::error('Auto search store failed', [
                    'product_id' => $product->id,
                    'store'      => $storeName,
                    'message'    => $e->getMessage(),
                ]);
            }
        }
    }

    public static function resetSitemapCache(): void
    {
        static::$zoraSitemapUrls = null;
        static::$zoraWarmedUp    = false;
    }

    // ================================================================
    // PAZARUVAJ
    // ================================================================

    protected function searchPazaruvajUrl(Product $product): ?string
    {
        $bestUrl = null; $bestScore = -999;

        foreach (array_slice($this->buildProgressiveQueries($product), 0, 16) as $query) {
            try {
                $resp = Http::timeout(20)
                    ->withHeaders($this->headers('https://www.pazaruvaj.com/'))
                    ->get('https://www.pazaruvaj.com/CategorySearch.php?st=' . urlencode($query));

                if (!$resp->successful()) continue;

                preg_match_all('/https:\/\/www\.pazaruvaj\.com\/p\/[^"\'<>\s]+/i', $resp->body(), $m);

                foreach (array_values(array_unique($m[0] ?? [])) as $c) {
                    $u = $this->cleanUrl($c);
                    [$s] = $this->computeMatchScore($u, '', $product);
                    if ($s > $bestScore) { $bestScore = $s; $bestUrl = $u; }

                    if ($s >= self::SCORE_ACCEPT) {
                        $html = $this->fetchHtml($u, 'https://www.pazaruvaj.com/');
                        [$ps] = $this->computeMatchScore($u, (string) $html, $product);
                        if ($ps >= self::SCORE_ACCEPT) {
                            Log::info('Pazaruvaj found', ['product_id' => $product->id, 'url' => $u, 'score' => $ps]);
                            return $u;
                        }
                    }
                }
            } catch (\Throwable) {}
        }

        if ($bestUrl && $bestScore >= self::SCORE_FALLBACK) {
            $html = $this->fetchHtml($bestUrl, 'https://www.pazaruvaj.com/');
            [$ps] = $this->computeMatchScore($bestUrl, (string) $html, $product);
            if ($ps >= self::SCORE_FALLBACK) {
                Log::info('Pazaruvaj fallback', ['product_id' => $product->id, 'url' => $bestUrl, 'score' => $ps]);
                return $bestUrl;
            }
        }

        return null;
    }

    // ================================================================
    // TECHNOPOLIS
    // ================================================================

    protected function searchTechnopolisUrl(Product $product): ?string
    {
        $bestUrl = null; $bestScore = -999;

        foreach (array_slice($this->buildProgressiveQueries($product), 0, 14) as $query) {
            $searchUrls = [
                'https://www.technopolis.bg/bg/search?query=' . urlencode($query),
                'https://www.technopolis.bg/bg/search/' . Str::slug(Str::ascii($query), '-') . '?query=' . urlencode($query),
            ];

            foreach ($searchUrls as $searchUrl) {
                $html = $this->fetchHtml($searchUrl, 'https://www.technopolis.bg/');
                if (!$html) continue;

                foreach ($this->extractTechnopolisProductUrls($html) as $u) {
                    [$s] = $this->computeMatchScore($u, '', $product);
                    if ($s > $bestScore) { $bestScore = $s; $bestUrl = $u; }

                    if ($s >= self::SCORE_URL_HINT) {
                        $pageHtml = $this->fetchHtml($u, 'https://www.technopolis.bg/');
                        if (!$pageHtml) continue;
                        [$ps, $why] = $this->computeMatchScore($u, $pageHtml, $product);
                        Log::debug('Technopolis candidate', ['product_id' => $product->id, 'url' => $u, 'score' => $ps, 'why' => $why]);
                        if ($ps > $bestScore) { $bestScore = $ps; $bestUrl = $u; }
                        if ($ps >= self::SCORE_ACCEPT) {
                            Log::info('Technopolis found', ['product_id' => $product->id, 'url' => $u, 'score' => $ps]);
                            return $u;
                        }
                    }
                }
            }
        }

        if ($bestUrl && $bestScore >= self::SCORE_FALLBACK) {
            Log::info('Technopolis fallback', ['product_id' => $product->id, 'url' => $bestUrl, 'score' => $bestScore]);
            return $bestUrl;
        }

        return null;
    }

    protected function extractTechnopolisProductUrls(string $html): array
    {
        $urls = []; $seen = [];

        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $m1);
        preg_match_all('/"url"\s*:\s*"([^"]+)"/i', $html, $m2);
        preg_match_all('/data-url=["\']([^"\']+)["\']/i', $html, $m3);

        foreach (array_merge($m1[1] ?? [], $m2[1] ?? [], $m3[1] ?? []) as $href) {
            $href = str_replace('\/', '/', (string) $href);
            $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($href === '') continue;
            if (!Str::contains($href, 'technopolis.bg') && !Str::startsWith($href, '/')) continue;

            $abs  = $this->cleanUrl($this->makeAbsoluteUrl($href, 'https://www.technopolis.bg'));
            $path = parse_url($abs, PHP_URL_PATH) ?? '';

            if (!preg_match('#/p/\d+#i', $path)) continue;
            if (preg_match('#/(search|compare|cart|wishlist|account|checkout)#i', $abs)) continue;

            if (!isset($seen[$abs])) { $seen[$abs] = true; $urls[] = $abs; }
        }

        return $urls;
    }

    // ================================================================
    // TECHNOMARKET
    // ================================================================

    protected function searchTechnomarketUrl(Product $product): ?string
    {
        $bestUrl = null; $bestScore = -999;

        foreach (array_slice($this->buildProgressiveQueries($product), 0, 16) as $query) {
            $html = $this->fetchHtml(
                'https://www.technomarket.bg/search?query=' . urlencode($query),
                'https://www.technomarket.bg/'
            );
            if (!$html) continue;

            foreach ($this->extractTechnomarketProductUrls($html) as $u) {
                [$s] = $this->computeMatchScore($u, '', $product);
                if ($s > $bestScore) { $bestScore = $s; $bestUrl = $u; }

                if ($s >= self::SCORE_URL_HINT) {
                    $pageHtml = $this->fetchHtml($u, 'https://www.technomarket.bg/');
                    if (!$pageHtml) continue;
                    [$ps, $why] = $this->computeMatchScore($u, $pageHtml, $product);
                    Log::debug('Technomarket candidate', ['product_id' => $product->id, 'url' => $u, 'score' => $ps, 'why' => $why]);
                    if ($ps > $bestScore) { $bestScore = $ps; $bestUrl = $u; }
                    if ($ps >= self::SCORE_ACCEPT) {
                        Log::info('Technomarket found', ['product_id' => $product->id, 'url' => $u, 'score' => $ps]);
                        return $u;
                    }
                }
            }
        }

        if ($bestUrl && $bestScore >= self::SCORE_FALLBACK) {
            Log::info('Technomarket fallback', ['product_id' => $product->id, 'url' => $bestUrl, 'score' => $bestScore]);
            return $bestUrl;
        }

        return null;
    }

    protected function extractTechnomarketProductUrls(string $html): array
    {
        $urls = []; $seen = [];
        $hardBlock = ['/cart', '/wishlist', '/account', '/checkout', '/search', '/category', '/brand', '/filter'];
        $imageExts = ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg', '.ico', '.pdf'];

        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $m);

        foreach (($m[1] ?? []) as $href) {
            $href = html_entity_decode(trim((string) $href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($href === '') continue;
            if (!Str::contains($href, 'technomarket.bg') && !Str::startsWith($href, '/')) continue;

            $abs  = $this->cleanUrl($this->makeAbsoluteUrl($href, 'https://www.technomarket.bg'));
            $path = parse_url($abs, PHP_URL_PATH) ?? '';
            if ($path === '' || $path === '/') continue;

            $skip = false;
            foreach ($hardBlock as $b) { if (Str::contains($abs, $b)) { $skip = true; break; } }
            foreach ($imageExts as $e) { if (Str::endsWith(mb_strtolower($abs), $e)) { $skip = true; break; } }
            if ($skip) continue;

            $segments = array_filter(explode('/', trim($path, '/')));
            if (count($segments) < 2) continue;

            if (!isset($seen[$abs])) { $seen[$abs] = true; $urls[] = $abs; }
        }

        return $urls;
    }

    // ================================================================
    // ZORA
    // ================================================================

    protected function searchZoraUrl(Product $product): ?string
    {
        $primary = $this->getPrimaryIdentifier($product);
        $brand   = trim((string) $product->brand);

        Log::info('Zora search start', [
            'product_id' => $product->id,
            'primary'    => $primary,
            'brand'      => $brand,
        ]);

        $url = $this->searchZoraViaHtml($product);
        if ($url) return $url;

        $url = $this->searchZoraViaSearchEngine($product, 'google');
        if ($url) return $url;

        usleep(1500000);
        $url = $this->searchZoraViaSearchEngine($product, 'bing');
        if ($url) return $url;

        return null;
    }

    protected function searchZoraViaHtml(Product $product): ?string
    {
        $bestUrl   = null;
        $bestScore = -999;
        $firstUrl  = null;

        foreach (array_slice($this->buildProgressiveQueries($product), 0, 12) as $query) {
            foreach ([
                'https://zora.bg/search?q=' . urlencode($query),
                'https://zora.bg/catalogsearch/result/?q=' . urlencode($query),
            ] as $searchUrl) {

                $html = $this->fetchZoraHtml($searchUrl);

                Log::info('Zora search page', [
                    'product_id' => $product->id,
                    'query'      => $query,
                    'url'        => $searchUrl,
                    'got_html'   => $html !== null,
                    'body_len'   => $html ? strlen($html) : 0,
                ]);

                if (!$html) continue;

                $candidates = $this->extractZoraProductUrls($html);

                Log::info('Zora search candidates', [
                    'product_id' => $product->id,
                    'query'      => $query,
                    'count'      => count($candidates),
                    'urls'       => array_slice($candidates, 0, 5),
                ]);

                if (empty($candidates)) continue;

                if ($firstUrl === null) {
                    $firstUrl = $candidates[0];
                }

                foreach ($candidates as $u) {
                    [$s, $why] = $this->computeMatchScore($u, '', $product);

                    Log::debug('Zora url score', [
                        'product_id' => $product->id,
                        'url'        => $u,
                        'score'      => $s,
                        'why'        => $why,
                    ]);

                    if ($s > $bestScore) { $bestScore = $s; $bestUrl = $u; }

                    if ($s >= self::SCORE_ACCEPT) {
                        $pageHtml = $this->fetchZoraHtml($u);
                        if ($pageHtml) {
                            [$ps] = $this->computeMatchScore($u, $pageHtml, $product);
                            if ($ps > $bestScore) { $bestScore = $ps; $bestUrl = $u; }
                            if ($ps >= self::SCORE_ACCEPT) {
                                Log::info('Zora found via search+page', ['product_id' => $product->id, 'url' => $u, 'score' => $ps]);
                                return $u;
                            }
                        } else {
                            Log::info('Zora found via search url-only', ['product_id' => $product->id, 'url' => $u, 'score' => $s]);
                            return $u;
                        }
                    }
                }

                if ($firstUrl !== null && $bestScore < self::SCORE_ACCEPT) {
                    $pageHtml = $this->fetchZoraHtml($firstUrl);
                    if ($pageHtml) {
                        [$ps, $why] = $this->computeMatchScore($firstUrl, $pageHtml, $product);
                        Log::info('Zora first-result page score', [
                            'product_id' => $product->id,
                            'url'        => $firstUrl,
                            'score'      => $ps,
                            'why'        => $why,
                        ]);
                        if ($ps >= self::SCORE_FALLBACK) {
                            Log::info('Zora found via first-result', ['product_id' => $product->id, 'url' => $firstUrl, 'score' => $ps]);
                            return $firstUrl;
                        }
                    }
                }
            }
        }

        if ($bestUrl && $bestScore >= self::SCORE_FALLBACK) {
            Log::info('Zora fallback best via html', ['product_id' => $product->id, 'url' => $bestUrl, 'score' => $bestScore]);
            return $bestUrl;
        }

        if ($firstUrl) {
            $pageHtml = $this->fetchZoraHtml($firstUrl);
            if ($pageHtml) {
                [$ps] = $this->computeMatchScore($firstUrl, $pageHtml, $product);
                if ($ps >= self::SCORE_FALLBACK) {
                    Log::info('Zora last-resort first-result', ['product_id' => $product->id, 'url' => $firstUrl, 'score' => $ps]);
                    return $firstUrl;
                }
            }
        }

        return null;
    }

    protected function searchZoraViaSearchEngine(Product $product, string $engine): ?string
    {
        $primary = $this->getPrimaryIdentifier($product);
        $brand   = trim((string) $product->brand);
        $name    = trim((string) $product->name);

        $queries = [];
        if ($primary && $brand) $queries[] = "site:zora.bg $brand $primary";
        if ($primary)           $queries[] = "site:zora.bg $primary";
        if ($brand && $name)    $queries[] = "site:zora.bg $brand $name";

        foreach (array_slice($queries, 0, 3) as $query) {
            $url = $this->searchEngineQuery($query, $product, $engine);
            if ($url) return $url;
            usleep(2000000);
        }

        return null;
    }

    protected function searchEngineQuery(string $query, Product $product, string $engine): ?string
    {
        try {
            if ($engine === 'google') {
                $searchUrl = 'https://www.google.com/search?q=' . urlencode($query) . '&num=10&hl=bg&gl=bg';
                $referer   = 'https://www.google.com/';
            } else {
                $searchUrl = 'https://www.bing.com/search?q=' . urlencode($query) . '&count=10&setlang=bg&cc=BG';
                $referer   = 'https://www.bing.com/';
            }

            $resp = Http::timeout(25)->withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
                'Accept-Language' => 'bg-BG,bg;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Referer'         => $referer,
                'Cache-Control'   => 'no-cache',
            ])->get($searchUrl);

            $body = $resp->body();

            Log::info("Zora $engine query", [
                'product_id' => $product->id,
                'query'      => $query,
                'status'     => $resp->status(),
                'body_len'   => strlen($body),
            ]);

            if (!$resp->successful() || strlen($body) < 500) return null;

            $raw = [];

            preg_match_all('/https?:\/\/(?:www\.)?zora\.bg\/product\/[^"\'&\s<>\\\]+/i', $body, $m1);
            $raw = array_merge($raw, $m1[0] ?? []);

            preg_match_all('/href=["\']([^"\']*zora\.bg\/product\/[^"\']+)["\']/i', $body, $m2);
            $raw = array_merge($raw, $m2[1] ?? []);

            preg_match_all('/\/url\?[^"\']*q=(https?:\/\/(?:www\.)?zora\.bg\/product\/[^&"\'<>\s]+)/i', $body, $m3);
            $raw = array_merge($raw, $m3[1] ?? []);

            preg_match_all('/https?%3A%2F%2F(?:www\.)?zora\.bg%2Fproduct%2F([^&"\'<>\s%]+)/i', $body, $m4);
            foreach (($m4[0] ?? []) as $encoded) { $raw[] = urldecode($encoded); }

            preg_match_all('/data-(?:href|url)=["\']([^"\']*zora\.bg\/product\/[^"\']+)["\']/i', $body, $m5);
            $raw = array_merge($raw, $m5[1] ?? []);

            preg_match_all('/"(https?:\/\/(?:www\.)?zora\.bg\/product\/[^"\\\\]+)"/i', $body, $m6);
            $raw = array_merge($raw, $m6[1] ?? []);

            preg_match_all('/<cite[^>]*>([^<]*zora\.bg\/product\/[^<]+)<\/cite>/i', $body, $m7);
            foreach (($m7[1] ?? []) as $cite) {
                $cite = strip_tags($cite);
                if (!Str::startsWith($cite, 'http')) $cite = 'https://' . $cite;
                $raw[] = $cite;
            }

            $candidates = array_values(array_unique(array_map(
                fn ($u) => $this->cleanUrl(html_entity_decode(urldecode(trim((string) $u)), ENT_QUOTES, 'UTF-8')),
                $raw
            )));

            $candidates = array_values(array_filter(
                $candidates,
                fn ($u) => (bool) preg_match('#https?://(?:www\.)?zora\.bg/product/[^/?#\s]+$#i', $u)
            ));

            Log::info("Zora $engine candidates", [
                'product_id' => $product->id,
                'query'      => $query,
                'count'      => count($candidates),
                'urls'       => array_slice($candidates, 0, 5),
            ]);

            if (empty($candidates)) return null;

            $bestUrl = null; $bestScore = -999;

            foreach ($candidates as $u) {
                [$s, $why] = $this->computeMatchScore($u, '', $product);
                Log::debug("Zora $engine url score", ['product_id' => $product->id, 'url' => $u, 'score' => $s, 'why' => $why]);
                if ($s > $bestScore) { $bestScore = $s; $bestUrl = $u; }
                if ($s >= self::SCORE_ACCEPT) {
                    Log::info("Zora found via $engine", ['product_id' => $product->id, 'url' => $u, 'score' => $s]);
                    return $u;
                }
            }

            if ($bestUrl && $bestScore >= self::SCORE_FALLBACK) {
                Log::info("Zora fallback via $engine", ['product_id' => $product->id, 'url' => $bestUrl, 'score' => $bestScore]);
                return $bestUrl;
            }

        } catch (\Throwable $e) {
            Log::warning("Zora $engine exception", [
                'product_id' => $product->id,
                'query'      => $query,
                'message'    => $e->getMessage(),
            ]);
        }

        return null;
    }

    protected function extractZoraProductUrls(string $html): array
    {
        $urls = []; $seen = [];
        $hardBlock = ['/cart', '/wishlist', '/account', '/checkout'];

        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $m1);
        preg_match_all('/(?:https?:\/\/)?(?:www\.)?zora\.bg\/product\/([^"\'&\s<>\\\\\/][^"\'&\s<>\\\\]*)/i', $html, $m2);

        $hrefs = $m1[1] ?? [];

        foreach (($m2[0] ?? []) as $raw) {
            $raw = trim((string) $raw);
            if (!Str::startsWith($raw, 'http')) {
                $raw = 'https://' . ltrim($raw, '/');
            }
            $hrefs[] = $raw;
        }

        foreach ($hrefs as $href) {
            $href = html_entity_decode(trim((string) $href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($href === '') continue;

            $isZora     = Str::contains($href, 'zora.bg');
            $isProduct  = Str::contains($href, '/product/');
            $isRelative = Str::startsWith($href, '/product/') || Str::startsWith($href, 'product/');

            if (!$isZora && !$isRelative) continue;
            if (!$isProduct) continue;

            $abs  = $this->cleanUrl($this->makeAbsoluteUrl($href, 'https://zora.bg'));
            $path = parse_url($abs, PHP_URL_PATH) ?? '';
            if ($path === '' || $path === '/') continue;

            $host = parse_url($abs, PHP_URL_HOST) ?? '';
            if (!Str::contains($host, 'zora.bg')) continue;

            $skip = false;
            foreach ($hardBlock as $b) {
                if (Str::contains($abs, $b)) { $skip = true; break; }
            }
            if ($skip) continue;

            if (!isset($seen[$abs])) { $seen[$abs] = true; $urls[] = $abs; }
        }

        return $urls;
    }

    // ================================================================
    // SCORING
    // ================================================================

    protected function computeMatchScore(string $url, string $html, Product $product): array
    {
        $score = 0; $why = [];

        $primary = $this->getPrimaryIdentifier($product);
        $sku     = trim((string) ($product->sku ?? ''));
        $ean     = preg_replace('/\D+/', '', (string) ($product->ean ?? ''));
        $brand   = mb_strtolower(Str::ascii((string) $product->brand));
        $name    = mb_strtolower(Str::ascii((string) $product->name));

        // ── 1. URL score ──────────────────────────────────────────────
        $uNorm = mb_strtolower(Str::ascii($url));
        $uFlat = preg_replace('/[^a-z0-9]/', '', $uNorm);

        $primaryFoundInUrl = $primary && $this->containsExactIdentifier($uNorm, $primary);
        $skuFoundInUrl     = $sku     && $this->containsExactIdentifier($uNorm, $sku);
        $eanFoundInUrl     = strlen($ean) >= 8 && str_contains($uFlat, $ean);

        if ($primaryFoundInUrl) { $score += 55; $why[] = 'url_primary'; }
        if ($skuFoundInUrl)     { $score += 40; $why[] = 'url_sku'; }
        if ($eanFoundInUrl)     { $score += 35; $why[] = 'url_ean'; }

        // Fuzzy prefix match в URL (напр. FTXA20CW → ftxa20c в URL)
        if (!$primaryFoundInUrl && !$skuFoundInUrl && $primary) {
            $pFlat = preg_replace('/[^a-z0-9]/', '', mb_strtolower(Str::ascii($primary)));
            for ($len = min(strlen($pFlat), 8); $len >= 6; $len--) {
                if (str_contains($uFlat, substr($pFlat, 0, $len))) {
                    $score += 30; $why[] = 'url_fuzzy:' . substr($pFlat, 0, $len); break;
                }
            }
        }

        foreach (array_filter(preg_split('/\s+/u', "$brand $name"), fn ($t) => strlen($t) >= 3) as $t) {
            if (str_contains($uNorm, $t)) { $score += 5; $why[] = 'url_token:' . $t; }
        }

        if (!$primaryFoundInUrl && !$skuFoundInUrl && !$eanFoundInUrl) {
            foreach (['bundle', 'komplekt', 'set-', 'paket-', 'filter', 'aksesoar'] as $b) {
                if (str_contains($uNorm, $b)) { $score -= 18; $why[] = 'url_penalty:' . $b; }
            }
        }

        // ── 2. Page content score ─────────────────────────────────────
        if ($html !== '') {
            $title = ''; $h1 = '';
            if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) $title = strip_tags($m[1]);
            if (preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $html, $m))       $h1    = strip_tags($m[1]);

            $prio     = mb_strtolower(Str::ascii("$title $h1 $url"));
            $prioFlat = preg_replace('/[^a-z0-9]/', '', $prio);
            $bodyText = mb_strtolower(Str::ascii(strip_tags(mb_substr($html, 0, 200000))));
            $bodyFlat = preg_replace('/[^a-z0-9]/', '', $bodyText);

            $primaryFoundInPage = $primary && (
                $this->containsExactIdentifier($prio, $primary) ||
                $this->containsExactIdentifier($bodyText, $primary)
            );

            if ($primary) {
                if ($this->containsExactIdentifier($prio, $primary))         { $score += 50; $why[] = 'title_primary'; }
                elseif ($this->containsExactIdentifier($bodyText, $primary)) { $score += 30; $why[] = 'body_primary'; }
            }
            if ($sku) {
                if ($this->containsExactIdentifier($prio, $sku))             { $score += 35; $why[] = 'title_sku'; }
                elseif ($this->containsExactIdentifier($bodyText, $sku))     { $score += 20; $why[] = 'body_sku'; }
            }
            if (strlen($ean) >= 8) {
                if (str_contains($prioFlat, $ean))                           { $score += 30; $why[] = 'title_ean'; }
                elseif (str_contains($bodyFlat, $ean))                       { $score += 18; $why[] = 'body_ean'; }
            }

            foreach ($this->extractStructuredIds($html) as $sid) {
                if ($primary && $this->containsExactIdentifier($sid, $primary)) { $score += 35; $why[] = 'jsonld_primary'; break; }
                if ($sku     && $this->containsExactIdentifier($sid, $sku))     { $score += 25; $why[] = 'jsonld_sku';     break; }
                if (strlen($ean) >= 8 && str_contains(preg_replace('/\D/', '', mb_strtolower($sid)), $ean)) { $score += 20; $why[] = 'jsonld_ean'; break; }
            }

            $tokens = array_values(array_filter(preg_split('/\s+/u', "$brand $name"), fn ($t) => strlen(trim($t)) >= 3));
            $hitsPrio = 0; $hitsBody = 0;
            foreach ($tokens as $t) {
                if (str_contains($prio, $t))     $hitsPrio++;
                if (str_contains($bodyText, $t)) $hitsBody++;
            }
            $score += min(40, $hitsPrio * 10 + $hitsBody * 3);
            if ($hitsPrio >= 2) $why[] = "title_tokens:{$hitsPrio}";
            if ($hitsBody >= 3) $why[] = "body_tokens:{$hitsBody}";

            if (!$primaryFoundInPage) {
                foreach (['аксесоар', 'консуматив', 'hepa', 'bundle'] as $b) {
                    if (str_contains($bodyText, mb_strtolower(Str::ascii($b)))) {
                        $score -= 12; $why[] = 'page_penalty:' . $b;
                    }
                }
            }
        }

        return [$score, array_values(array_unique($why))];
    }

    protected function extractStructuredIds(string $html): array
    {
        $out = [];
        preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $m);
        foreach (($m[1] ?? []) as $json) {
            $data = json_decode(html_entity_decode(trim((string) $json), ENT_QUOTES, 'UTF-8'), true);
            if (!is_array($data)) continue;
            $flat = $this->flattenArray($data);
            foreach (['sku', 'mpn', 'gtin', 'gtin13', 'gtin12', 'productID'] as $k) {
                foreach ((array) ($flat[$k] ?? []) as $v) {
                    $v = trim((string) $v); if ($v !== '') $out[] = $v;
                }
            }
        }
        foreach (['sku', 'mpn', 'gtin', 'ean'] as $k) {
            preg_match_all('/"' . $k . '"\s*:\s*"([^"]+)"/i', $html, $mx);
            foreach (($mx[1] ?? []) as $v) { $v = trim((string) $v); if ($v !== '') $out[] = $v; }
        }
        return array_values(array_unique($out));
    }

    protected function flattenArray(array $arr): array
    {
        $res = [];
        $walk = function ($node) use (&$res, &$walk) {
            if (!is_array($node)) return;
            foreach ($node as $k => $v) {
                if (is_array($v)) $walk($v); else $res[$k][] = $v;
            }
        };
        $walk($arr);
        return $res;
    }

    // ================================================================
    // QUERY BUILDER
    // ================================================================

    protected function buildProgressiveQueries(Product $product): array
    {
        $queries = []; $seen = [];

        $add = function (string $v) use (&$queries, &$seen) {
            $v = trim(preg_replace('/\s+/', ' ', $v));
            if ($v !== '' && $v !== '-' && !isset($seen[$v])) {
                $seen[$v] = true; $queries[] = $v;
            }
        };

        $brand   = trim((string) $product->brand);
        $name    = trim((string) $product->name);
        $sku     = trim((string) ($product->sku ?? ''));
        $ean     = trim((string) ($product->ean ?? ''));
        $primary = $this->getPrimaryIdentifier($product);

        // Ако model съдържа "/" — добави и двете части
        $rawModel   = trim((string) ($product->model ?? ''));
        $modelParts = [];
        if (str_contains($rawModel, '/')) {
            $modelParts = array_values(array_filter(array_map('trim', explode('/', $rawModel))));
        }

        if ($brand && $primary) $add("$brand $primary");
        if ($primary)           $add($primary);

        foreach ($modelParts as $part) {
            $p = $this->normalizeIdentifier($part);
            if ($brand) $add("$brand $p");
            $add($p);
        }

        foreach ($this->identifierVariants((string) $primary) as $v) {
            if ($brand) $add("$brand $v"); $add($v);
        }

        if ($sku) {
            if ($brand) $add("$brand $sku");
            $add($sku);
            foreach ($this->identifierVariants($sku) as $v) {
                if ($brand) $add("$brand $v"); $add($v);
            }
        }

        if ($ean && $ean !== '-') $add($ean);

        if ($brand && $name) {
            $parts = array_values(array_filter(preg_split('/\s+/u', $name), fn ($w) => mb_strlen($w) >= 2));
            $add($brand . ' ' . implode(' ', array_slice($parts, 0, 4)));
            $add($brand . ' ' . implode(' ', array_slice($parts, 0, 2)));
        }

        foreach ($this->prefixFragments((string) $primary) as $f) {
            if ($brand) $add("$brand $f"); $add($f);
        }

        if ($name)           $add($name);
        if ($brand && $name) $add("$brand $name");

        return $queries;
    }

    protected function prefixFragments(string $value): array
    {
        $v = preg_replace('/[^A-Z0-9]/', '', $this->normalizeIdentifier($value));
        if ($v === '') return [];
        $out = [];
        for ($i = min(10, strlen($v)); $i >= 4; $i--) $out[] = substr($v, 0, $i);
        if (strlen($v) >= 8) { $out[] = substr($v, 1, 6); $out[] = substr($v, -6); }
        return array_values(array_unique($out));
    }

    // ================================================================
    // PRIMARY IDENTIFIER
    // ================================================================

    protected function getPrimaryIdentifier(Product $product): ?string
    {
        $model = trim((string) ($this->effectiveModel($product) ?? ''));
        if ($model !== '' && $model !== '-') return $this->normalizeIdentifier($model);

        $sku = trim((string) ($product->sku ?? ''));
        if ($sku !== '' && $sku !== '-') return $this->normalizeIdentifier($sku);

        return null;
    }

    protected function effectiveModel(Product $product): ?string
    {
        $model = trim((string) ($product->model ?? ''));
        if ($model !== '' && $model !== '-') {
            // Ако моделът съдържа "/" (напр. FTXA20CW/RXA20A8) — вземи само първата част
            if (str_contains($model, '/')) {
                $parts = array_values(array_filter(array_map('trim', explode('/', $model))));
                $model = $parts[0] ?? $model;
            }
            return $this->normalizeIdentifier($model);
        }

        $name  = trim((string) ($product->name ?? ''));
        $brand = mb_strtolower(trim((string) ($product->brand ?? '')));
        if ($name === '') return null;

        $norm = $this->normalizeIdentifier($name);

        // Ако има "FTXA20CW/RXA20A8" в name — вземи само първата част
        if (preg_match('/\b([A-Z0-9]{4,}(?:\/[A-Z0-9]{4,})+)\b/u', $norm, $mx)) {
            $first = explode('/', $mx[1])[0];
            if (strlen(preg_replace('/[^A-Z0-9]/', '', $first)) >= 4) {
                return $first;
            }
        }

        if (preg_match('/\b([A-Z]{1,5}[\s\-]?\d{2,5}[A-Z0-9\.\/\-]{0,15})\b/u', $norm, $m)) {
            $c = trim((string) $m[1]);
            if ($c !== '' && mb_strtolower($c) !== $brand && preg_match('/\d/', $c) && strlen(preg_replace('/[^A-Z0-9]/', '', $c)) >= 3) {
                return $c;
            }
        }

        if (preg_match('/\b([A-Z]{1,5}\d{2,5}[A-Z0-9\.\/\-]{0,12})\b/u', $norm, $m)) {
            $c = trim((string) $m[1]);
            if ($c !== '' && mb_strtolower($c) !== $brand && preg_match('/\d/', $c) && strlen(preg_replace('/[^A-Z0-9]/', '', $c)) >= 4) {
                return $c;
            }
        }

        preg_match_all('/\b([A-Z0-9][A-Z0-9\/\.\-]{3,})\b/u', $norm, $ms);
        foreach (($ms[1] ?? []) as $c) {
            $c = trim((string) $c);
            if ($c === '' || mb_strtolower($c) === $brand || !preg_match('/\d/', $c)) continue;
            if (strlen(preg_replace('/[^A-Z0-9]/', '', $c)) >= 4) return $c;
        }

        return null;
    }

    // ================================================================
    // HTTP
    // ================================================================

    protected function fetchHtml(string $url, string $referer = ''): ?string
    {
        try {
            $resp = Http::timeout(20)->withHeaders($this->headers($referer ?: $url))->get($url);
            if (!$resp->successful()) {
                usleep(300000);
                $resp = Http::timeout(20)->withHeaders($this->headers($referer ?: $url))->get($url);
                if (!$resp->successful()) return null;
            }
            return $resp->body();
        } catch (\Throwable) { return null; }
    }

    protected function warmupZora(): void
    {
        if (static::$zoraWarmedUp) return;
        $cookieFile = storage_path('app/zora_cookies.txt');
        if (!is_dir(dirname($cookieFile))) @mkdir(dirname($cookieFile), 0777, true);
        if (!file_exists($cookieFile)) @touch($cookieFile);
        shell_exec(sprintf(
            '/usr/bin/curl -s -o /dev/null -L --max-time 20 --cookie-jar %s --cookie %s -H %s -H %s -H %s %s',
            escapeshellarg($cookieFile), escapeshellarg($cookieFile),
            escapeshellarg('User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
            escapeshellarg('Accept-Language: bg-BG,bg;q=0.9,en-US;q=0.8,en;q=0.7'),
            escapeshellarg('Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'),
            escapeshellarg('https://zora.bg/')
        ));
        static::$zoraWarmedUp = true;
    }

    protected function fetchZoraHtml(string $url): ?string
    {
        $this->warmupZora();
        $cookieFile = storage_path('app/zora_cookies.txt');

        $cmd = sprintf(
            '/usr/bin/curl -s -L --max-time 25 --cookie-jar %s --cookie %s -H %s -H %s -H %s -H %s -w "HTTPSTATUS:%%{http_code}" %s',
            escapeshellarg($cookieFile), escapeshellarg($cookieFile),
            escapeshellarg('User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
            escapeshellarg('Accept-Language: bg-BG,bg;q=0.9,en-US;q=0.8,en;q=0.7'),
            escapeshellarg('Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'),
            escapeshellarg('Referer: https://zora.bg/'),
            escapeshellarg($url)
        );

        $out    = (string) shell_exec($cmd);
        preg_match('/HTTPSTATUS:(\d+)$/', $out, $m);
        $status = (int) ($m[1] ?? 0);
        $body   = (string) preg_replace('/HTTPSTATUS:\d+$/', '', $out);

        if ($status < 200 || $status >= 300 || empty(trim($body))) {
            usleep(800000);
            $out    = (string) shell_exec($cmd);
            preg_match('/HTTPSTATUS:(\d+)$/', $out, $m);
            $status = (int) ($m[1] ?? 0);
            $body   = (string) preg_replace('/HTTPSTATUS:\d+$/', '', $out);
        }

        return ($status >= 200 && $status < 300 && !empty(trim($body))) ? $body : null;
    }

    // ================================================================
    // HELPERS
    // ================================================================

    protected function normalizeLookalikeChars(string $value): string
    {
        return strtr(trim($value), [
            'А' => 'A', 'В' => 'B', 'С' => 'C', 'Е' => 'E', 'Н' => 'H',
            'К' => 'K', 'М' => 'M', 'О' => 'O', 'Р' => 'P', 'Т' => 'T',
            'Х' => 'X', 'У' => 'Y',
            'а' => 'a', 'в' => 'b', 'с' => 'c', 'е' => 'e', 'н' => 'h',
            'к' => 'k', 'м' => 'm', 'о' => 'o', 'р' => 'p', 'т' => 't',
            'х' => 'x', 'у' => 'y',
        ]);
    }

    protected function normalizeIdentifier(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtoupper(Str::ascii($this->normalizeLookalikeChars($value)))));
    }

    protected function identifierVariants(string $id): array
    {
        $id = $this->normalizeIdentifier((string) $id);
        if ($id === '' || $id === '-') return [];

        $variants = [
            $id,
            str_replace('/', '',  $id), str_replace('/', '-', $id), str_replace('/', ' ', $id),
            str_replace('.', '',  $id), str_replace('.', '-', $id), str_replace('.', ' ', $id),
            str_replace(['/', '.'], '',  $id),
            str_replace(['/', '.'], '-', $id),
            str_replace(['/', '.'], ' ', $id),
        ];
        $flat = preg_replace('/[^A-Z0-9]/', '', $id);
        if ($flat !== '') $variants[] = $flat;

        return array_values(array_unique(array_filter($variants)));
    }

    protected function containsExactIdentifier(string $text, string $identifier): bool
    {
        $tNorm = $this->normalizeIdentifier($text);
        $tFlat = preg_replace('/[^A-Z0-9]/', '', $tNorm);

        foreach ($this->identifierVariants($identifier) as $v) {
            $vNorm = $this->normalizeIdentifier($v);
            $vFlat = preg_replace('/[^A-Z0-9]/', '', $vNorm);
            if ($vNorm !== '' && str_contains($tNorm, $vNorm)) return true;
            if ($vFlat !== '' && str_contains($tFlat, $vFlat)) return true;

            // Fuzzy prefix: ако identifier е >= 6 символа, опитай съкратен prefix
            if (strlen($vFlat) >= 6) {
                for ($len = strlen($vFlat) - 1; $len >= 6; $len--) {
                    $prefix = substr($vFlat, 0, $len);
                    if (str_contains($tFlat, $prefix)) return true;
                }
            }
        }
        return false;
    }

    protected function defaultStoreUrl(string $name): string
    {
        return match ($name) {
            'Pazaruvaj'    => 'https://www.pazaruvaj.com',
            'Technopolis'  => 'https://www.technopolis.bg',
            'Technomarket' => 'https://www.technomarket.bg',
            'Zora'         => 'https://zora.bg',
            default        => '',
        };
    }

    protected function makeAbsoluteUrl(string $url, string $base): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) return $url;
        return rtrim($base, '/') . '/' . ltrim($url, '/');
    }

    protected function cleanUrl(string $url): string
    {
        $url   = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url   = preg_replace('/[\s"<>\'\]\)]+$/', '', $url);
        $parts = parse_url(trim($url));
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return trim($url);
        return $parts['scheme'] . '://' . $parts['host'] . ($parts['path'] ?? '');
    }

    protected function headers(string $referer): array
    {
        return [
            'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
            'Accept-Language' => 'bg-BG,bg;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Cache-Control'   => 'no-cache',
            'Pragma'          => 'no-cache',
            'Referer'         => $referer,
        ];
    }
}