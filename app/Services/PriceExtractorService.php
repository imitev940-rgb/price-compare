<?php

namespace App\Services;

class PriceExtractorService
{
    private const BGN_TO_EUR     = 1.95583;
    private const MAX_HTML_LENGTH = 350000;
    private const MAX_TEXT_LENGTH = 120000;

    public function extractPriceFromUrl(string $url, string $html): ?float
    {
        $url  = strtolower($url);
        $html = $this->prepareHtml($html);

        if (str_contains($url, 'technopolis.bg'))  return $this->extractTechnopolisPrice($html);
        if (str_contains($url, 'technomarket.bg'))  return $this->extractTechnomarketPrice($html);
        if (str_contains($url, 'techmart.bg'))      return $this->extractTechmartPrice($html);
        if (str_contains($url, 'tehnomix.bg'))      return $this->extractTehnomixPrice($html);
        if (str_contains($url, 'pazaruvaj.com'))    return $this->extractPazaruvajPrice($html);
        if (str_contains($url, 'jarcomputers.com')) return $this->extractJarPrice($html);

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
                if ($title !== '') return mb_substr($title, 0, 255);
            }
        }

        return null;
    }

    // ================================================================
    // TECHNOPOLIS
    // ================================================================

    protected function extractTechnopolisPrice(string $html): ?float
    {
        $paired = $this->extractHighestPairedEuroPrice($html);
        if ($paired !== null) return $paired;

        $jsonLd = $this->extractPriceFromJsonLd($html);
        if ($jsonLd !== null) return $jsonLd;

        return $this->extractByPatterns($html, [
            '/property="product:price:amount"\s+content="([\d\.,]+)"/iu',
            '/itemprop="price"\s+content="([\d\.,]+)"/iu',
            '/"currentPrice"\s*:\s*"?(?:BGN|EUR)?\s*([\d\.,]+)"?/iu',
            '/"salePrice"\s*:\s*"?(?:BGN|EUR)?\s*([\d\.,]+)"?/iu',
            '/"price"\s*:\s*"([\d\.,]+)"/iu',
        ], null);
    }

    // ================================================================
    // TECHNOMARKET
    // Сайтът показва цени в BGN (лв.) но JSON-LD може да е BGN или EUR.
    // Стратегия:
    //   1. JSON-LD с explicit priceCurrency → normalizePrice го конвертира правилно
    //   2. Paired EUR/BGN pattern ("199.00 € / 389.21 лв.")
    //   3. itemprop="price" content → стойността е в BGN → конвертираме
    //   4. og:price → BGN → конвертираме
    //   5. Явна EUR цена в текст
    // ================================================================

    protected function extractTechnomarketPrice(string $html): ?float
    {
        // 1. JSON-LD — най-надежден, normalizePrice вика BGN→EUR ако трябва
        $jsonLd = $this->extractPriceFromJsonLd($html);
        if ($jsonLd !== null) return $jsonLd;

        // 2. Paired EUR + BGN ("249.00 € / 486.91 лв.")
        $paired = $this->extractHighestPairedEuroPrice($html);
        if ($paired !== null) return $paired;

        // 3. itemprop="price" content — Technomarket го слага в BGN
        if (preg_match('/itemprop="price"\s+content="([\d\.,]+)"/iu', $html, $m)) {
            $price = $this->parseRawNumber($m[1]);
            if ($price !== null && $price > 0) {
                return round($price / self::BGN_TO_EUR, 2);
            }
        }

        // 4. og:price:amount meta — също BGN
        if (preg_match('/property="product:price:amount"\s+content="([\d\.,]+)"/iu', $html, $m)) {
            $price = $this->parseRawNumber($m[1]);
            if ($price !== null && $price > 0) {
                return round($price / self::BGN_TO_EUR, 2);
            }
        }

        // 5. Явна EUR цена в текст "249.00 €" или "€ 249.00"
        if (preg_match('/(\d{1,5}[.,]\d{2})\s*€/u', $html, $m)) {
            $price = $this->parseRawNumber($m[1]);
            if ($price !== null && $price > 0) return round($price, 2);
        }
        if (preg_match('/€\s*(\d{1,5}[.,]\d{2})/u', $html, $m)) {
            $price = $this->parseRawNumber($m[1]);
            if ($price !== null && $price > 0) return round($price, 2);
        }

        // 6. Явна BGN цена в текст "486.91 лв." → конвертираме
        if (preg_match('/(\d{1,6}[.,]\d{2})\s*(?:лв\.?|BGN)/iu', $html, $m)) {
            $price = $this->parseRawNumber($m[1]);
            if ($price !== null && $price > 0) {
                return round($price / self::BGN_TO_EUR, 2);
            }
        }

        return null;
    }

    // ================================================================
    // TECHMART
    // ================================================================

    protected function extractTechmartPrice(string $html): ?float
    {
        $jsonLd = $this->extractPriceFromJsonLd($html);
        if ($jsonLd !== null) return $jsonLd;

        $paired = $this->extractHighestPairedEuroPrice($html);
        if ($paired !== null) return $paired;

        return $this->extractByPatterns($html, [
            '/itemprop="price"\s+content="([\d\.,]+)"/iu',
            '/property="product:price:amount"\s+content="([\d\.,]+)"/iu',
            '/"price"\s*:\s*"([\d\.,]+)"/iu',
            '/"final_price"\s*:\s*"([\d\.,]+)"/iu',
            '/class="[^"]*price[^"]*"[^>]*>\s*([\d\.,]+)\s*(?:€|EUR)/iu',
        ], null);
    }

    // ================================================================
    // TEHNOMIX  (Magento — JSON-LD с BGN или EUR)
    // ================================================================

    protected function extractTehnomixPrice(string $html): ?float
    {
        $jsonLd = $this->extractPriceFromJsonLd($html);
        if ($jsonLd !== null) return $jsonLd;

        $paired = $this->extractHighestPairedEuroPrice($html);
        if ($paired !== null) return $paired;

        return $this->extractByPatterns($html, [
            '/property="product:price:amount"\s+content="([\d\.,]+)"/iu',
            '/itemprop="price"\s+content="([\d\.,]+)"/iu',
            '/"price"\s*:\s*"([\d\.,]+)"/iu',
            '/"final_price"\s*:\s*"([\d\.,]+)"/iu',
            '/class="[^"]*price[^"]*"[^>]*>\s*([\d\.,]+)\s*(?:лв\.?|BGN|€|EUR)/iu',
        ], null);
    }

    // ================================================================
    // PAZARUVAJ
    // ================================================================

    protected function extractPazaruvajPrice(string $html): ?float
    {
        $jsonLd = $this->extractPriceFromJsonLd($html);
        if ($jsonLd !== null) return $jsonLd;

        $paired = $this->extractHighestPairedEuroPrice($html);
        if ($paired !== null) return $paired;

        return $this->extractByPatterns($html, [
            '/property="product:price:amount"\s+content="([\d\.,]+)"/iu',
            '/itemprop="price"\s+content="([\d\.,]+)"/iu',
            '/"price"\s*:\s*"([\d\.,]+)"/iu',
        ], null);
    }

    // ================================================================
    // JAR
    // ================================================================

    protected function extractJarPrice(string $html): ?float
    {
        $jsonLd = $this->extractPriceFromJsonLd($html);
        if ($jsonLd !== null) return $jsonLd;

        return $this->extractByPatterns($html, [
            '/property="product:price:amount"\s+content="([\d\.,]+)"/iu',
            '/itemprop="price"\s+content="([\d\.,]+)"/iu',
            '/"price"\s*:\s*"([\d\.,]+)"/iu',
        ], null);
    }

    // ================================================================
    // GENERIC
    // ================================================================

    protected function extractGenericPrice(string $html): ?float
    {
        $jsonLd = $this->extractPriceFromJsonLd($html);
        if ($jsonLd !== null) return $jsonLd;

        $paired = $this->extractHighestPairedEuroPrice($html);
        if ($paired !== null) return $paired;

        return $this->extractByPatterns($html, [
            '/property="product:price:amount"\s+content="([\d\.,]+)"/iu',
            '/itemprop="price"\s+content="([\d\.,]+)"/iu',
            '/"price"\s*:\s*"([\d\.,]+)"/iu',
            '/"price"\s*:\s*([\d\.,]+)/iu',
        ], null);
    }

    // ================================================================
    // CORE EXTRACTORS
    // ================================================================

    protected function extractHighestPairedEuroPrice(string $html): ?float
    {
        $text = $this->extractSearchableText($html);
        if ($text === '') return null;

        $candidates = [];

        $patterns = [
            '/(\d{1,5}(?:[.,]\d{2}))\s*(?:€|EUR)\s*\/\s*(\d{1,6}(?:[.,]\d{2}))\s*(?:лв\.?|BGN)/iu',
            '/(\d{1,5}(?:[.,]\d{2}))\s*(?:€|EUR)\s*-\s*(\d{1,6}(?:[.,]\d{2}))\s*(?:лв\.?|BGN)/iu',
            '/(\d{1,5}(?:[.,]\d{2}))\s*(?:€|EUR)\s+(\d{1,6}(?:[.,]\d{2}))\s*(?:лв\.?|BGN)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $eurRaw   = $this->normalizeSpecialNumber($match[1]);
                    $bgnRaw   = $this->normalizeSpecialNumber($match[2]);
                    $eur      = $this->normalizePrice($eurRaw . ' EUR');
                    $bgnAsEur = $this->normalizePrice($bgnRaw . ' BGN');

                    if ($eur === null || $bgnAsEur === null) continue;
                    if (abs($eur - $bgnAsEur) <= 0.8 && $eur > 10) {
                        $candidates[] = $eur;
                    }
                }
            }
            if (!empty($candidates)) break;
        }

        return empty($candidates) ? null : round(max($candidates), 2);
    }

    protected function extractPriceFromJsonLd(string $html): ?float
    {
        preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/isu', $html, $matches);

        foreach ($matches[1] ?? [] as $block) {
            $json    = trim(html_entity_decode($block, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($json === '') continue;

            $decoded = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) continue;

            $price = $this->findPriceInJsonLd($decoded);
            if ($price !== null && $price > 0) return $price;
        }

        return null;
    }

    protected function findPriceInJsonLd(mixed $node): ?float
    {
        if (is_array($node)) {
            if (isset($node['price'])) {
                $currency = strtoupper(trim((string) ($node['priceCurrency'] ?? '')));
                $raw      = (string) $node['price'];

                $value = $this->parseRawNumber($raw);
                if ($value === null || $value <= 0) goto recurse;

                // Explicit EUR
                if ($currency === 'EUR') return round($value, 2);

                // Explicit BGN → convert
                if ($currency === 'BGN') return round($value / self::BGN_TO_EUR, 2);

                // No currency — heuristic: BGN values usually >= 2x EUR
                // If value looks like BGN (e.g. 486.91) convert, else treat as EUR
                if ($value > 50 && $value < 100000) {
                    // Try: if dividing by rate gives a "reasonable" EUR price
                    $asEur = round($value / self::BGN_TO_EUR, 2);
                    if ($asEur > 5 && $asEur < 50000) return $asEur;
                }

                return round($value, 2);
            }

            recurse:
            foreach ($node as $v) {
                $found = $this->findPriceInJsonLd($v);
                if ($found !== null) return $found;
            }
        }

        return null;
    }

    protected function extractByPatterns(string $html, array $patterns, ?string $forcedCurrency = null): ?float
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $raw    = $this->normalizeSpecialNumber($matches[1]);
                $suffix = $forcedCurrency ? (' ' . $forcedCurrency) : '';
                $price  = $this->normalizePrice($raw . $suffix);
                if ($price !== null && $price > 0) return $price;
            }
        }

        return null;
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * Парсира сурово число "1 234,56" / "1234.56" / "1.234,56" → float
     * БЕЗ currency conversion — само числото.
     */
    protected function parseRawNumber(string $value): ?float
    {
        $value = trim(strip_tags($value));
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[^\d,.]/', '', $value);

        if ($value === '') return null;

        // "1.234,56" → "1234.56"
        if (substr_count($value, ',') > 0 && substr_count($value, '.') > 0) {
            $lastComma = strrpos($value, ',');
            $lastDot   = strrpos($value, '.');
            if ($lastComma > $lastDot) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (substr_count($value, ',') > 0) {
            // "1234,56" → "1234.56"  /  "1,234" → "1234"
            if (preg_match('/,\d{2}$/', $value)) {
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif (substr_count($value, '.') > 1) {
            // "1.234.56" → вземи последната точка като десетичен разделител
            $parts   = explode('.', $value);
            $decimal = array_pop($parts);
            $value   = implode('', $parts) . '.' . $decimal;
        }

        if (!is_numeric($value)) return null;

        $f = (float) $value;
        return $f > 0 ? $f : null;
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
        $price    = trim(strip_tags($rawPrice));
        $price    = html_entity_decode($price, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $isBGN = str_contains($original, 'лв') || str_contains($original, 'BGN');
        $isEUR = str_contains($original, '€')  || str_contains($original, 'EUR');

        $price = str_replace(["\xc2\xa0", ' ', 'лв.', 'лв', 'BGN', 'EUR', '€'], '', $price);

        if (substr_count($price, ',') > 0 && substr_count($price, '.') > 0) {
            $lastComma = strrpos($price, ',');
            $lastDot   = strrpos($price, '.');
            if ($lastComma > $lastDot) {
                $price = str_replace('.', '', $price);
                $price = str_replace(',', '.', $price);
            } else {
                $price = str_replace(',', '', $price);
            }
        } elseif (substr_count($price, ',') > 0) {
            if (preg_match('/,\d{2}$/', $price)) {
                $price = str_replace('.', '', $price);
                $price = str_replace(',', '.', $price);
            } else {
                $price = str_replace(',', '', $price);
            }
        } elseif (substr_count($price, '.') > 1) {
            $parts   = explode('.', $price);
            $decimal = array_pop($parts);
            $price   = implode('', $parts) . '.' . $decimal;
        }

        $price = preg_replace('/[^\d\.]/', '', $price);

        if ($price === '' || !is_numeric($price)) return null;

        $price = (float) $price;
        if ($price <= 0) return null;

        if ($isBGN && !$isEUR) {
            $price = $price / self::BGN_TO_EUR;
        }

        return round($price, 2);
    }

    protected function prepareHtml(string $html): string
    {
        if ($html === '') return '';
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

        if ($text === '') return '';

        return mb_substr($text, 0, self::MAX_TEXT_LENGTH);
    }
}