<?php

namespace App\Services;

use App\Models\CompetitorLink;
use App\Models\PazaruvajOffer;
use App\Models\Product;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PazaruvajScraperService
{
    private const BGN_TO_EUR = 1.95583;

    public function scrape(Product $product, ?CompetitorLink $pazaruvajLink = null): array
    {
        if (! $pazaruvajLink || empty($pazaruvajLink->product_url) || $pazaruvajLink->product_url === '#') {
            return $this->emptyResult();
        }

        try {
            Log::info('Pazaruvaj scrape started', [
                'product_id' => $product->id,
                'product_name' => $product->name ?? null,
                'original_url' => $pazaruvajLink->product_url,
            ]);

            $finalUrl = $this->resolveWorkingUrl($product, $pazaruvajLink->product_url);

            if (! $finalUrl) {
                Log::warning('Pazaruvaj could not resolve final product url', [
                    'product_id' => $product->id,
                    'original_url' => $pazaruvajLink->product_url,
                ]);

                return $this->emptyResult();
            }

            if ($finalUrl !== $pazaruvajLink->product_url) {
                $oldUrl = $pazaruvajLink->product_url;

                $pazaruvajLink->update([
                    'product_url' => $finalUrl,
                ]);

                Log::info('Pazaruvaj product url auto-updated', [
                    'product_id' => $product->id,
                    'old_url' => $oldUrl,
                    'new_url' => $finalUrl,
                ]);
            }

            $response = $this->makeRequest($finalUrl);

            if (! $response || ! $response->successful()) {
                Log::warning('Pazaruvaj scrape failed: bad product page response', [
                    'product_id' => $product->id,
                    'url' => $finalUrl,
                    'status' => $response?->status(),
                ]);

                return $this->emptyResult();
            }

            $html = $response->body();
            $offers = $this->extractOffers($html, $finalUrl);

            Log::info('Pazaruvaj extracted offers summary', [
                'product_id' => $product->id,
                'url' => $finalUrl,
                'offers_count' => count($offers),
                'offers_preview' => array_slice($offers, 0, 50),
            ]);

            PazaruvajOffer::where('product_id', $product->id)->delete();

            if (empty($offers)) {
                Log::warning('Pazaruvaj no offers found after parsing', [
                    'product_id' => $product->id,
                    'url' => $finalUrl,
                ]);

                return $this->emptyResult();
            }

            usort($offers, fn ($a, $b) => $a['price'] <=> $b['price']);

            $lowestPrice = $offers[0]['price'] ?? null;
            $lowestStoreName = $offers[0]['store_name'] ?? null;
            $ourPosition = null;

            foreach ($offers as $index => &$offer) {
                $offer['position'] = $index + 1;
                $offer['is_lowest'] = $index === 0;

                if ($product->our_price !== null && $ourPosition === null) {
                    if ((float) $product->our_price <= (float) $offer['price']) {
                        $ourPosition = $index + 1;
                    }
                }
            }
            unset($offer);

            if ($ourPosition === null && $product->our_price !== null) {
                $ourPosition = count($offers) + 1;
            }

            foreach ($offers as $offer) {
                PazaruvajOffer::create([
                    'product_id' => $product->id,
                    'competitor_link_id' => $pazaruvajLink->id,
                    'store_name' => $offer['store_name'],
                    'offer_title' => null,
                    'offer_url' => $offer['offer_url'],
                    'price' => $offer['price'],
                    'position' => $offer['position'],
                    'is_lowest' => $offer['is_lowest'],
                    'checked_at' => now(),
                ]);
            }

            Log::info('Pazaruvaj scrape finished successfully', [
                'product_id' => $product->id,
                'final_url' => $finalUrl,
                'offers_count' => count($offers),
                'lowest_price' => $lowestPrice,
                'lowest_store_name' => $lowestStoreName,
                'our_position' => $ourPosition,
            ]);

            return [
                'success' => true,
                'offers_count' => count($offers),
                'lowest_price' => $lowestPrice,
                'lowest_store_name' => $lowestStoreName,
                'best_store' => $lowestStoreName,
                'our_position' => $ourPosition,
            ];
        } catch (\Throwable $e) {
            Log::error('Pazaruvaj scrape error', [
                'product_id' => $product->id,
                'url' => $pazaruvajLink->product_url ?? null,
                'message' => $e->getMessage(),
            ]);

            return $this->emptyResult();
        }
    }

    protected function emptyResult(): array
    {
        return [
            'success' => false,
            'offers_count' => 0,
            'lowest_price' => null,
            'lowest_store_name' => null,
            'best_store' => null,
            'our_position' => null,
        ];
    }

    protected function makeRequest(string $url)
    {
        return Http::timeout(25)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36',
                'Accept-Language' => 'bg-BG,bg;q=0.9,en;q=0.8',
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
            ])
            ->get($url);
    }

    protected function resolveWorkingUrl(Product $product, string $url): ?string
    {
        if (! $this->isSearchUrl($url)) {
            return $url;
        }

        Log::info('Pazaruvaj resolving product url from search url', [
            'product_id' => $product->id,
            'search_url' => $url,
        ]);

        $response = $this->makeRequest($url);

        if (! $response || ! $response->successful()) {
            return null;
        }

        $html = $response->body();

        return $this->extractBestProductUrlFromSearch($product, $html, $url);
    }

    protected function isSearchUrl(string $url): bool
    {
        $url = mb_strtolower(trim($url), 'UTF-8');

        return str_contains($url, '/s/?q=')
            || str_contains($url, '/s/')
            || str_contains($url, '?q=');
    }

    protected function extractBestProductUrlFromSearch(Product $product, string $html, string $baseUrl): ?string
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);

        $xpath = new DOMXPath($dom);

        $links = $xpath->query('//a[@href]');

        if (! $links || $links->length === 0) {
            return null;
        }

        $productName = mb_strtolower($product->name ?? '', 'UTF-8');
        $sku = mb_strtolower((string) ($product->sku ?? ''), 'UTF-8');
        $ean = mb_strtolower((string) ($product->ean ?? ''), 'UTF-8');

        $candidates = [];

        foreach ($links as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $href = trim($link->getAttribute('href'));
            $text = $this->cleanText($link->textContent ?? null);

            if (! $href || ! str_contains($href, '/p/')) {
                continue;
            }

            $fullUrl = $this->normalizeUrl($href, $baseUrl);

            if (! $fullUrl) {
                continue;
            }

            $score = 0;
            $haystack = mb_strtolower(($text ?? '') . ' ' . $href, 'UTF-8');

            if ($sku !== '' && str_contains($haystack, $sku)) {
                $score += 100;
            }

            if ($ean !== '' && str_contains($haystack, $ean)) {
                $score += 100;
            }

            $nameTokens = $this->makeSearchTokens($productName);

            foreach ($nameTokens as $token) {
                if (str_contains($haystack, $token)) {
                    $score += 10;
                }
            }

            if (preg_match('/ep2330[\/\-]?10/iu', $haystack)) {
                $score += 50;
            }

            $candidates[] = [
                'url' => $fullUrl,
                'text' => $text,
                'score' => $score,
            ];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        Log::info('Pazaruvaj search candidates', [
            'product_id' => $product->id,
            'candidates' => array_slice($candidates, 0, 10),
        ]);

        return $candidates[0]['url'] ?? null;
    }

    protected function makeSearchTokens(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[^a-zа-я0-9\/\- ]+/iu', ' ', $text);
        $parts = preg_split('/\s+/u', trim($text)) ?: [];

        $parts = array_filter($parts, function ($part) {
            return mb_strlen($part, 'UTF-8') >= 3;
        });

        return array_values(array_unique($parts));
    }

    protected function extractOffers(string $html, string $baseUrl): array
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);

        $xpath = new DOMXPath($dom);

        $offers = [];

        $shopLinks = $xpath->query('//a[contains(translate(normalize-space(.), "КЪММАГАЗИНА", "къммагазина"), "към магазина")]');

        Log::info('Pazaruvaj raw shop links found', [
            'count' => $shopLinks ? $shopLinks->length : 0,
        ]);

        if (! $shopLinks || $shopLinks->length === 0) {
            return [];
        }

        foreach ($shopLinks as $index => $shopLink) {
            if (! $shopLink instanceof DOMElement) {
                continue;
            }

            $offerUrl = $this->normalizeUrl($shopLink->getAttribute('href'), $baseUrl);

            if (! $offerUrl || $this->looksLikeBadOfferUrl($offerUrl)) {
                continue;
            }

            $container = $this->findOfferContainer($shopLink);

            if (! $container) {
                continue;
            }

            $storeName = $this->extractStoreNameFromContainer($container, $xpath);
            $price = $this->extractPriceForShopLink($container, $shopLink, $xpath);

            $debugText = $this->cleanText($container->textContent ?? '');
            $debugText = $debugText ? mb_substr($debugText, 0, 500) : null;

            Log::info('Pazaruvaj parsed offer candidate', [
                'index' => $index,
                'store_name' => $storeName,
                'price' => $price,
                'offer_url' => $offerUrl,
                'container_excerpt' => $debugText,
            ]);

            if (! $storeName || $this->isBadStoreName($storeName)) {
                continue;
            }

            if ($price === null || $price <= 0) {
                continue;
            }

            $offers[] = [
                'store_name' => $storeName,
                'offer_title' => null,
                'offer_url' => $offerUrl,
                'price' => $price,
            ];
        }

        $offers = $this->uniqueOffers($offers);

        Log::info('Pazaruvaj offers after unique', [
            'count' => count($offers),
            'offers' => array_slice($offers, 0, 100),
        ]);

        return $offers;
    }

    protected function findOfferContainer(DOMElement $node): ?DOMElement
    {
        $current = $node;
        $steps = 0;

        while ($current && $steps < 12) {
            $text = $this->cleanText($current->textContent ?? '');

            if ($text && $this->looksLikeOfferContainerText($text)) {
                return $current;
            }

            $parent = $current->parentNode;
            $current = $parent instanceof DOMElement ? $parent : null;
            $steps++;
        }

        return null;
    }

    protected function looksLikeOfferContainerText(string $text): bool
    {
        $text = mb_strtolower($text, 'UTF-8');

        $hasStoreAction =
            str_contains($text, 'към магазина') ||
            str_contains($text, 'данни на магазина');

        $hasPrice =
            str_contains($text, '€') ||
            str_contains($text, 'eur') ||
            str_contains($text, 'лв') ||
            str_contains($text, 'bgn');

        return $hasStoreAction && $hasPrice;
    }

    protected function extractStoreNameFromContainer(DOMElement $container, DOMXPath $xpath): ?string
    {
        $logoImages = $xpath->query('.//img[@alt]', $container);

        if ($logoImages) {
            foreach ($logoImages as $img) {
                if (! $img instanceof DOMElement) {
                    continue;
                }

                $alt = $this->cleanText($img->getAttribute('alt'));

                if (! $alt) {
                    continue;
                }

                if (preg_match('/^Logo\s+(.+)$/iu', $alt, $matches)) {
                    $name = $this->cleanText($matches[1] ?? null);

                    if ($name && ! $this->isBadStoreName($name)) {
                        return $name;
                    }
                }
            }
        }

        $containerText = $this->cleanText($container->textContent ?? '');

        if (! $containerText) {
            return null;
        }

        if (preg_match('/от\s+([A-ZА-Я0-9][A-Za-zА-Яа-я0-9\-\._ ]{1,80}\.(bg|com|eu))\s+Към магазина/iu', $containerText, $matches)) {
            $name = $this->cleanText($matches[1] ?? null);

            if ($name && ! $this->isBadStoreName($name)) {
                return $name;
            }
        }

        if (preg_match('/от\s+(.+?)\s+Към магазина/iu', $containerText, $matches)) {
            $name = $this->cleanText($matches[1] ?? null);

            if ($name) {
                $name = preg_replace('/^от\s+/iu', '', $name);
                $name = preg_replace('/\s+от\s+\d+[.,]\d{2}\s*(€|EUR|лв\.?|BGN).*$/iu', '', $name);
                $name = trim($name);

                if ($name && ! $this->isBadStoreName($name)) {
                    return $name;
                }
            }
        }

        if (preg_match('/Logo\s+(.+?)(?:\s+В наличност|\s+В рамките|\s+Информация в магазина|\s+Данни на магазина)/iu', $containerText, $matches)) {
            $name = $this->cleanText($matches[1] ?? null);

            if ($name && ! $this->isBadStoreName($name)) {
                return $name;
            }
        }

        return null;
    }

    protected function extractPriceForShopLink(DOMElement $container, DOMElement $shopLink, DOMXPath $xpath): ?float
    {
        $shopHref = trim($shopLink->getAttribute('href'));

        if ($shopHref !== '') {
            $sameHrefLinks = $xpath->query('.//a[@href]', $container);

            if ($sameHrefLinks && $sameHrefLinks->length > 0) {
                foreach ($sameHrefLinks as $link) {
                    if (! $link instanceof DOMElement) {
                        continue;
                    }

                    $href = trim($link->getAttribute('href'));
                    $text = $this->cleanText($link->textContent ?? null);

                    if ($href !== $shopHref || ! $text) {
                        continue;
                    }

                    if (
                        ! str_contains($text, '€') &&
                        ! str_contains(mb_strtolower($text, 'UTF-8'), 'eur') &&
                        ! str_contains($text, 'лв')
                    ) {
                        continue;
                    }

                    $price = $this->extractMainPriceFromText($text);

                    if ($price !== null && $price > 0) {
                        return $price;
                    }
                }
            }
        }

        $priceAnchors = $xpath->query('.//a[contains(normalize-space(.), "€") or contains(normalize-space(.), "лв")]', $container);

        if ($priceAnchors && $priceAnchors->length > 0) {
            $prices = [];

            foreach ($priceAnchors as $anchor) {
                if (! $anchor instanceof DOMElement) {
                    continue;
                }

                $text = $this->cleanText($anchor->textContent ?? null);

                if (! $text) {
                    continue;
                }

                $price = $this->extractMainPriceFromText($text);

                if ($price !== null && $price > 0) {
                    $prices[] = $price;
                }
            }

            if (! empty($prices)) {
                sort($prices);
                return $prices[0];
            }
        }

        $fullText = $this->cleanText($container->textContent ?? null);

        if (! $fullText) {
            return null;
        }

        return $this->extractMainPriceFromText($fullText);
    }

    protected function extractMainPriceFromText(string $text): ?float
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        $eurPrices = [];

        if (preg_match_all('/(\d{1,3}(?:[ \.]\d{3})*(?:,\d{2})|\d+(?:,\d{2}))\s*(€|EUR)/iu', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $price = $this->normalizePrice(($match[1] ?? '') . ' ' . ($match[2] ?? ''));
                if ($price !== null && $price > 0) {
                    $eurPrices[] = $price;
                }
            }
        }

        if (! empty($eurPrices)) {
            sort($eurPrices);
            return $eurPrices[0];
        }

        $bgnPrices = [];

        if (preg_match_all('/(\d{1,3}(?:[ \.]\d{3})*(?:,\d{2})|\d+(?:,\d{2}))\s*(лв\.?|BGN)/iu', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $price = $this->normalizePrice(($match[1] ?? '') . ' ' . ($match[2] ?? ''));
                if ($price !== null && $price > 0) {
                    $bgnPrices[] = $price;
                }
            }
        }

        if (! empty($bgnPrices)) {
            sort($bgnPrices);
            return $bgnPrices[0];
        }

        return null;
    }

    protected function uniqueOffers(array $offers): array
    {
        $result = [];
        $seen = [];

        foreach ($offers as $offer) {
            $url = trim((string) ($offer['offer_url'] ?? ''));
            $price = $offer['price'] ?? null;
            $store = trim((string) ($offer['store_name'] ?? ''));

            if (! $url || $price === null || ! $store) {
                continue;
            }

            if ($this->isBadStoreName($store)) {
                continue;
            }

            $key = md5(
                mb_strtolower($store, 'UTF-8') . '|' .
                number_format((float) $price, 2, '.', '') . '|' .
                $this->normalizeUrlForDedupe($url)
            );

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $result[] = [
                'store_name' => $store,
                'offer_title' => null,
                'offer_url' => $url,
                'price' => (float) $price,
            ];
        }

        return array_values($result);
    }

    protected function normalizeUrlForDedupe(string $url): string
    {
        $parts = parse_url($url);

        $host = mb_strtolower($parts['host'] ?? '', 'UTF-8');
        $path = $parts['path'] ?? '';

        return $host . $path;
    }

    protected function isBadStoreName(string $name): bool
    {
        $name = trim($name);
        $lower = mb_strtolower($name, 'UTF-8');

        $bad = [
            'pazaruvaj',
            'данни на магазина',
            'към магазина',
            'open',
            'lowest',
            'в наличност',
            'един вариант',
            'повече варианти',
            'информация в магазина',
        ];

        foreach ($bad as $word) {
            if ($lower === $word) {
                return true;
            }
        }

        if (mb_strlen($name, 'UTF-8') < 2 || mb_strlen($name, 'UTF-8') > 100) {
            return true;
        }

        return false;
    }

    protected function looksLikeBadOfferUrl(string $url): bool
    {
        $url = mb_strtolower($url, 'UTF-8');

        $badParts = [
            'javascript:',
            '/velemenyek',
            '/arfigyelo',
            '/uzletadatlap',
        ];

        foreach ($badParts as $badPart) {
            if (str_contains($url, $badPart)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeUrl(?string $url, ?string $baseUrl = null): ?string
    {
        if (! $url) {
            return null;
        }

        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($url === '' || $url === '#') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, '/')) {
            if ($baseUrl) {
                $parts = parse_url($baseUrl);
                $scheme = $parts['scheme'] ?? 'https';
                $host = $parts['host'] ?? 'www.pazaruvaj.com';

                return $scheme . '://' . $host . $url;
            }

            return 'https://www.pazaruvaj.com' . $url;
        }

        if (! preg_match('/^https?:\/\//i', $url)) {
            return null;
        }

        return $url;
    }

    protected function cleanText(?string $text): ?string
    {
        if (! $text) {
            return null;
        }

        $text = trim(strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $text = preg_replace('/\s+/u', ' ', $text);

        return $text !== '' ? $text : null;
    }

    protected function normalizePrice(string $rawPrice): ?float
    {
        $original = $rawPrice;

        $price = trim(strip_tags($rawPrice));
        $price = html_entity_decode($price, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $isBGN = str_contains($original, 'лв') || str_contains($original, 'BGN');
        $isEUR = str_contains($original, '€') || str_contains($original, 'EUR');

        $price = str_replace(["\xc2\xa0", ' ', 'лв.', 'лв', 'BGN', 'EUR', '€'], '', $price);

        if (substr_count($price, ',') > 0 && substr_count($price, '.') > 0) {
            $price = str_replace('.', '', $price);
            $price = str_replace(',', '.', $price);
        } elseif (substr_count($price, ',') > 0) {
            $price = str_replace(',', '.', $price);
        }

        $price = preg_replace('/[^\d\.]/', '', $price);

        if (! is_numeric($price)) {
            return null;
        }

        $price = (float) $price;

        if ($isBGN || ! $isEUR) {
            $price = $price / self::BGN_TO_EUR;
        }

        return round($price, 2);
    }
}