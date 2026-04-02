<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OwnProductPriceService
{
    public function getPrice(string $url): ?float
    {
        try {
            $response = Http::timeout(20)
                ->connectTimeout(8)
                ->retry(2, 700)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Accept-Language' => 'bg-BG,bg;q=0.9,en;q=0.8',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                    'Referer' => 'https://technika.bg/',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::warning('OwnProductPriceService: bad status', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $html = mb_substr($response->body(), 0, 900000);

            if (str_contains(mb_strtolower($url), 'technika.bg')) {
                $price = $this->extractTechnikaPrice($html);

                Log::info('OwnProductPriceService technika result', [
                    'url' => $url,
                    'price' => $price,
                ]);

                return $price;
            }

            return $this->extractGenericEuroPrice($html);
        } catch (\Throwable $e) {
            Log::warning('OwnProductPriceService failed', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function extractTechnikaPrice(string $html): ?float
    {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 1) Най-важно: цена със sup за стотинки
        // Примери:
        // €399<sup>99</sup>
        // €1,153<sup>00</sup>
        // €399<span>99</span>
        $patternsWithDecimals = [
            '/€\s*([0-9]{1,3}(?:[.,][0-9]{3})*|[0-9]+)\s*<(?:sup|span)[^>]*>\s*([0-9]{2})\s*<\/(?:sup|span)>/iu',
            '/€\s*([0-9]{1,3}(?:[.,][0-9]{3})*|[0-9]+)\s*[\.,]\s*([0-9]{2})/iu',
            '/€\s*([0-9]{1,3}(?:[.,][0-9]{3})*|[0-9]+)\s*\^\{?\s*([0-9]{2})\s*\}?/iu',
        ];

        foreach ($patternsWithDecimals as $pattern) {
            if (preg_match($pattern, $decoded, $m)) {
                $combined = $m[1] . '.' . $m[2];
                $value = $this->normalizeEuroPrice($combined);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        // 2) fallback - plain text, ако HTML е станал вече 399.99
        $plain = strip_tags($decoded);
        $plain = preg_replace('/\s+/u', ' ', $plain);
        $plain = trim($plain);

        if (preg_match('/€\s*([0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]{2})?|[0-9]+(?:[.,][0-9]{2}))/iu', $plain, $m)) {
            $value = $this->normalizeEuroPrice($m[1]);
            if ($value !== null) {
                return $value;
            }
        }

        // 3) fallback за цена преди /
        // €399.99 / 782.31 лв.
        if (preg_match('/€\s*([0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]{2})?|[0-9]+(?:[.,][0-9]{2}))\s*\//iu', $plain, $m)) {
            $value = $this->normalizeEuroPrice($m[1]);
            if ($value !== null) {
                return $value;
            }
        }

        Log::warning('Technika euro price not found', [
            'plain_excerpt' => mb_substr($plain, 0, 1500),
        ]);

        return null;
    }

    private function extractGenericEuroPrice(string $html): ?float
    {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = strip_tags($decoded);
        $plain = preg_replace('/\s+/u', ' ', $plain);
        $plain = trim($plain);

        $patterns = [
            '/€\s*([0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]{2})?|[0-9]+(?:[.,][0-9]{2}))/iu',
            '/([0-9]{1,3}(?:[.,][0-9]{3})*(?:[.,][0-9]{2})?|[0-9]+(?:[.,][0-9]{2}))\s*(?:€|EUR)\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $plain, $m)) {
                $value = $this->normalizeEuroPrice($m[1]);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        if (preg_match('/(priceCurrency|product:price:currency|currency)["\']?\s*[:=]\s*["\']EUR["\']/iu', $decoded)) {
            $structuredPricePatterns = [
                '/<meta[^>]+property=["\']product:price:amount["\'][^>]+content=["\']([^"\']+)["\']/iu',
                '/<meta[^>]+itemprop=["\']price["\'][^>]+content=["\']([^"\']+)["\']/iu',
                '/<meta[^>]+name=["\']price["\'][^>]+content=["\']([^"\']+)["\']/iu',
                '/"price"\s*:\s*"([^"]+)"/iu',
                '/"price"\s*:\s*([0-9\.,]+)/iu',
            ];

            foreach ($structuredPricePatterns as $pattern) {
                if (preg_match($pattern, $decoded, $m)) {
                    $value = $this->normalizeEuroPrice($m[1]);
                    if ($value !== null) {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    private function normalizeEuroPrice(string $raw): ?float
    {
        $price = trim($raw);

        if ($price === '') {
            return null;
        }

        $price = html_entity_decode($price, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $price = str_replace(["\xc2\xa0", ' '], '', $price);
        $price = str_replace(['^{', '}'], ['.', ''], $price);
        $price = preg_replace('/[^\d\.,]/u', '', $price);

        if ($price === '') {
            return null;
        }

        if (str_contains($price, ',') && str_contains($price, '.')) {
            $lastComma = strrpos($price, ',');
            $lastDot = strrpos($price, '.');

            if ($lastComma < $lastDot) {
                $price = str_replace(',', '', $price);
            } else {
                $price = str_replace('.', '', $price);
                $price = str_replace(',', '.', $price);
            }
        } elseif (str_contains($price, ',')) {
            $parts = explode(',', $price);

            if (count($parts) > 1 && strlen((string) end($parts)) === 2) {
                $price = str_replace(',', '.', $price);
            } else {
                $price = str_replace(',', '', $price);
            }
        }

        $price = preg_replace('/[^\d\.]/u', '', $price);

        if ($price === '' || !is_numeric($price)) {
            return null;
        }

        $value = (float) $price;

        if ($value <= 0 || $value > 999999.99) {
            return null;
        }

        return round($value, 2);
    }
}