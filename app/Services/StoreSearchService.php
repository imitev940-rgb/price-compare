<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StoreSearchService
{
    public function search(Product $product, string $storeKey): ?array
    {
        return match ($storeKey) {
            'technomarket' => $this->searchTechnomarket($product),
            'technopolis'  => $this->searchTechnopolis($product),
            'techmart'     => $this->searchTechmart($product),
            'tehnomix'     => $this->searchTehnomix($product),
            'pazaruvaj'    => $this->searchPazaruvajFallback($product),
            default        => null,
        };
    }

    protected function searchTechnomarket(Product $product): ?array
    {
        $terms = $this->buildDirectTerms($product);

        foreach ($terms as $term) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders($this->headers())
                    ->get('https://www.technomarket.bg/search', [
                        'query' => $term,
                    ]);

                if (! $response->successful()) {
                    continue;
                }

                $candidate = $this->extractTechnomarketCandidate($response->body(), $product);

                Log::info('Technomarket direct search', [
                    'product_id' => $product->id,
                    'term' => $term,
                    'found' => $candidate ? true : false,
                    'url' => $candidate['product_url'] ?? null,
                ]);

                if ($candidate) {
                    return $candidate;
                }
            } catch (\Throwable $e) {
                Log::warning('Technomarket direct search failed', [
                    'product_id' => $product->id,
                    'term' => $term,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    protected function searchTechnopolis(Product $product): ?array
    {
        $terms = $this->buildDirectTerms($product);

        foreach ($terms as $term) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders($this->headers())
                    ->get('https://www.technopolis.bg/bg/search', [
                        'q' => $term,
                    ]);

                if (! $response->successful()) {
                    continue;
                }

                $candidate = $this->extractTechnopolisCandidate($response->body(), $product);

                Log::info('Technopolis direct search', [
                    'product_id' => $product->id,
                    'term' => $term,
                    'found' => $candidate ? true : false,
                    'url' => $candidate['product_url'] ?? null,
                ]);

                if ($candidate) {
                    return $candidate;
                }
            } catch (\Throwable $e) {
                Log::warning('Technopolis direct search failed', [
                    'product_id' => $product->id,
                    'term' => $term,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    protected function searchTechmart(Product $product): ?array
    {
        $terms = $this->buildDirectTerms($product);

        foreach ($terms as $term) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders($this->headers())
                    ->get('https://techmart.bg/product/product/searchProducts/search/' . urlencode($term));

                if (! $response->successful()) {
                    continue;
                }

                $candidate = $this->extractTechmartCandidate($response->body(), $product);

                Log::info('Techmart direct search', [
                    'product_id' => $product->id,
                    'term' => $term,
                    'found' => $candidate ? true : false,
                    'url' => $candidate['product_url'] ?? null,
                ]);

                if ($candidate) {
                    return $candidate;
                }
            } catch (\Throwable $e) {
                Log::warning('Techmart direct search failed', [
                    'product_id' => $product->id,
                    'term' => $term,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    protected function searchTehnomix(Product $product): ?array
    {
        $terms = $this->buildDirectTerms($product);

        foreach ($terms as $term) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders($this->headers())
                    ->get('https://www.tehnomix.bg/catalogsearch/result/', [
                        'q' => $term,
                    ]);

                if (! $response->successful()) {
                    continue;
                }

                $candidate = $this->extractTehnomixCandidate($response->body(), $product);

                Log::info('Tehnomix direct search', [
                    'product_id' => $product->id,
                    'term' => $term,
                    'found' => $candidate ? true : false,
                    'url' => $candidate['product_url'] ?? null,
                ]);

                if ($candidate) {
                    return $candidate;
                }
            } catch (\Throwable $e) {
                Log::warning('Tehnomix direct search failed', [
                    'product_id' => $product->id,
                    'term' => $term,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    protected function searchPazaruvajFallback(Product $product): ?array
    {
        return $this->searchByKnownPattern(
            $product,
            'https://www.pazaruvaj.com/search/',
            '/\/p\//iu',
            'Pazaruvaj'
        );
    }

    protected function searchByKnownPattern(
        Product $product,
        string $baseUrl,
        string $pathPattern,
        string $label
    ): ?array {
        $terms = $this->buildDirectTerms($product);

        foreach ($terms as $term) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders($this->headers())
                    ->get($baseUrl, [
                        'st' => $term,
                        'q' => $term,
                        'query' => $term,
                    ]);

                if (! $response->successful()) {
                    continue;
                }

                $html = $response->body();

                if (! preg_match_all('/href="([^"]+)"/iu', $html, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $href) {
                    $url = html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $url = $this->makeAbsoluteUrl($url, 'https://www.pazaruvaj.com');

                    if (! $url) {
                        continue;
                    }

                    if (! preg_match($pathPattern, $url)) {
                        continue;
                    }

                    if ($this->isBadUrl($url)) {
                        continue;
                    }

                    $score = $this->calculateMatchScore($product, $url, null);

                    if ($score < 40) {
                        continue;
                    }

                    Log::info($label . ' fallback search', [
                        'product_id' => $product->id,
                        'term' => $term,
                        'url' => $url,
                        'score' => $score,
                    ]);

                    return [
                        'product_url' => $this->normalizeUrl($url),
                        'matched_title' => null,
                        'last_price' => null,
                        'search_status' => 'found',
                        'match_score' => $score,
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning($label . ' fallback failed', [
                    'product_id' => $product->id,
                    'term' => $term,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    protected function extractTechnomarketCandidate(string $html, Product $product): ?array
    {
        preg_match_all(
            '/<a[^>]+href="([^"]*\/[a-z0-9\-]+\/[a-z0-9\-]+-[0-9]+)"[^>]*>(.*?)<\/a>/isu',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        return $this->pickBestCandidate($matches, $product, 'https://www.technomarket.bg');
    }

    protected function extractTechnopolisCandidate(string $html, Product $product): ?array
    {
        preg_match_all(
            '/<a[^>]+href="([^"]*\/p\/[0-9]+)"[^>]*>(.*?)<\/a>/isu',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        return $this->pickBestCandidate($matches, $product, 'https://www.technopolis.bg');
    }

    protected function extractTechmartCandidate(string $html, Product $product): ?array
    {
        preg_match_all(
            '/href="([^"]+)"/iu',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        $best = null;

        foreach ($matches as $match) {
            $href = html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $url = $this->makeAbsoluteUrl($href, 'https://techmart.bg');

            if (! $url || $this->isBadUrl($url)) {
                continue;
            }

            if (! str_contains($url, 'techmart.bg')) {
                continue;
            }

            $path = parse_url($url, PHP_URL_PATH) ?? '';
            $slug = trim((string) basename($path), '/');

            if ($slug === '' || substr_count($slug, '-') < 2 || ! preg_match('/\d/', $slug)) {
                continue;
            }

            $score = $this->calculateMatchScore($product, $url, null);

            if ($score < 35) {
                continue;
            }

            if (! $best || $score > $best['match_score']) {
                $best = [
                    'product_url' => $this->normalizeUrl($url),
                    'matched_title' => null,
                    'last_price' => null,
                    'search_status' => 'found',
                    'match_score' => $score,
                ];
            }
        }

        return $best;
    }

    protected function extractTehnomixCandidate(string $html, Product $product): ?array
    {
        preg_match_all(
            '/href="([^"]+)"/iu',
            $html,
            $matches,
            PREG_SET_ORDER
        );

        $best = null;

        foreach ($matches as $match) {
            $href = html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $url = $this->makeAbsoluteUrl($href, 'https://www.tehnomix.bg');

            if (! $url || $this->isBadUrl($url)) {
                continue;
            }

            if (! str_contains($url, 'tehnomix.bg')) {
                continue;
            }

            $path = parse_url($url, PHP_URL_PATH) ?? '';
            $slug = trim((string) basename($path), '/');

            if ($slug === '' || substr_count($slug, '-') < 2 || ! preg_match('/\d/', $slug)) {
                continue;
            }

            $score = $this->calculateMatchScore($product, $url, null);

            if ($score < 35) {
                continue;
            }

            if (! $best || $score > $best['match_score']) {
                $best = [
                    'product_url' => $this->normalizeUrl($url),
                    'matched_title' => null,
                    'last_price' => null,
                    'search_status' => 'found',
                    'match_score' => $score,
                ];
            }
        }

        return $best;
    }

    protected function pickBestCandidate(array $matches, Product $product, string $baseUrl): ?array
    {
        $best = null;

        foreach ($matches as $match) {
            $url = html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = $this->cleanTitle($match[2] ?? '');
            $url = $this->makeAbsoluteUrl($url, $baseUrl);

            if (! $url || $this->isBadUrl($url)) {
                continue;
            }

            $score = $this->calculateMatchScore($product, $url, $title);

            if ($score < 35) {
                continue;
            }

            if (! $best || $score > $best['match_score']) {
                $best = [
                    'product_url' => $this->normalizeUrl($url),
                    'matched_title' => $title,
                    'last_price' => null,
                    'search_status' => 'found',
                    'match_score' => $score,
                ];
            }
        }

        return $best;
    }

    protected function buildDirectTerms(Product $product): array
    {
        $terms = [];

        $sku = $this->sanitizeToken((string) $product->sku);
        $ean = $this->sanitizeToken((string) $product->ean);
        $brand = $this->normalizeText((string) $product->brand);
        $model = $this->sanitizeToken($this->extractModel($product));
        $name = $this->normalizeText((string) $product->name);

        if ($ean !== '') {
            $terms[] = $ean;
        }

        if ($sku !== '') {
            $terms[] = $sku;
        }

        if ($brand !== '' && $model !== '') {
            $terms[] = trim($brand . ' ' . $model);
        }

        if ($model !== '') {
            $terms[] = $model;
        }

        if ($brand !== '' && $name !== '') {
            $terms[] = trim($brand . ' ' . $this->limitWords($name, 4));
        }

        return array_values(array_unique(array_filter($terms)));
    }

    protected function extractModel(Product $product): string
    {
        $sources = [
            (string) $product->sku,
            (string) $product->name,
        ];

        foreach ($sources as $source) {
            if (preg_match('/([A-Z0-9]{2,}(?:[-\/][A-Z0-9]{1,})+)/iu', $source, $matches)) {
                $model = strtoupper(trim($matches[1]));

                if ($this->sanitizeToken($model) !== '') {
                    return $model;
                }
            }
        }

        return '';
    }

    protected function calculateMatchScore(Product $product, string $url, ?string $title = null): float
    {
        $score = 0;

        $haystack = mb_strtolower($url . ' ' . ($title ?? ''), 'UTF-8');
        $sku = mb_strtolower($this->sanitizeToken((string) $product->sku), 'UTF-8');
        $ean = mb_strtolower($this->sanitizeToken((string) $product->ean), 'UTF-8');
        $brand = mb_strtolower($this->normalizeText((string) $product->brand), 'UTF-8');
        $model = mb_strtolower($this->sanitizeToken($this->extractModel($product)), 'UTF-8');

        if ($ean !== '' && str_contains($haystack, $ean)) {
            $score += 60;
        }

        if ($sku !== '' && str_contains($haystack, $sku)) {
            $score += 35;
        }

        if ($model !== '' && str_contains($haystack, $model)) {
            $score += 40;
        }

        if ($brand !== '' && str_contains($haystack, $brand)) {
            $score += 12;
        }

        if (! $this->isBadUrl($url)) {
            $score += 8;
        }

        return min(100, $score);
    }

    protected function makeAbsoluteUrl(string $url, string $base): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, '/')) {
            return rtrim($base, '/') . $url;
        }

        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        return null;
    }

    protected function headers(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36',
            'Accept-Language' => 'bg-BG,bg;q=0.9,en;q=0.8',
        ];
    }

    protected function sanitizeToken(string $value): string
    {
        $value = trim($value);

        if ($value === '' || in_array($value, ['-', '/', '--', '—'], true)) {
            return '';
        }

        $value = preg_replace('/\s+/u', ' ', $value);

        if (preg_match('/^[-\/—]+$/u', $value ?? '')) {
            return '';
        }

        return trim($value ?? '');
    }

    protected function isBadUrl(string $url): bool
    {
        $url = mb_strtolower($url, 'UTF-8');

        $blocked = [
            '/search',
            '/catalog',
            '/category',
            '/categories',
            '?q=',
            '&q=',
            '/blog',
            '/news',
            '/help',
            '/contact',
            '/promo',
            '/promotions',
        ];

        foreach ($blocked as $part) {
            if (str_contains($url, $part)) {
                return true;
            }
        }

        return false;
    }

    protected function cleanTitle(string $html): ?string
    {
        $title = trim(strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $title = preg_replace('/\s+/u', ' ', $title ?? '');

        return $title ?: null;
    }

    protected function normalizeUrl(string $url): string
    {
        $url = preg_replace('/#.*$/', '', $url);
        $url = preg_replace('/\?.*$/', '', $url);

        return rtrim($url, '/');
    }

    protected function normalizeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s\/\-\.\+]/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text ?? '');
    }

    protected function limitWords(string $text, int $limit = 5): string
    {
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_slice($words, 0, $limit));
    }
}