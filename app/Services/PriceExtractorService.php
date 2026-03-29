<?php

namespace App\Services;

class PriceExtractorService
{
    private const BGN_TO_EUR = 1.95583;
    private const MAX_HTML_LENGTH = 350000;
    private const MAX_TEXT_LENGTH = 120000;

    public function extractPriceFromUrl(string $url, string $html): ?float
    {
        $url = strtolower($url);
        $html = $this->prepareHtml($html);

        if (str_contains($url, 'technopolis.bg')) {
            return $this->extractTechnopolisPrice($html);
        }

        if (str_contains($url, 'technomarket.bg')) {
            return $this->extractTechnomarketPrice($html);
        }

        if (str_contains($url, 'zora.bg')) {
            return $this->extractZoraPrice($html);
        }

        if (str_contains($url, 'pazaruvaj.com')) {
            return $this->extractPazaruvajPrice($html);
        }

        if (str_contains($url, 'jarcomputers.com')) {
            return $this->extractJarPrice($html);
        }

        return $this->extractGenericPrice($html);
    }

    public function extractTitleFromHtml(string $html): ?string
    {
        $html = $this->prepareHtml($html);

        $patterns = [
            '/<meta\s+property="og:title"\s+content="([^"]+)"/iu',
            '/<meta\s+name="title"\s+content="([^"]+)"/iu',
            '/<title>(.*?)<\/title>/isu',
            '/<h1[^>]*>(.*?)<\/h1>/isu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $title = trim(strip_tags(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));

                if ($title !== '') {
                    return mb_substr($title, 0, 255);
                }
            }
        }

        return null;
    }

    protected function extractTechnopolisPrice(string $html): ?float
    {
        $paired = $this->extractHighestPairedEuroPrice($html);
        if ($paired !== null) {
            return $paired;
        }

        $jsonLd = $this->extractPriceFromJsonLd($html);
        if ($jsonLd !== null) {
            return $jsonLd;
        }

        return $this->extractByPatterns($html, [
            '/property="product:price:amount"\s+content="([\d\.,]+)"/iu',
            '/itemprop="price"\s+content="([\d\.,]+)"/iu',
            '/"currentPrice"\s*:\s*"?(?:BGN|EUR)?\s*([\d\.,]+)"?/iu',
            '/"salePrice"\s*:\s*"?(?:BGN|EUR)?\s*([\d\.,]+)"?/iu',
            '/"price"\s*:\s*"([\d\.,]+)"/iu',
        ], null);
    }

    protected function extractTechnomarketPrice(string $html): ?float
    {
        $jsonLd = $this->extractPriceFromJsonLd($html);
        if ($jsonLd !== null) {
            return $jsonLd;
        }

        $paired = $this->extractHighestPairedEuroPrice($html);
        if ($paired !== null) {
            return $paired;
        }

        return $this->extractByPatterns($html, [
            '/property="product:price:amount"\s+content="([\d\.,]+)"/iu',
            '/itemprop="price"\s+content="([\d\.,]+)"/iu',
            '/"offers"\s*:\s*\{.*?"price"\s*:\s*"([\d\.,]+)"/isu',
            '/"price"\s*:\s*"([\d\.,]+)"/iu',
            '/"price"\s*:\s*([\d\.,]+)/iu',
            '/class="[^"]*price-value[^"]*"[^>]*>\s*([\d\.,]+)\s*(?:€|EUR|лв|BGN)?/iu',
            '/class="[^"]*product-price[^"]*"[^>]*>\s*([\d\.,]+)\s*(?:€|EUR|лв|BGN)?/iu',
            '/class="[^"]*current-price[^"]*"[^>]*>\s*([\d\.,]+)\s*(?:€|EUR|лв|BGN)?/iu',
        ], null);
    }

    protected function extractZoraPrice(string $html): ?float
    {
        $paired = $this->extractHighestPairedEuroPrice($html);
        if ($paired !== null) {
            return $paired;
        }

        $jsonLd = $this->extractPriceFromJsonLd($html);
        if ($jsonLd !== null) {
            return $jsonLd;
        }

        return $this->extractByPatterns($html, [
            '/"final_price"\s*:\s*"([\d\.,]+)"/iu',
            '/"special_price"\s*:\s*"([\d\.,]+)"/iu',
            '/"price"\s*:\s*"([\d\.,]+)"/iu',
            '/([\d]{1,5}(?:,\^\{\d{2}\}|[.,]\d{2}))\s*€/iu',
        ], 'EUR');
    }

    protected function extractPazaruvajPrice(string $html): ?float
    {
        $jsonLd = $this->extractPriceFromJsonLd($html);
        if ($jsonLd !== null) {
            return $jsonLd;
        }

        $paired = $this->extractHighestPairedEuroPrice($html);
        if ($paired !== null) {
            return $paired;
        }

        return $this->extractByPatterns($html, [
            '/property="product:price:amount"\s+content="([\d\.,]+)"/iu',
            '/itemprop="price"\s+content="([\d\.,]+)"/iu',
            '/"price"\s*:\s*"([\d\.,]+)"/iu',
        ], null);
    }

    protected function extractJarPrice(string $html): ?float
    {
        $jsonLd = $this->extractPriceFromJsonLd($html);
        if ($jsonLd !== null) {
            return $jsonLd;
        }

        return $this->extractByPatterns($html, [
            '/property="product:price:amount"\s+content="([\d\.,]+)"/iu',
            '/itemprop="price"\s+content="([\d\.,]+)"/iu',
            '/"price"\s*:\s*"([\d\.,]+)"/iu',
        ], null);
    }

    protected function extractGenericPrice(string $html): ?float
    {
        $jsonLd = $this->extractPriceFromJsonLd($html);
        if ($jsonLd !== null) {
            return $jsonLd;
        }

        $paired = $this->extractHighestPairedEuroPrice($html);
        if ($paired !== null) {
            return $paired;
        }

        return $this->extractByPatterns($html, [
            '/property="product:price:amount"\s+content="([\d\.,]+)"/iu',
            '/itemprop="price"\s+content="([\d\.,]+)"/iu',
            '/"price"\s*:\s*"([\d\.,]+)"/iu',
            '/"price"\s*:\s*([\d\.,]+)/iu',
        ], null);
    }

    protected function extractHighestPairedEuroPrice(string $html): ?float
    {
        $text = $this->extractSearchableText($html);

        if ($text === '') {
            return null;
        }

        $candidates = [];

        $patterns = [
            '/(\d{1,5}(?:[.,]\d{2}))\s*(?:€|EUR)\s*\/\s*(\d{1,6}(?:[.,]\d{2}))\s*(?:лв\.?|BGN)/iu',
            '/(\d{1,5}(?:[.,]\d{2}))\s*(?:€|EUR)\s*-\s*(\d{1,6}(?:[.,]\d{2}))\s*(?:лв\.?|BGN)/iu',
            '/(\d{1,5}(?:[.,]\d{2}))\s*(?:€|EUR)\s+(\d{1,6}(?:[.,]\d{2}))\s*(?:лв\.?|BGN)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $eurRaw = $this->normalizeSpecialNumber($match[1]);
                    $bgnRaw = $this->normalizeSpecialNumber($match[2]);

                    $eur = $this->normalizePrice($eurRaw . ' EUR');
                    $bgnAsEur = $this->normalizePrice($bgnRaw . ' BGN');

                    if ($eur === null || $bgnAsEur === null) {
                        continue;
                    }

                    if (abs($eur - $bgnAsEur) <= 0.8 && $eur > 10) {
                        $candidates[] = $eur;
                    }
                }
            }

            if (!empty($candidates)) {
                break;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        return round(max($candidates), 2);
    }

    protected function extractPriceFromJsonLd(string $html): ?float
    {
        preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/isu', $html, $matches);

        foreach ($matches[1] ?? [] as $block) {
            $json = trim(html_entity_decode($block, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($json === '') {
                continue;
            }

            $decoded = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            $price = $this->findPriceInJsonLd($decoded);

            if ($price !== null && $price > 0) {
                return $price;
            }
        }

        return null;
    }

    protected function findPriceInJsonLd(mixed $node): ?float
    {
        if (is_array($node)) {
            if (isset($node['price'])) {
                $currency = $node['priceCurrency'] ?? null;
                $price = $this->normalizePrice((string) $node['price'] . ' ' . (string) $currency);

                if ($price !== null && $price > 0) {
                    return $price;
                }
            }

            foreach ($node as $value) {
                $found = $this->findPriceInJsonLd($value);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    protected function extractByPatterns(string $html, array $patterns, ?string $forcedCurrency = null): ?float
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $raw = $this->normalizeSpecialNumber($matches[1]);
                $suffix = $forcedCurrency ? (' ' . $forcedCurrency) : '';
                $price = $this->normalizePrice($raw . $suffix);

                if ($price !== null && $price > 0) {
                    return $price;
                }
            }
        }

        return null;
    }

    protected function normalizeSpecialNumber(string $value): string
    {
        $value = preg_replace('/\^\{(\d{2})\}/', '.$1', $value);
        $value = preg_replace('/,\s*(\d{2})/', '.$1', $value);

        return str_replace(',', '.', $value);
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

        if (!is_numeric($price)) {
            return null;
        }

        $price = (float) $price;

        if ($isBGN || !$isEUR) {
            $price = $price / self::BGN_TO_EUR;
        }

        return round($price, 2);
    }

    protected function prepareHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        return mb_substr($html, 0, self::MAX_HTML_LENGTH);
    }

    protected function extractSearchableText(string $html): string
    {
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $text);
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $text);
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        return mb_substr($text, 0, self::MAX_TEXT_LENGTH);
    }
}