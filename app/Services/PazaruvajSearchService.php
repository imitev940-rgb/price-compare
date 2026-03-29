<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PazaruvajSearchService
{
    public function findProductUrl(Product $product): ?string
    {
        $queries = $this->buildQueries($product);

        foreach ($queries as $query) {
            $url = $this->searchAndResolve($product, $query);

            if ($url) {
                Log::info('Pazaruvaj product url found', [
                    'product_id' => $product->id,
                    'query' => $query,
                    'url' => $url,
                ]);

                return $url;
            }
        }

        Log::warning('Pazaruvaj product url not found', [
            'product_id' => $product->id,
            'queries' => $queries,
        ]);

        return null;
    }

    protected function buildQueries(Product $product): array
    {
        $queries = [];

        if (!empty($product->ean)) {
            $queries[] = trim($product->ean);
        }

        if (!empty($product->sku)) {
            $queries[] = trim($product->sku);
        }

        if (!empty($product->name)) {
            $queries[] = trim($product->name);
        }

        $queries = array_filter(array_unique($queries));

        return array_values($queries);
    }

    protected function searchAndResolve(Product $product, string $query): ?string
    {
        $searchUrl = 'https://www.pazaruvaj.com/s/?q=' . urlencode($query);

        $response = Http::timeout(20)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36',
                'Accept-Language' => 'bg-BG,bg;q=0.9,en;q=0.8',
            ])
            ->get($searchUrl);

        if (! $response->successful()) {
            return null;
        }

        $html = $response->body();

        preg_match_all('/href="([^"]*\/p\/[^"]+)"/i', $html, $matches);

        if (empty($matches[1])) {
            return null;
        }

        $productName = mb_strtolower($product->name ?? '', 'UTF-8');
        $sku = mb_strtolower((string) ($product->sku ?? ''), 'UTF-8');
        $ean = mb_strtolower((string) ($product->ean ?? ''), 'UTF-8');

        $candidates = [];

        foreach ($matches[1] as $href) {
            $fullUrl = $this->normalizeUrl($href);

            if (! $fullUrl) {
                continue;
            }

            $haystack = mb_strtolower($href, 'UTF-8');
            $score = 0;

            if ($ean !== '' && str_contains($haystack, $ean)) {
                $score += 100;
            }

            if ($sku !== '' && str_contains($haystack, $sku)) {
                $score += 80;
            }

            foreach ($this->makeTokens($productName) as $token) {
                if (str_contains($haystack, $token)) {
                    $score += 10;
                }
            }

            $candidates[] = [
                'url' => $fullUrl,
                'score' => $score,
            ];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates[0]['url'] ?? null;
    }

    protected function makeTokens(string $text): array
    {
        $text = preg_replace('/[^a-zA-Zа-яА-Я0-9\/\-\s]+/u', ' ', $text);
        $parts = preg_split('/\s+/u', trim($text)) ?: [];

        $parts = array_filter($parts, fn ($part) => mb_strlen($part, 'UTF-8') >= 3);

        return array_values(array_unique(array_map(
            fn ($part) => mb_strtolower($part, 'UTF-8'),
            $parts
        )));
    }

    protected function normalizeUrl(string $href): ?string
    {
        $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($href === '') {
            return null;
        }

        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }

        if (str_starts_with($href, '/')) {
            return 'https://www.pazaruvaj.com' . $href;
        }

        return null;
    }
}