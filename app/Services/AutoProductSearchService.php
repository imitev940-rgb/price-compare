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
    private string $scriptPath;
    private int $nodeTimeout = 35;

    private const PLAYWRIGHT_STORES = ['technopolis', 'technomarket', 'techmart', 'tehnomix', 'zora'];

    private const STORE_KEYS = [
        'Technopolis'  => 'technopolis',
        'Technomarket' => 'technomarket',
        'Techmart'     => 'techmart',
        'Tehnomix'     => 'tehnomix',
        'Zora'         => 'zora',
    ];

    private const SCORE_ACCEPT   = 80;
    private const SCORE_FALLBACK = 55;
    private const SCORE_URL_HINT = 20;

    public function __construct()
    {
        $this->scriptPath = base_path('scripts/search-competitor.js');
    }

    // ================================================================
    // HANDLE
    // ================================================================

    public function handle(Product $product, bool $overwrite = false, ?string $onlyStore = null): void
    {
        $onlyStoreNorm = $onlyStore ? mb_strtolower(trim($onlyStore)) : null;

        $stores = [
            'Pazaruvaj'    => fn () => $this->searchPazaruvajUrl($product),
            'Technopolis'  => fn () => $this->searchViaPlaywright($product, 'technopolis'),
            'Technomarket' => fn () => $this->searchViaPlaywright($product, 'technomarket'),
            'Techmart'     => fn () => $this->searchViaPlaywright($product, 'techmart'),
            'Tehnomix'     => fn () => $this->searchViaPlaywright($product, 'tehnomix'),
            'Zora'         => fn () => $this->searchViaPlaywright($product, 'zora'),
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

                $start   = microtime(true);
                $url     = $resolver();
                $elapsed = round(microtime(true) - $start, 2);

                if (! $url) {
                    // При overwrite — деактивирай стария грешен линк
                    if ($overwrite && $existing) {
                        $existing->update([
                            'is_active'     => 0,
                            'search_status' => 'not_found',
                        ]);
                        Log::info('AutoSearch deactivated wrong link', [
                            'product_id' => $product->id,
                            'store'      => $storeName,
                        ]);
                    }

                    Log::info('AutoSearch no url found', [
                        'product_id' => $product->id,
                        'store'      => $storeName,
                        'elapsed'    => $elapsed,
                    ]);
                    continue;
                }

                CompetitorLink::updateOrCreate(
                    ['product_id' => $product->id, 'store_id' => $store->id],
                    ['product_url' => $url, 'is_active' => 1]
                );

                Log::info('AutoSearch saved link', [
                    'product_id' => $product->id,
                    'store'      => $storeName,
                    'url'        => $url,
                    'elapsed'    => $elapsed,
                ]);

            } catch (\Throwable $e) {
                Log::error('AutoSearch store failed', [
                    'product_id' => $product->id,
                    'store'      => $storeName,
                    'message'    => $e->getMessage(),
                ]);
            }
        }
    }

    // ================================================================
    // PLAYWRIGHT
    // ================================================================

    protected function searchViaPlaywright(Product $product, string $storeKey): ?string
    {
        if (! file_exists($this->scriptPath)) {
            Log::error('AutoSearch: search-competitor.js not found', [
                'path' => $this->scriptPath,
            ]);
            return null;
        }

        $query = $this->buildQuery($product);
        if (! $query) {
            return null;
        }

        Log::debug('AutoSearch Playwright call', [
            'store'      => $storeKey,
            'product_id' => $product->id,
            'query'      => $query,
        ]);

        $result = $this->runPlaywright($storeKey, $query);

        if ($result && ! empty($result['url'])) {
            Log::debug('AutoSearch Playwright result', [
                'store'   => $storeKey,
                'product' => $product->id,
                'url'     => $result['url'],
                'score'   => $result['score'] ?? 0,
                'method'  => $result['method'] ?? '',
            ]);
            return $result['url'];
        }

        // Fallback — пробвай всички варианти последователно
        foreach ($this->buildFallbackQueries($product) as $fallbackQuery) {
            if (!$fallbackQuery || $fallbackQuery === $query) continue;

            Log::debug('AutoSearch Playwright fallback', [
                'store'   => $storeKey,
                'product' => $product->id,
                'query'   => $fallbackQuery,
            ]);

            $result = $this->runPlaywright($storeKey, $fallbackQuery);
            if ($result && ! empty($result['url'])) {
                return $result['url'];
            }
        }

        return null;
    }

    protected function runPlaywright(string $storeKey, string $query): ?array
    {
        // Mac използва gtimeout (brew install coreutils), Linux използва timeout
        $timeoutCmd = strtoupper(substr(PHP_OS, 0, 3)) === 'DAR' ? 'gtimeout' : 'timeout';

        $cmd = sprintf(
            '%s %d node %s %s %s 2>/dev/null',
            $timeoutCmd,
            $this->nodeTimeout,
            escapeshellarg($this->scriptPath),
            escapeshellarg($storeKey),
            escapeshellarg($query)
        );

        $output = shell_exec($cmd);

        if (! $output) {
            return null;
        }

        $data = json_decode(trim($output), true);

        if (! is_array($data)) {
            Log::warning('AutoSearch invalid JSON from Playwright', [
                'store'  => $storeKey,
                'output' => substr($output, 0, 200),
            ]);
            return null;
        }

        return $data;
    }

    // ================================================================
    // QUERY BUILDERS
    // ================================================================

    protected function buildQuery(Product $product): ?string
    {
        $brand = trim((string) ($product->brand ?? ''));
        $model = trim((string) ($product->model ?? ''));
        $sku   = trim((string) ($product->sku ?? ''));

        $identifier = ($model !== '' && $model !== '-') ? $model : $sku;

        // Ако identifier е само цифри (вътрешен SKU) → игнорирай го
        if (preg_match('/^\d+$/', $identifier)) {
            $identifier = '';
        }

        // buildQuery използва оригинален SKU без промяна
        // buildFallbackQuery ще опита с интервал ако е нужно

        if ($brand && $identifier) return "$brand $identifier";
        if ($identifier)           return $identifier;

        // Fallback → извлечи модела от name чрез effectiveModel
        $primary = $this->getPrimaryIdentifier($product);
        if ($brand && $primary) return "$brand $primary";
        if ($primary)           return $primary;

        return trim((string) ($product->name ?? '')) ?: null;
    }

    protected function buildFallbackQuery(Product $product): ?string
    {
        // buildFallbackQuery вече не се използва директно — използвай buildFallbackQueries()
        $queries = $this->buildFallbackQueries($product);
        return $queries[0] ?? null;
    }

    protected function buildFallbackQueries(Product $product): array
    {
        $model = trim((string) ($product->model ?? ''));
        $sku   = trim((string) ($product->sku ?? ''));

        $identifier = ($model !== '' && $model !== '-') ? $model : $sku;
        if (!$identifier) return [];

        $brand   = trim((string) ($product->brand ?? ''));
        $queries = [];

        // Вариант 1: махни последния суфикс -XX (напр. CTPele231-26 → CTPele231)
        $stripped = preg_replace('/-\d{1,3}$/', '', $identifier);
        if ($stripped !== $identifier) {
            $queries[] = $brand ? "$brand $stripped" : $stripped;
        }

        // Вариант 2: добави интервал между букви и цифри само ако има точно 1 преход
        // Re1201 → Re 1201 ✅ | EY9228E0 → пропускаме (2+ преходи) ✅
        $transitions = preg_match_all('/([A-Za-z])(\d)/', $identifier);
        if ($transitions === 1) {
            $spaced = preg_replace('/([A-Za-z])(\d)/', '$1 $2', $identifier);
            if ($spaced !== $identifier) {
                $queries[] = $brand ? "$brand $spaced" : $spaced;
            }
        }

        // Вариант 3: само identifier без brand (напр. "201VFE" вместо "Eldom 201VFE")
        if ($brand && $identifier) {
            $queries[] = $identifier;
        }

        return array_unique($queries);
    }

    // ================================================================
    // PAZARUVAJ (непроменен)
    // ================================================================

    protected function searchPazaruvajUrl(Product $product): ?string
    {
        $bestUrl   = null;
        $bestScore = -999;

        foreach (array_slice($this->buildProgressiveQueries($product), 0, 16) as $query) {
            try {
                $searchUrl = 'https://www.pazaruvaj.com/CategorySearch.php?st=' . urlencode($query);
                $html = $this->fetchPazaruvajHtml($searchUrl);

                if (!$html) {
                    continue;
                }

                preg_match_all('/https:\/\/www\.pazaruvaj\.com\/p\/[^"\'<>\s]+/i', $html, $m);

                foreach (array_values(array_unique($m[0] ?? [])) as $candidate) {
                    $u = $this->cleanUrl($candidate);
                    [$s] = $this->computeMatchScore($u, '', $product);
                    Log::debug('Pazaruvaj candidate', ['url' => $u, 'score' => $s]);

                    if ($s > $bestScore) {
                        $bestScore = $s;
                        $bestUrl   = $u;
                    }

                    if ($s >= self::SCORE_ACCEPT) {
                        $html = $this->fetchPazaruvajHtml($u);
                        [$ps] = $this->computeMatchScore($u, (string) $html, $product);

                        if ($ps >= self::SCORE_ACCEPT) {
                            Log::info('Pazaruvaj found', ['product_id' => $product->id, 'url' => $u, 'score' => $ps]);
                            return $u;
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        if ($bestUrl && $bestScore >= self::SCORE_FALLBACK) {
            $html = $this->fetchPazaruvajHtml($bestUrl);
            [$ps] = $this->computeMatchScore($bestUrl, (string) $html, $product);

            if ($ps >= self::SCORE_FALLBACK) {
                Log::info('Pazaruvaj fallback', ['product_id' => $product->id, 'url' => $bestUrl, 'score' => $ps]);
                return $bestUrl;
            }
        }

        return null;
    }

    // ================================================================
    // FETCH HTML (само за Pazaruvaj)
    // ================================================================

    protected function fetchPazaruvajHtml(string $url): ?string
    {
        try {
            $scriptPath = base_path('scripts/fetch-pazaruvaj.js');
            $cmd = sprintf('timeout 45 node %s %s 2>/dev/null', escapeshellarg($scriptPath), escapeshellarg($url));
            $output = shell_exec($cmd);

            if (!$output) {
                return null;
            }

            $data = json_decode(trim($output), true);
            if (!is_array($data) || empty($data['html'])) {
                return null;
            }

            $status = $data['status'] ?? 0;
            if ($status < 200 || $status >= 300) {
                return null;
            }

            return $data['html'];
        } catch (\Throwable $e) {
            Log::debug('fetchPazaruvajHtml failed', ['url' => $url, 'message' => $e->getMessage()]);
            return null;
        }
    }

    protected function fetchHtml(string $url, string $referer = ''): ?string
    {
        try {
            $resp = Http::timeout(25)
                ->retry(2, 1200)
                ->withoutVerifying()
                ->withOptions(['curl' => [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false]])
                ->withHeaders($this->headers($referer ?: $url))
                ->get($url);

            if ($resp->status() === 429 || $resp->status() === 403) {
                sleep(2);
                $resp = Http::timeout(25)->withoutVerifying()->withHeaders($this->headers($referer ?: $url))->get($url);
            }

            if (! $resp->successful()) return null;
            return $resp->body();
        } catch (\Throwable $e) {
            Log::debug('fetchHtml failed', ['url' => $url, 'message' => $e->getMessage()]);
            return null;
        }
    }

    // ================================================================
    // SCORING (само за Pazaruvaj)
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

        if ($primaryFoundInUrl) { $score += 55; $why[] = 'url_primary'; }
        if ($skuFoundInUrl)     { $score += 40; $why[] = 'url_sku'; }
        if ($eanFoundInUrl)     { $score += 35; $why[] = 'url_ean'; }

        if (! $primaryFoundInUrl && ! $skuFoundInUrl && $primary) {
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

        if (! $primaryFoundInUrl && ! $skuFoundInUrl && ! $eanFoundInUrl) {
            foreach (['bundle', 'komplekt', 'set-', 'paket-', 'filter', 'aksesoar'] as $b) {
                if (str_contains($uNorm, $b)) { $score -= 18; $why[] = 'url_penalty:' . $b; }
            }
        }

        if ($html !== '') {
            $title = '';
            $h1    = '';

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
                if ($this->containsExactIdentifier($prio, $sku))         { $score += 35; $why[] = 'title_sku'; }
                elseif ($this->containsExactIdentifier($bodyText, $sku)) { $score += 20; $why[] = 'body_sku'; }
            }

            if (strlen($ean) >= 8) {
                if (str_contains($prioFlat, $ean))     { $score += 30; $why[] = 'title_ean'; }
                elseif (str_contains($bodyFlat, $ean)) { $score += 18; $why[] = 'body_ean'; }
            }

            foreach ($this->extractStructuredIds($html) as $sid) {
                if ($primary && $this->containsExactIdentifier($sid, $primary)) { $score += 35; $why[] = 'jsonld_primary'; break; }
                if ($sku && $this->containsExactIdentifier($sid, $sku))         { $score += 25; $why[] = 'jsonld_sku'; break; }
                if (strlen($ean) >= 8 && str_contains(preg_replace('/\D/', '', mb_strtolower($sid)), $ean)) { $score += 20; $why[] = 'jsonld_ean'; break; }
            }

            $tokens   = array_values(array_filter(preg_split('/\s+/u', "$brand $name"), fn ($t) => strlen(trim($t)) >= 3));
            $hitsPrio = 0;
            $hitsBody = 0;

            foreach ($tokens as $t) {
                if (str_contains($prio, $t))     $hitsPrio++;
                if (str_contains($bodyText, $t)) $hitsBody++;
            }

            $score += min(40, $hitsPrio * 10 + $hitsBody * 3);
            if ($hitsPrio >= 2) $why[] = "title_tokens:{$hitsPrio}";
            if ($hitsBody >= 3) $why[] = "body_tokens:{$hitsBody}";

            if (! $primaryFoundInPage) {
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
            if (! is_array($data)) continue;
            $flat = $this->flattenArray($data);
            foreach (['sku', 'mpn', 'gtin', 'gtin13', 'gtin12', 'productID'] as $k) {
                foreach ((array) ($flat[$k] ?? []) as $v) {
                    $v = trim((string) $v);
                    if ($v !== '') $out[] = $v;
                }
            }
        }

        foreach (['sku', 'mpn', 'gtin', 'ean'] as $k) {
            preg_match_all('/"' . $k . '"\s*:\s*"([^"]+)"/i', $html, $mx);
            foreach (($mx[1] ?? []) as $v) {
                $v = trim((string) $v);
                if ($v !== '') $out[] = $v;
            }
        }

        return array_values(array_unique($out));
    }

    protected function flattenArray(array $arr): array
    {
        $res  = [];
        $walk = function ($node) use (&$res, &$walk) {
            if (! is_array($node)) return;
            foreach ($node as $k => $v) {
                if (is_array($v)) $walk($v);
                else $res[$k][] = $v;
            }
        };
        $walk($arr);
        return $res;
    }

    // ================================================================
    // QUERY BUILDER (само за Pazaruvaj)
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

        if ($brand && $primary) $add("$brand $primary");
        if ($primary)           $add($primary);

        foreach ($modelParts as $part) {
            $p = $this->normalizeIdentifier($part);
            if ($brand) $add("$brand $p");
            $add($p);
        }

        foreach ($this->identifierVariants((string) $primary) as $v) {
            if ($brand) $add("$brand $v");
            $add($v);
        }

        if ($sku) {
            if ($brand) $add("$brand $sku");
            $add($sku);
            foreach ($this->identifierVariants($sku) as $v) {
                if ($brand) $add("$brand $v");
                $add($v);
            }
        }

        if ($ean && $ean !== '-') $add($ean);

        if ($brand && $name) {
            $parts = array_values(array_filter(preg_split('/\s+/u', $name), fn ($w) => mb_strlen($w) >= 2));
            $add($brand . ' ' . implode(' ', array_slice($parts, 0, 4)));
            $add($brand . ' ' . implode(' ', array_slice($parts, 0, 2)));
        }

        foreach ($this->prefixFragments((string) $primary) as $f) {
            if ($brand) $add("$brand $f");
            $add($f);
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
        if ($model !== '' && $model !== '-') return $this->normalizeIdentifier($model);

        $sku = trim((string) ($product->sku ?? ''));
        if ($sku !== '' && $sku !== '-') return $this->normalizeIdentifier($sku);

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
        if ($name === '') return null;

        $norm = $this->normalizeIdentifier($name);

        if (preg_match('/\b([A-Z0-9]{4,}(?:\/[A-Z0-9]{4,})+)\b/u', $norm, $mx)) {
            $first = explode('/', $mx[1])[0];
            if (strlen(preg_replace('/[^A-Z0-9]/', '', $first)) >= 4) return $first;
        }

        if (preg_match('/\b([A-Z]{1,5}[\s\-]?\d{2,5}[A-Z0-9\.\/\-]{0,15})\b/u', $norm, $m)) {
            $c = trim((string) $m[1]);
            if ($c !== '' && mb_strtolower($c) !== $brand && preg_match('/\d/', $c) && strlen(preg_replace('/[^A-Z0-9]/', '', $c)) >= 3) return $c;
        }

        if (preg_match('/\b([A-Z]{1,5}\d{2,5}[A-Z0-9\.\/\-]{0,12})\b/u', $norm, $m)) {
            $c = trim((string) $m[1]);
            if ($c !== '' && mb_strtolower($c) !== $brand && preg_match('/\d/', $c) && strlen(preg_replace('/[^A-Z0-9]/', '', $c)) >= 4) return $c;
        }

        preg_match_all('/\b([A-Z0-9][A-Z0-9\/\.\-]{3,})\b/u', $norm, $ms);
        foreach (($ms[1] ?? []) as $c) {
            $c = trim((string) $c);
            if ($c === '' || mb_strtolower($c) === $brand || ! preg_match('/\d/', $c)) continue;
            if (strlen(preg_replace('/[^A-Z0-9]/', '', $c)) >= 4) return $c;
        }

        return null;
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

            if (strlen($vFlat) >= 6) {
                for ($len = strlen($vFlat) - 1; $len >= 6; $len--) {
                    if (str_contains($tFlat, substr($vFlat, 0, $len))) return true;
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
            'Zora'         => 'https://zora.bg',
            default        => '',
        };
    }

    protected function cleanUrl(string $url): string
    {
        $url   = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url   = preg_replace('/[\s"<>\'\]\)]+$/', '', $url);
        $parts = parse_url(trim($url));

        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) return trim($url);

        $scheme = $parts['scheme'];
        $host   = mb_strtolower($parts['host']);
        $path   = $parts['path'] ?? '';

        if ($host === 'www.techmart.bg') $host = 'techmart.bg';

        return $scheme . '://' . $host . $path;
    }

    protected function makeAbsoluteUrl(string $url, string $base): string
    {
        if (Str::startsWith($url, ['http://', 'https://'])) return $url;
        return rtrim($base, '/') . '/' . ltrim($url, '/');
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