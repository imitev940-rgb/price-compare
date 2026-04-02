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
            'Techmart'     => fn () => $this->searchTechmartUrl($product),
            'Tehnomix'     => fn () => $this->searchTehnomixUrl($product),
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
                    ->where('store_id', $store->id)
                    ->first();

                if ($existing && ! $overwrite) {
                    continue;
                }

                $url = $resolver();

                if (! $url) {
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

    // ================================================================
    // PAZARUVAJ
    // ================================================================

    protected function searchPazaruvajUrl(Product $product): ?string
    {
        $bestUrl = null;
        $bestScore = -999;

        foreach (array_slice($this->buildProgressiveQueries($product), 0, 16) as $query) {
            try {
                $resp = Http::timeout(20)
                    ->withHeaders($this->headers('https://www.pazaruvaj.com/'))
                    ->get('https://www.pazaruvaj.com/CategorySearch.php?st=' . urlencode($query));

                if (! $resp->successful()) {
                    continue;
                }

                preg_match_all('/https:\/\/www\.pazaruvaj\.com\/p\/[^"\'<>\s]+/i', $resp->body(), $m);

                foreach (array_values(array_unique($m[0] ?? [])) as $candidate) {
                    $u = $this->cleanUrl($candidate);

                    [$s] = $this->computeMatchScore($u, '', $product);

                    if ($s > $bestScore) {
                        $bestScore = $s;
                        $bestUrl = $u;
                    }

                    if ($s >= self::SCORE_ACCEPT) {
                        $html = $this->fetchHtml($u, 'https://www.pazaruvaj.com/');
                        [$ps] = $this->computeMatchScore($u, (string) $html, $product);

                        if ($ps >= self::SCORE_ACCEPT) {
                            Log::info('Pazaruvaj found', [
                                'product_id' => $product->id,
                                'url'        => $u,
                                'score'      => $ps,
                            ]);

                            return $u;
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        if ($bestUrl && $bestScore >= self::SCORE_FALLBACK) {
            $html = $this->fetchHtml($bestUrl, 'https://www.pazaruvaj.com/');
            [$ps] = $this->computeMatchScore($bestUrl, (string) $html, $product);

            if ($ps >= self::SCORE_FALLBACK) {
                Log::info('Pazaruvaj fallback', [
                    'product_id' => $product->id,
                    'url'        => $bestUrl,
                    'score'      => $ps,
                ]);

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
        $bestUrl   = null;
        $bestScore = -999;

        $primary = $this->getPrimaryIdentifier($product);
        $sku     = trim((string) ($product->sku ?? ''));

        foreach (array_slice($this->buildProgressiveQueries($product), 0, 14) as $query) {
            $searchUrls = [
                'https://www.technopolis.bg/bg/search?query=' . urlencode($query),
                'https://www.technopolis.bg/bg/search/' . Str::slug(Str::ascii($query), '-') . '?query=' . urlencode($query),
            ];

            foreach ($searchUrls as $searchUrl) {
                $html = $this->fetchHtml($searchUrl, 'https://www.technopolis.bg/');
                if (! $html) {
                    continue;
                }

                foreach ($this->extractTechnopolisProductUrls($html) as $u) {
                    [$s] = $this->computeMatchScore($u, '', $product);

                    if ($s > $bestScore) {
                        $bestScore = $s;
                        $bestUrl   = $u;
                    }

                    if ($s >= self::SCORE_URL_HINT) {
                        $pageHtml = $this->fetchHtml($u, 'https://www.technopolis.bg/');
                        if (! $pageHtml) {
                            continue;
                        }

                        if ($primary || $sku) {
                            $identifier = $primary ?: $sku;
                            $uSlug      = preg_replace('/[^a-z0-9]/', '', mb_strtolower($u));
                            $idFlat     = preg_replace('/[^a-z0-9]/', '', mb_strtolower($identifier));

                            $foundMatch = false;
                            if ($idFlat !== '') {
                                if (str_contains($uSlug, $idFlat)) {
                                    $foundMatch = true;
                                }
                                if (! $foundMatch) {
                                    for ($len = min(strlen($idFlat) - 1, 8); $len >= 5; $len--) {
                                        $prefix = substr($idFlat, 0, $len);
                                        $pos    = strpos($uSlug, $prefix);
                                        if ($pos !== false) {
                                            $after = $pos + $len;
                                            if ($after >= strlen($uSlug) || ! ctype_alnum($uSlug[$after])) {
                                                $foundMatch = true;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }

                            if (! $foundMatch) {
                                Log::debug('Technopolis skip wrong model', [
                                    'product_id' => $product->id,
                                    'url'        => $u,
                                    'expected'   => $idFlat,
                                ]);
                                continue;
                            }
                        }

                        [$ps, $why] = $this->computeMatchScore($u, $pageHtml, $product);

                        Log::debug('Technopolis candidate', [
                            'product_id' => $product->id,
                            'url'        => $u,
                            'score'      => $ps,
                            'why'        => $why,
                        ]);

                        if ($ps > $bestScore) {
                            $bestScore = $ps;
                            $bestUrl   = $u;
                        }

                        if ($ps >= self::SCORE_ACCEPT) {
                            Log::info('Technopolis found', [
                                'product_id' => $product->id,
                                'url'        => $u,
                                'score'      => $ps,
                            ]);

                            return $u;
                        }
                    }
                }
            }
        }

        if ($bestUrl && $bestScore >= self::SCORE_FALLBACK) {
            if ($primary || $sku) {
                $identifier = $primary ?: $sku;
                $uSlug      = preg_replace('/[^a-z0-9]/', '', mb_strtolower($bestUrl));
                $idFlat     = preg_replace('/[^a-z0-9]/', '', mb_strtolower($identifier));

                $foundMatch = false;
                if ($idFlat !== '') {
                    if (str_contains($uSlug, $idFlat)) {
                        $foundMatch = true;
                    }
                    if (! $foundMatch) {
                        for ($len = min(strlen($idFlat) - 1, 8); $len >= 5; $len--) {
                            $prefix = substr($idFlat, 0, $len);
                            $pos    = strpos($uSlug, $prefix);
                            if ($pos !== false) {
                                $after = $pos + $len;
                                if ($after >= strlen($uSlug) || ! ctype_alnum($uSlug[$after])) {
                                    $foundMatch = true;
                                    break;
                                }
                            }
                        }
                    }
                }

                if (! $foundMatch) {
                    Log::info('Technopolis fallback rejected wrong model', [
                        'product_id' => $product->id,
                        'url'        => $bestUrl,
                        'expected'   => $idFlat,
                    ]);
                    return null;
                }
            }

            Log::info('Technopolis fallback', [
                'product_id' => $product->id,
                'url'        => $bestUrl,
                'score'      => $bestScore,
            ]);

            return $bestUrl;
        }

        return null;
    }

    protected function extractTechnopolisProductUrls(string $html): array
    {
        $urls = [];
        $seen = [];

        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $m1);
        preg_match_all('/"url"\s*:\s*"([^"]+)"/i', $html, $m2);
        preg_match_all('/data-url=["\']([^"\']+)["\']/i', $html, $m3);

        foreach (array_merge($m1[1] ?? [], $m2[1] ?? [], $m3[1] ?? []) as $href) {
            $href = str_replace('\/', '/', (string) $href);
            $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($href === '') {
                continue;
            }

            if (! Str::contains($href, 'technopolis.bg') && ! Str::startsWith($href, '/')) {
                continue;
            }

            $abs  = $this->cleanUrl($this->makeAbsoluteUrl($href, 'https://www.technopolis.bg'));
            $path = parse_url($abs, PHP_URL_PATH) ?? '';

            if (! preg_match('#/p/\d+#i', $path)) {
                continue;
            }

            if (preg_match('#/(search|compare|cart|wishlist|account|checkout)#i', $abs)) {
                continue;
            }

            if (! isset($seen[$abs])) {
                $seen[$abs] = true;
                $urls[] = $abs;
            }
        }

        return $urls;
    }

    // ================================================================
    // TECHNOMARKET
    // ================================================================

    protected function searchTechnomarketUrl(Product $product): ?string
    {
        $bestUrl   = null;
        $bestScore = -999;

        $primary = $this->getPrimaryIdentifier($product);
        $sku     = trim((string) ($product->sku ?? ''));

        foreach (array_slice($this->buildProgressiveQueries($product), 0, 16) as $query) {
            $searchUrl = 'https://www.technomarket.bg/search?query=' . urlencode($query);

            Log::debug('Technomarket search request', [
                'product_id' => $product->id,
                'query'      => $query,
                'url'        => $searchUrl,
            ]);

            $html = $this->fetchHtml($searchUrl, 'https://www.technomarket.bg/');

            if (! $html) {
                Log::debug('Technomarket search empty html', [
                    'product_id' => $product->id,
                    'query'      => $query,
                ]);
                continue;
            }

            foreach ($this->extractTechnomarketProductUrls($html) as $u) {
                [$s] = $this->computeMatchScore($u, '', $product);

                if ($s > $bestScore) {
                    $bestScore = $s;
                    $bestUrl   = $u;
                }

                if ($s >= self::SCORE_URL_HINT) {
                    $pageHtml = $this->fetchHtml($u, 'https://www.technomarket.bg/');
                    if (! $pageHtml) {
                        continue;
                    }

                    if ($primary || $sku) {
                        $identifier = $primary ?: $sku;
                        $uSlug      = preg_replace('/[^a-z0-9]/', '', mb_strtolower(basename(parse_url($u, PHP_URL_PATH) ?? '')));
                        $idFlat     = preg_replace('/[^a-z0-9]/', '', mb_strtolower($identifier));

                        $foundMatch = false;
                        if ($idFlat !== '') {
                            if (str_contains($uSlug, $idFlat)) {
                                $foundMatch = true;
                            }
                            if (! $foundMatch) {
                                for ($len = min(strlen($idFlat) - 1, 8); $len >= 5; $len--) {
                                    $prefix = substr($idFlat, 0, $len);
                                    $pos    = strpos($uSlug, $prefix);
                                    if ($pos !== false) {
                                        $after = $pos + $len;
                                        if ($after >= strlen($uSlug) || ! ctype_alnum($uSlug[$after])) {
                                            $foundMatch = true;
                                            break;
                                        }
                                    }
                                }
                            }
                        }

                        if (! $foundMatch) {
                            Log::debug('Technomarket skip wrong model', [
                                'product_id' => $product->id,
                                'url'        => $u,
                                'expected'   => $idFlat,
                            ]);
                            continue;
                        }
                    }

                    [$ps, $why] = $this->computeMatchScore($u, $pageHtml, $product);

                    Log::debug('Technomarket candidate', [
                        'product_id' => $product->id,
                        'url'        => $u,
                        'score'      => $ps,
                        'why'        => $why,
                    ]);

                    if ($ps > $bestScore) {
                        $bestScore = $ps;
                        $bestUrl   = $u;
                    }

                    if ($ps >= self::SCORE_ACCEPT) {
                        Log::info('Technomarket found', [
                            'product_id' => $product->id,
                            'url'        => $u,
                            'score'      => $ps,
                        ]);

                        return $u;
                    }
                }
            }
        }

        $googleUrl = $this->searchViaGoogle($product, 'technomarket.bg');
        if ($googleUrl) {
            $pageHtml = $this->fetchHtml($googleUrl, 'https://www.technomarket.bg/');
            [$ps] = $this->computeMatchScore($googleUrl, (string) $pageHtml, $product);

            Log::info('Technomarket Google fallback candidate', [
                'product_id' => $product->id,
                'url'        => $googleUrl,
                'score'      => $ps,
            ]);

            if ($ps >= self::SCORE_FALLBACK) {
                return $googleUrl;
            }
        }

        if ($bestUrl && $bestScore >= self::SCORE_FALLBACK) {
            if ($primary || $sku) {
                $identifier = $primary ?: $sku;
                $uSlug      = preg_replace('/[^a-z0-9]/', '', mb_strtolower(basename(parse_url($bestUrl, PHP_URL_PATH) ?? '')));
                $idFlat     = preg_replace('/[^a-z0-9]/', '', mb_strtolower($identifier));

                $foundMatch = false;
                if ($idFlat !== '') {
                    if (str_contains($uSlug, $idFlat)) {
                        $foundMatch = true;
                    }
                    if (! $foundMatch) {
                        for ($len = min(strlen($idFlat) - 1, 8); $len >= 5; $len--) {
                            $prefix = substr($idFlat, 0, $len);
                            $pos    = strpos($uSlug, $prefix);
                            if ($pos !== false) {
                                $after = $pos + $len;
                                if ($after >= strlen($uSlug) || ! ctype_alnum($uSlug[$after])) {
                                    $foundMatch = true;
                                    break;
                                }
                            }
                        }
                    }
                }

                if (! $foundMatch) {
                    Log::info('Technomarket fallback rejected wrong model', [
                        'product_id' => $product->id,
                        'url'        => $bestUrl,
                        'expected'   => $idFlat,
                    ]);
                    return null;
                }
            }

            Log::info('Technomarket fallback', [
                'product_id' => $product->id,
                'url'        => $bestUrl,
                'score'      => $bestScore,
            ]);

            return $bestUrl;
        }

        return null;
    }

    protected function extractTechnomarketProductUrls(string $html): array
    {
        $urls = [];
        $seen = [];
        $hardBlock = ['/cart', '/wishlist', '/account', '/checkout', '/search', '/category', '/brand', '/filter'];
        $imageExts = ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg', '.ico', '.pdf'];

        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $m1);
        preg_match_all('/"url"\s*:\s*"([^"]+)"/i', $html, $m2);

        foreach (array_merge($m1[1] ?? [], $m2[1] ?? []) as $href) {
            $href = html_entity_decode(trim((string) $href), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($href === '') {
                continue;
            }

            if (! Str::contains($href, 'technomarket.bg') && ! Str::startsWith($href, '/')) {
                continue;
            }

            $abs  = $this->cleanUrl($this->makeAbsoluteUrl($href, 'https://www.technomarket.bg'));
            $path = parse_url($abs, PHP_URL_PATH) ?? '';

            if ($path === '' || $path === '/') {
                continue;
            }

            $skip = false;

            foreach ($hardBlock as $b) {
                if (Str::contains($abs, $b)) {
                    $skip = true;
                    break;
                }
            }

            foreach ($imageExts as $e) {
                if (Str::endsWith(mb_strtolower($abs), $e)) {
                    $skip = true;
                    break;
                }
            }

            if ($skip) {
                continue;
            }

            $segments = array_filter(explode('/', trim($path, '/')));
            if (count($segments) < 2) {
                continue;
            }

            $slug = trim((string) basename($path), '/');
            if ($slug === '') {
                continue;
            }

            if (! preg_match('/\d/', $slug) && ! preg_match('/[a-z]{2,}\d{2,}/i', $slug)) {
                continue;
            }

            if (! isset($seen[$abs])) {
                $seen[$abs] = true;
                $urls[] = $abs;
            }
        }

        return $urls;
    }

    // ================================================================
    // TECHMART
    // ================================================================

    protected function searchTechmartUrl(Product $product): ?string
    {
        $bestUrl   = null;
        $bestScore = -999;

        foreach ($this->buildTechmartDirectCandidates($product) as $u) {
            $pageHtml = $this->fetchHtml($u, 'https://techmart.bg/');
            if (! $pageHtml) {
                continue;
            }

            [$ps, $why] = $this->computeMatchScore($u, $pageHtml, $product);

            Log::debug('Techmart direct candidate', [
                'product_id' => $product->id,
                'url'        => $u,
                'score'      => $ps,
                'why'        => $why,
            ]);

            if ($ps > $bestScore) {
                $bestScore = $ps;
                $bestUrl   = $u;
            }

            if ($ps >= self::SCORE_ACCEPT) {
                Log::info('Techmart found via direct slug', [
                    'product_id' => $product->id,
                    'url'        => $u,
                    'score'      => $ps,
                ]);

                return $u;
            }
        }

        $googleUrl = $this->searchViaGoogle($product, 'techmart.bg');
        if ($googleUrl) {
            $pageHtml = $this->fetchHtml($googleUrl, 'https://techmart.bg/');
            [$ps] = $this->computeMatchScore($googleUrl, (string) $pageHtml, $product);

            Log::info('Techmart Google fallback candidate', [
                'product_id' => $product->id,
                'url'        => $googleUrl,
                'score'      => $ps,
            ]);

            if ($ps >= self::SCORE_FALLBACK) {
                return $googleUrl;
            }
        }

        if ($bestUrl && $bestScore >= self::SCORE_FALLBACK) {
            Log::info('Techmart fallback', [
                'product_id' => $product->id,
                'url'        => $bestUrl,
                'score'      => $bestScore,
            ]);

            return $bestUrl;
        }

        return null;
    }

    protected function sanitizeTechmartSearchTerm(string $query): string
    {
        $query = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $query);
        $query = trim(preg_replace('/\s+/', ' ', (string) $query));

        return $query;
    }

    protected function extractTechmartProductUrls(string $html): array
    {
        $urls      = [];
        $seen      = [];
        $hardBlock = [
            '/cart', '/wishlist', '/account', '/checkout',
            '/wp-login', '/?s=', '/tag/', '/page/', '/wp-admin',
        ];
        $imageExts = ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg', '.ico', '.pdf'];

        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $m1);
        preg_match_all('/"permalink"\s*:\s*"([^"]+)"/i', $html, $m2);
        preg_match_all('/"url"\s*:\s*"([^"]+)"/i', $html, $m3);

        foreach (array_merge($m1[1] ?? [], $m2[1] ?? [], $m3[1] ?? []) as $href) {
            $href = str_replace('\/', '/', (string) $href);
            $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($href === '') {
                continue;
            }

            if (! Str::contains($href, 'techmart.bg') && ! Str::startsWith($href, '/')) {
                continue;
            }

            $abs  = $this->cleanUrl($this->makeAbsoluteUrl($href, 'https://techmart.bg'));
            $path = parse_url($abs, PHP_URL_PATH) ?? '';

            if ($path === '' || $path === '/') {
                continue;
            }

            $skip = false;

            foreach ($hardBlock as $b) {
                if (Str::contains($abs, $b)) {
                    $skip = true;
                    break;
                }
            }

            foreach ($imageExts as $e) {
                if (Str::endsWith(mb_strtolower($abs), $e)) {
                    $skip = true;
                    break;
                }
            }

            if ($skip) {
                continue;
            }

            $slug = trim(basename($path), '/');
            if ($slug === '' || substr_count($slug, '-') < 1) {
                continue;
            }

            if (! isset($seen[$abs])) {
                $seen[$abs] = true;
                $urls[]     = $abs;
            }
        }

        return $urls;
    }

    protected function buildTechmartDirectCandidates(Product $product): array
    {
        $urls = [];
        $seen = [];

        $add = function (string $slug) use (&$urls, &$seen) {
            $slug = trim($slug, " \t\n\r\0\x0B-/");
            if ($slug === '' || isset($seen[$slug])) {
                return;
            }
            $seen[$slug] = true;
            $urls[] = 'https://techmart.bg/' . $slug;
        };

        $brand   = trim((string) $product->brand);
        $name    = trim((string) $product->name);
        $sku     = trim((string) ($product->sku ?? ''));
        $primary = trim((string) ($this->getPrimaryIdentifier($product) ?? ''));

        $nameSlugBul = $this->techmartSlugifyBulgarian($name);
        $brandSlug   = $this->techmartSlugifyBulgarian($brand);
        $tokens      = array_values(array_filter(explode('-', $nameSlugBul)));

        $skuVariants = array_values(array_unique(array_filter([
            $this->techmartSlugifyBulgarian($sku),
            $this->techmartSlugifyBulgarian(str_replace(['/', '.', '-', ' '], '', $sku)),
            $this->techmartSlugifyBulgarian(str_replace('/', '', $sku)),
            $this->techmartSlugifyBulgarian(str_replace('.', '', $sku)),
            $this->techmartSlugifyBulgarian($primary),
            $this->techmartSlugifyBulgarian(str_replace(['/', '.', '-', ' '], '', $primary)),
            $this->techmartSlugifyBulgarian(str_replace('/', '', $primary)),
        ])));

        $prefixes = [
            'furna-za-vgrazhdane', 'furna',
            'mikrovylnova-za-vgrazhdane', 'mikrovylnova-furna', 'mikrovylnova',
            'plot-za-vgrazhdane', 'plot',
            'induktsen-plot', 'elektricheski-plot',
            'sydomiqlna-za-vgrazhdane', 'sydomiqlna-45sm',
            'sydomiqlna-60sm', 'sydomiqlna',
            'peralnya', 'sushilnq', 'peralnya-sushilnq',
            'hladilnik', 'hladilnik-s-frizer',
            'frizer', 'vgrazhdan-hladilnik',
            'multikukyr', 'multikuker', 'multikukar', 'multicooker',
            'kafeavtomat', 'kafe-avtomat',
            'espreso-mashina', 'kapkova-kafemashina',
            'klimatik', 'invertoren-klimatik',
            'televizor', 'smart-televizor', 'oled-televizor', 'qled-televizor',
            'prakhosmukachka', 'robotizirana-prakhosmukachka',
            'boyler', 'termopot',
            'air-fryer', 'friturnik',
            'epilator', 'seshhoar', 'shteker',
            'mikser', 'blender', 'kuhnenski-robot',
            'toster', 'skara', 'ured',
            'monitor', 'printer', 'skaner',
        ];

        foreach ($prefixes as $prefix) {
            foreach ($skuVariants as $skuV) {
                if ($skuV === '') {
                    continue;
                }
                if ($brandSlug) {
                    $add($prefix . '-' . $brandSlug . '-' . $skuV);
                }
                $add($prefix . '-' . $skuV);
            }
        }

        for ($n = min(8, count($tokens)); $n >= 3; $n--) {
            $add(implode('-', array_slice($tokens, 0, $n)));
        }

        foreach ($skuVariants as $skuV) {
            if ($skuV === '') {
                continue;
            }
            if ($brandSlug) {
                $add($brandSlug . '-' . $skuV);
            }
            $add($skuV);
        }

        $nameTokens = array_values(array_filter(
            $tokens,
            fn ($t) => strlen($t) >= 3 && ! in_array($t, ['za', 'i', 'na', 'se', 'ot', 'do', 'pri', 'sus', 'vgrazhdane'])
        ));

        foreach ($prefixes as $prefix) {
            foreach ($skuVariants as $skuV) {
                if ($skuV === '') {
                    continue;
                }
                foreach ($nameTokens as $token) {
                    if ($token === $skuV || $token === $brandSlug) {
                        continue;
                    }
                    if ($brandSlug) {
                        $add($prefix . '-' . $brandSlug . '-' . $skuV . '-' . $token);
                    }
                }
            }
        }

        return $urls;
    }

    protected function techmartSlugifyBulgarian(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        $map = [
            'а' => 'a',  'б' => 'b',  'в' => 'v',  'г' => 'g',
            'д' => 'd',  'е' => 'e',  'ж' => 'zh', 'з' => 'z',
            'и' => 'i',  'й' => 'y',  'к' => 'k',  'л' => 'l',
            'м' => 'm',  'н' => 'n',  'о' => 'o',  'п' => 'p',
            'р' => 'r',  'с' => 's',  'т' => 't',  'у' => 'u',
            'ф' => 'f',  'х' => 'h',  'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sht','ъ' => 'y',  'ь' => '',
            'ю' => 'yu', 'я' => 'q',
        ];

        $value = strtr($value, $map);
        $value = Str::ascii($value);

        $value = str_replace('/', '', $value);

        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = preg_replace('/-+/', '-', (string) $value);

        return trim((string) $value, '-');
    }

    // ================================================================
    // TEHNOMIX
    // ================================================================

    protected function searchTehnomixUrl(Product $product): ?string
    {
        $bestUrl   = null;
        $bestScore = -999;

        $primary = $this->getPrimaryIdentifier($product);
        $sku     = trim((string) ($product->sku ?? ''));

        $extraQueries = [];
        foreach ([$primary, $sku] as $id) {
            if (! $id) {
                continue;
            }
            $flat = preg_replace('/[^A-Z0-9]/', '', $this->normalizeIdentifier($id));
            for ($len = min(strlen($flat), 7); $len >= 4; $len--) {
                $extraQueries[] = trim((string) $product->brand) . ' ' . substr($flat, 0, $len);
                $extraQueries[] = substr($flat, 0, $len);
            }
        }

        $queries = array_values(array_unique(array_merge(
            array_slice($this->buildProgressiveQueries($product), 0, 12),
            $extraQueries
        )));

        foreach ($queries as $query) {
            $html = $this->fetchHtml(
                'https://www.tehnomix.bg/catalogsearch/result/?q=' . urlencode($query),
                'https://www.tehnomix.bg/'
            );

            if (! $html) {
                continue;
            }

            foreach ($this->extractTehnomixProductUrls($html) as $u) {
                [$s] = $this->computeMatchScore($u, '', $product);

                if ($s > $bestScore) {
                    $bestScore = $s;
                    $bestUrl   = $u;
                }

                if ($s >= self::SCORE_URL_HINT) {
                    $pageHtml = $this->fetchHtml($u, 'https://www.tehnomix.bg/');
                    if (! $pageHtml) {
                        continue;
                    }

                    [$ps, $why] = $this->computeMatchScore($u, $pageHtml, $product);

                    if ($primary || $sku) {
                        $identifier = $primary ?: $sku;
                        $uSlug      = preg_replace('/[^a-z0-9]/', '', mb_strtolower(basename(parse_url($u, PHP_URL_PATH) ?? '')));
                        $idFlat     = preg_replace('/[^a-z0-9]/', '', mb_strtolower($identifier));

                        $foundMatch = false;
                        if ($idFlat !== '') {
                            if (str_contains($uSlug, $idFlat)) {
                                $foundMatch = true;
                            }
                            if (! $foundMatch) {
                                for ($len = min(strlen($idFlat) - 1, 8); $len >= 4; $len--) {
                                    $prefix = substr($idFlat, 0, $len);
                                    $pos    = strpos($uSlug, $prefix);
                                    if ($pos !== false) {
                                        $after = $pos + $len;
                                        if ($after >= strlen($uSlug) || ! ctype_alnum($uSlug[$after])) {
                                            $foundMatch = true;
                                            break;
                                        }
                                    }
                                }
                            }
                        }

                        if (! $foundMatch) {
                            Log::debug('Tehnomix skip wrong model', [
                                'product_id' => $product->id,
                                'url'        => $u,
                                'expected'   => $idFlat,
                            ]);
                            continue;
                        }
                    }

                    Log::debug('Tehnomix candidate', [
                        'product_id' => $product->id,
                        'url'        => $u,
                        'score'      => $ps,
                        'why'        => $why,
                    ]);

                    if ($ps > $bestScore) {
                        $bestScore = $ps;
                        $bestUrl   = $u;
                    }

                    if ($ps >= self::SCORE_ACCEPT) {
                        Log::info('Tehnomix found', [
                            'product_id' => $product->id,
                            'url'        => $u,
                            'score'      => $ps,
                        ]);

                        return $u;
                    }
                }
            }
        }

        $googleUrl = $this->searchViaGoogle($product, 'tehnomix.bg');
        if ($googleUrl) {
            Log::info('Tehnomix found via Google', [
                'product_id' => $product->id,
                'url'        => $googleUrl,
            ]);

            return $googleUrl;
        }

        if ($bestUrl && $bestScore >= self::SCORE_FALLBACK) {
            if ($primary || $sku) {
                $identifier = $primary ?: $sku;
                $uSlug      = preg_replace('/[^a-z0-9]/', '', mb_strtolower(basename(parse_url($bestUrl, PHP_URL_PATH) ?? '')));
                $idFlat     = preg_replace('/[^a-z0-9]/', '', mb_strtolower($identifier));

                $foundMatch = false;
                if ($idFlat !== '') {
                    if (str_contains($uSlug, $idFlat)) {
                        $foundMatch = true;
                    }
                    if (! $foundMatch) {
                        for ($len = min(strlen($idFlat) - 1, 8); $len >= 4; $len--) {
                            $prefix = substr($idFlat, 0, $len);
                            $pos    = strpos($uSlug, $prefix);
                            if ($pos !== false) {
                                $after = $pos + $len;
                                if ($after >= strlen($uSlug) || ! ctype_alnum($uSlug[$after])) {
                                    $foundMatch = true;
                                    break;
                                }
                            }
                        }
                    }
                }

                if (! $foundMatch) {
                    Log::info('Tehnomix fallback rejected wrong model', [
                        'product_id' => $product->id,
                        'url'        => $bestUrl,
                        'expected'   => $idFlat,
                    ]);
                    return null;
                }
            }

            Log::info('Tehnomix fallback', [
                'product_id' => $product->id,
                'url'        => $bestUrl,
                'score'      => $bestScore,
            ]);

            return $bestUrl;
        }

        return null;
    }

    protected function extractTehnomixProductUrls(string $html): array
    {
        $urls = [];
        $seen = [];

        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $m1);
        preg_match_all('/"url"\s*:\s*"([^"]+)"/i', $html, $m2);
        preg_match_all('/data-product-url=["\']([^"\']+)["\']/i', $html, $m3);

        foreach (array_merge($m1[1] ?? [], $m2[1] ?? [], $m3[1] ?? []) as $href) {
            $href = str_replace('\/', '/', (string) $href);
            $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($href === '') {
                continue;
            }

            if (! Str::contains($href, 'tehnomix.bg') && ! Str::startsWith($href, '/')) {
                continue;
            }

            $abs  = $this->cleanUrl($this->makeAbsoluteUrl($href, 'https://www.tehnomix.bg'));
            $path = parse_url($abs, PHP_URL_PATH) ?? '';

            if ($path === '' || $path === '/') {
                continue;
            }

            $bad = [
                '/catalogsearch', '/checkout', '/customer', '/wishlist',
                '/cart', '/brand', '/brands', '/blog', '/sales',
                '/promotions', '/contacts',
            ];

            $skip = false;
            foreach ($bad as $b) {
                if (Str::contains(mb_strtolower($path), mb_strtolower($b))) {
                    $skip = true;
                    break;
                }
            }

            if ($skip) {
                continue;
            }

            $slug = trim((string) basename($path), '/');
            if ($slug === '') {
                continue;
            }
            if (substr_count($slug, '-') < 2) {
                continue;
            }
            if (! preg_match('/\d/', $slug)) {
                continue;
            }

            if (! isset($seen[$abs])) {
                $seen[$abs] = true;
                $urls[] = $abs;
            }
        }

        return $urls;
    }

    // ================================================================
    // GOOGLE FALLBACK
    // ================================================================

    protected function searchViaGoogle(Product $product, string $site): ?string
    {
        $bestUrl   = null;
        $bestScore = -999;

        foreach (array_slice($this->buildProgressiveQueries($product), 0, 8) as $queryTerm) {
            $query = $queryTerm . ' site:' . $site;

            try {
                $html = $this->fetchHtml(
                    'https://www.google.com/search?q=' . urlencode($query),
                    'https://www.google.com/'
                );

                if (! $html) {
                    continue;
                }

                $candidates = $this->extractGoogleResultUrls($html, $site);

                foreach ($candidates as $u) {
                    [$s] = $this->computeMatchScore($u, '', $product);

                    if ($s > $bestScore) {
                        $bestScore = $s;
                        $bestUrl   = $u;
                    }

                    if ($s >= self::SCORE_URL_HINT) {
                        $pageHtml = $this->fetchHtml($u, 'https://www.google.com/');
                        [$ps] = $this->computeMatchScore($u, (string) $pageHtml, $product);

                        if ($ps > $bestScore) {
                            $bestScore = $ps;
                            $bestUrl   = $u;
                        }

                        if ($ps >= self::SCORE_ACCEPT) {
                            return $u;
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        if ($bestUrl && $bestScore >= self::SCORE_FALLBACK) {
            return $bestUrl;
        }

        return null;
    }

    protected function extractGoogleResultUrls(string $html, string $site): array
    {
        $urls = [];
        $seen = [];

        preg_match_all('/\/url\?q=([^"&]+)&/i', $html, $m1);
        foreach (($m1[1] ?? []) as $encodedUrl) {
            $decoded = urldecode(html_entity_decode((string) $encodedUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $u = $this->cleanUrl($decoded);
            if (Str::contains($u, $site) && ! $this->isBadSearchResultUrl($u) && ! isset($seen[$u])) {
                $seen[$u] = true;
                $urls[] = $u;
            }
        }

        preg_match_all('/https?:\/\/[^"\s<]+/i', $html, $m2);
        foreach (($m2[0] ?? []) as $rawUrl) {
            $u = $this->cleanUrl(html_entity_decode((string) $rawUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (Str::contains($u, $site) && ! $this->isBadSearchResultUrl($u) && ! isset($seen[$u])) {
                $seen[$u] = true;
                $urls[] = $u;
            }
        }

        return $urls;
    }

    protected function isBadSearchResultUrl(string $url): bool
    {
        $url = mb_strtolower($url);

        $blocked = [
            'google.com', '/search', '/catalogsearch', '/category', '/categories',
            '/brand', '/brands', '/cart', '/checkout', '/customer', '/account',
            '/wishlist', '/blog', '/news', '/contact', '/promo', '/promotions',
        ];

        foreach ($blocked as $part) {
            if (str_contains($url, $part)) {
                return true;
            }
        }

        return false;
    }

    // ================================================================
    // SCORING
    // ================================================================

    protected function computeMatchScore(string $url, string $html, Product $product): array
    {
        $score = 0;
        $why   = [];

        $primary = $this->getPrimaryIdentifier($product);
        $sku     = trim((string) ($product->sku ?? ''));
        $ean     = preg_replace('/\D+/', '', (string) ($product->ean ?? ''));
        $brand   = mb_strtolower(Str::ascii((string) $product->brand));
        $name    = mb_strtolower(Str::ascii((string) $product->name));

        $uNorm = mb_strtolower(Str::ascii($url));
        $uFlat = preg_replace('/[^a-z0-9]/', '', $uNorm);

        $primaryFoundInUrl = $primary && $this->containsExactIdentifier($uNorm, $primary);
        $skuFoundInUrl     = $sku && $this->containsExactIdentifier($uNorm, $sku);
        $eanFoundInUrl     = strlen($ean) >= 8 && str_contains($uFlat, $ean);

        if ($primaryFoundInUrl) {
            $score += 55;
            $why[] = 'url_primary';
        }
        if ($skuFoundInUrl) {
            $score += 40;
            $why[] = 'url_sku';
        }
        if ($eanFoundInUrl) {
            $score += 35;
            $why[] = 'url_ean';
        }

        if (! $primaryFoundInUrl && ! $skuFoundInUrl && $primary) {
            $pFlat = preg_replace('/[^a-z0-9]/', '', mb_strtolower(Str::ascii($primary)));
            for ($len = min(strlen($pFlat), 8); $len >= 6; $len--) {
                if (str_contains($uFlat, substr($pFlat, 0, $len))) {
                    $score += 30;
                    $why[] = 'url_fuzzy:' . substr($pFlat, 0, $len);
                    break;
                }
            }
        }

        foreach (array_filter(preg_split('/\s+/u', "$brand $name"), fn ($t) => strlen($t) >= 3) as $t) {
            if (str_contains($uNorm, $t)) {
                $score += 5;
                $why[] = 'url_token:' . $t;
            }
        }

        if (! $primaryFoundInUrl && ! $skuFoundInUrl && ! $eanFoundInUrl) {
            foreach (['bundle', 'komplekt', 'set-', 'paket-', 'filter', 'aksesoar'] as $b) {
                if (str_contains($uNorm, $b)) {
                    $score -= 18;
                    $why[] = 'url_penalty:' . $b;
                }
            }
        }

        if ($html !== '') {
            $title = '';
            $h1    = '';

            if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
                $title = strip_tags($m[1]);
            }
            if (preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $html, $m)) {
                $h1 = strip_tags($m[1]);
            }

            $prio     = mb_strtolower(Str::ascii("$title $h1 $url"));
            $prioFlat = preg_replace('/[^a-z0-9]/', '', $prio);
            $bodyText = mb_strtolower(Str::ascii(strip_tags(mb_substr($html, 0, 200000))));
            $bodyFlat = preg_replace('/[^a-z0-9]/', '', $bodyText);

            $primaryFoundInPage = $primary && (
                $this->containsExactIdentifier($prio, $primary) ||
                $this->containsExactIdentifier($bodyText, $primary)
            );

            if ($primary) {
                if ($this->containsExactIdentifier($prio, $primary)) {
                    $score += 50;
                    $why[] = 'title_primary';
                } elseif ($this->containsExactIdentifier($bodyText, $primary)) {
                    $score += 30;
                    $why[] = 'body_primary';
                }
            }

            if ($sku) {
                if ($this->containsExactIdentifier($prio, $sku)) {
                    $score += 35;
                    $why[] = 'title_sku';
                } elseif ($this->containsExactIdentifier($bodyText, $sku)) {
                    $score += 20;
                    $why[] = 'body_sku';
                }
            }

            if (strlen($ean) >= 8) {
                if (str_contains($prioFlat, $ean)) {
                    $score += 30;
                    $why[] = 'title_ean';
                } elseif (str_contains($bodyFlat, $ean)) {
                    $score += 18;
                    $why[] = 'body_ean';
                }
            }

            foreach ($this->extractStructuredIds($html) as $sid) {
                if ($primary && $this->containsExactIdentifier($sid, $primary)) {
                    $score += 35;
                    $why[] = 'jsonld_primary';
                    break;
                }
                if ($sku && $this->containsExactIdentifier($sid, $sku)) {
                    $score += 25;
                    $why[] = 'jsonld_sku';
                    break;
                }
                if (strlen($ean) >= 8 && str_contains(preg_replace('/\D/', '', mb_strtolower($sid)), $ean)) {
                    $score += 20;
                    $why[] = 'jsonld_ean';
                    break;
                }
            }

            $tokens   = array_values(array_filter(preg_split('/\s+/u', "$brand $name"), fn ($t) => strlen(trim($t)) >= 3));
            $hitsPrio = 0;
            $hitsBody = 0;

            foreach ($tokens as $t) {
                if (str_contains($prio, $t)) {
                    $hitsPrio++;
                }
                if (str_contains($bodyText, $t)) {
                    $hitsBody++;
                }
            }

            $score += min(40, $hitsPrio * 10 + $hitsBody * 3);
            if ($hitsPrio >= 2) {
                $why[] = "title_tokens:{$hitsPrio}";
            }
            if ($hitsBody >= 3) {
                $why[] = "body_tokens:{$hitsBody}";
            }

            if (! $primaryFoundInPage) {
                foreach (['аксесоар', 'консуматив', 'hepa', 'bundle'] as $b) {
                    if (str_contains($bodyText, mb_strtolower(Str::ascii($b)))) {
                        $score -= 12;
                        $why[] = 'page_penalty:' . $b;
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
            if (! is_array($data)) {
                continue;
            }

            $flat = $this->flattenArray($data);

            foreach (['sku', 'mpn', 'gtin', 'gtin13', 'gtin12', 'productID'] as $k) {
                foreach ((array) ($flat[$k] ?? []) as $v) {
                    $v = trim((string) $v);
                    if ($v !== '') {
                        $out[] = $v;
                    }
                }
            }
        }

        foreach (['sku', 'mpn', 'gtin', 'ean'] as $k) {
            preg_match_all('/"' . $k . '"\s*:\s*"([^"]+)"/i', $html, $mx);
            foreach (($mx[1] ?? []) as $v) {
                $v = trim((string) $v);
                if ($v !== '') {
                    $out[] = $v;
                }
            }
        }

        return array_values(array_unique($out));
    }

    protected function flattenArray(array $arr): array
    {
        $res  = [];
        $walk = function ($node) use (&$res, &$walk) {
            if (! is_array($node)) {
                return;
            }
            foreach ($node as $k => $v) {
                if (is_array($v)) {
                    $walk($v);
                } else {
                    $res[$k][] = $v;
                }
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
        $queries = [];
        $seen    = [];

        $add = function (string $v) use (&$queries, &$seen) {
            $v = trim(preg_replace('/\s+/', ' ', $v));
            if ($v !== '' && $v !== '-' && ! isset($seen[$v])) {
                $seen[$v] = true;
                $queries[] = $v;
            }
        };

        $brand   = trim((string) $product->brand);
        $name    = trim((string) $product->name);
        $sku     = trim((string) ($product->sku ?? ''));
        $ean     = trim((string) ($product->ean ?? ''));
        $primary = $this->getPrimaryIdentifier($product);

        $rawModel   = trim((string) ($product->model ?? ''));
        $modelParts = [];
        if (str_contains($rawModel, '/')) {
            $modelParts = array_values(array_filter(array_map('trim', explode('/', $rawModel))));
        }

        if ($brand && $primary) {
            $add("$brand $primary");
        }
        if ($primary) {
            $add($primary);
        }

        foreach ($modelParts as $part) {
            $p = $this->normalizeIdentifier($part);
            if ($brand) {
                $add("$brand $p");
            }
            $add($p);
        }

        foreach ($this->identifierVariants((string) $primary) as $v) {
            if ($brand) {
                $add("$brand $v");
            }
            $add($v);
        }

        if ($sku) {
            if ($brand) {
                $add("$brand $sku");
            }
            $add($sku);
            foreach ($this->identifierVariants($sku) as $v) {
                if ($brand) {
                    $add("$brand $v");
                }
                $add($v);
            }
        }

        if ($ean && $ean !== '-') {
            $add($ean);
        }

        if ($brand && $name) {
            $parts = array_values(array_filter(preg_split('/\s+/u', $name), fn ($w) => mb_strlen($w) >= 2));
            $add($brand . ' ' . implode(' ', array_slice($parts, 0, 4)));
            $add($brand . ' ' . implode(' ', array_slice($parts, 0, 2)));
        }

        foreach ($this->prefixFragments((string) $primary) as $f) {
            if ($brand) {
                $add("$brand $f");
            }
            $add($f);
        }

        if ($name) {
            $add($name);
        }
        if ($brand && $name) {
            $add("$brand $name");
        }

        return $queries;
    }

    protected function prefixFragments(string $value): array
    {
        $v = preg_replace('/[^A-Z0-9]/', '', $this->normalizeIdentifier($value));
        if ($v === '') {
            return [];
        }

        $out = [];
        for ($i = min(10, strlen($v)); $i >= 4; $i--) {
            $out[] = substr($v, 0, $i);
        }
        if (strlen($v) >= 8) {
            $out[] = substr($v, 1, 6);
            $out[] = substr($v, -6);
        }

        return array_values(array_unique($out));
    }

    // ================================================================
    // PRIMARY IDENTIFIER
    // ================================================================

    protected function getPrimaryIdentifier(Product $product): ?string
    {
        $model = trim((string) ($this->effectiveModel($product) ?? ''));
        if ($model !== '' && $model !== '-') {
            return $this->normalizeIdentifier($model);
        }

        $sku = trim((string) ($product->sku ?? ''));
        if ($sku !== '' && $sku !== '-') {
            return $this->normalizeIdentifier($sku);
        }

        return null;
    }

    protected function effectiveModel(Product $product): ?string
    {
        $model = trim((string) ($product->model ?? ''));

        if ($model !== '' && $model !== '-') {
            if (str_contains($model, '/')) {
                $parts = array_values(array_filter(array_map('trim', explode('/', $model))));
                $model = $parts[0] ?? $model;
            }
            return $this->normalizeIdentifier($model);
        }

        $name  = trim((string) ($product->name ?? ''));
        $brand = mb_strtolower(trim((string) ($product->brand ?? '')));
        if ($name === '') {
            return null;
        }

        $norm = $this->normalizeIdentifier($name);

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
            if ($c === '' || mb_strtolower($c) === $brand || ! preg_match('/\d/', $c)) {
                continue;
            }
            if (strlen(preg_replace('/[^A-Z0-9]/', '', $c)) >= 4) {
                return $c;
            }
        }

        return null;
    }

    // ================================================================
    // FETCH HTML
    // ================================================================

    protected function fetchHtml(string $url, string $referer = ''): ?string
    {
        if (str_contains($url, 'techmart.bg')) {
            return $this->fetchHtmlShell($url, $referer ?: 'https://techmart.bg/');
        }

        if (str_contains($url, 'technomarket.bg')) {
            return $this->fetchHtmlShell($url, $referer ?: 'https://www.technomarket.bg/');
        }

        try {
            $resp = Http::timeout(25)
                ->retry(2, 1200)
                ->withoutVerifying()
                ->withOptions([
                    'curl' => [
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ],
                ])
                ->withHeaders($this->headers($referer ?: $url))
                ->get($url);

            if ($resp->status() === 429 || $resp->status() === 403) {
                Log::warning('fetchHtml rate limited', [
                    'url'    => $url,
                    'status' => $resp->status(),
                ]);

                $resp = Http::timeout(25)
                    ->retry(1, 1500)
                    ->withoutVerifying()
                    ->withOptions([
                        'curl' => [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                        ],
                    ])
                    ->withHeaders($this->headers($referer ?: $url))
                    ->get($url);
            }

            if (! $resp->successful()) {
                return null;
            }

            return $resp->body();
        } catch (\Throwable $e) {
            Log::debug('fetchHtml failed', [
                'url'     => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function fetchHtmlShell(string $url, string $referer = ''): ?string
    {
        $cmd = sprintf(
            'curl -sk -L --max-time 30 ' .
            '--connect-timeout 10 ' .
            '--retry 2 --retry-delay 1 ' .
            '-H %s ' .
            '-H %s ' .
            '-H %s ' .
            '-H %s ' .
            '-w "HTTPSTATUS:%%{http_code}" ' .
            '%s',
            escapeshellarg('User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36'),
            escapeshellarg('Accept-Language: bg-BG,bg;q=0.9,en;q=0.8'),
            escapeshellarg('Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'),
            escapeshellarg('Referer: ' . ($referer ?: $url)),
            escapeshellarg($url)
        );

        $output = (string) shell_exec($cmd);

        preg_match('/HTTPSTATUS:(\d+)$/', $output, $matches);
        $status = (int) ($matches[1] ?? 0);
        $body   = (string) preg_replace('/HTTPSTATUS:\d+$/', '', $output);

        if ($status < 200 || $status >= 300 || trim($body) === '') {
            Log::debug('fetchHtmlShell failed', [
                'url'    => $url,
                'status' => $status,
            ]);
            return null;
        }

        return $body;
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
        if ($id === '' || $id === '-') {
            return [];
        }

        $variants = [
            $id,
            str_replace('/', '',  $id),
            str_replace('/', '-', $id),
            str_replace('/', ' ', $id),
            str_replace('.', '',  $id),
            str_replace('.', '-', $id),
            str_replace('.', ' ', $id),
            str_replace(['/', '.'], '',  $id),
            str_replace(['/', '.'], '-', $id),
            str_replace(['/', '.'], ' ', $id),
        ];

        $flat = preg_replace('/[^A-Z0-9]/', '', $id);
        if ($flat !== '') {
            $variants[] = $flat;
        }

        return array_values(array_unique(array_filter($variants)));
    }

    protected function containsExactIdentifier(string $text, string $identifier): bool
    {
        $tNorm = $this->normalizeIdentifier($text);
        $tFlat = preg_replace('/[^A-Z0-9]/', '', $tNorm);

        foreach ($this->identifierVariants($identifier) as $v) {
            $vNorm = $this->normalizeIdentifier($v);
            $vFlat = preg_replace('/[^A-Z0-9]/', '', $vNorm);

            if ($vNorm !== '' && str_contains($tNorm, $vNorm)) {
                return true;
            }
            if ($vFlat !== '' && str_contains($tFlat, $vFlat)) {
                return true;
            }

            if (strlen($vFlat) >= 6) {
                for ($len = strlen($vFlat) - 1; $len >= 6; $len--) {
                    if (str_contains($tFlat, substr($vFlat, 0, $len))) {
                        return true;
                    }
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
            'Techmart'     => 'https://techmart.bg',
            'Tehnomix'     => 'https://www.tehnomix.bg',
            default        => '',
        };
    }

    protected function makeAbsoluteUrl(string $url, string $base): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return rtrim($base, '/') . '/' . ltrim($url, '/');
    }

    protected function cleanUrl(string $url): string
    {
        $url   = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url   = preg_replace('/[\s"<>\'\]\)]+$/', '', $url);
        $parts = parse_url(trim($url));

        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            return trim($url);
        }

        $scheme = $parts['scheme'];
        $host   = mb_strtolower($parts['host']);
        $path   = $parts['path'] ?? '';

        if ($host === 'www.techmart.bg') {
            $host = 'techmart.bg';
        }

        return $scheme . '://' . $host . $path;
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