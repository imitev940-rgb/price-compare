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

            $html = mb_substr($response->body(), 0, 700000);

            if (str_contains(mb_strtolower($url), 'technika.bg')) {
                return $this->extractTechnikaPrice($html);
            }

            return $this->extractGenericPrice($html);
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

        // 1) Най-сигурно: "Цена на продукта"
        if (preg_match('/Цена на продукта:\s*€\s*([0-9\.,\^\{\}\s]+)/u', $decoded, $m)) {
            $value = $this->normalizeTechnikaEuro($m[1]);
            if ($value !== null) {
                return $value;
            }
        }

        // 2) Само блокът между "Цена:" и "ПЦД:"
        if (preg_match('/Цена:(.*?)ПЦД:/isu', $decoded, $block)) {
            $priceBlock = $block[1];

            // първо 1153.00 EUR
            if (preg_match('/([0-9\.,\^\{\}\s]+)\s*EUR\b/u', $priceBlock, $m)) {
                $value = $this->normalizeTechnikaEuro($m[1]);
                if ($value !== null) {
                    return $value;
                }
            }

            // после €1,153^{00} или €1,153.00
            if (preg_match('/€\s*([0-9\.,\^\{\}\s]+)/u', $priceBlock, $m)) {
                $value = $this->normalizeTechnikaEuro($m[1]);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        // 3) Plain text fallback
        $plain = strip_tags($decoded);
        $plain = preg_replace('/\s+/u', ' ', $plain);
        $plain = trim($plain);

        if (preg_match('/Цена на продукта:\s*€\s*([0-9\.,\s]+)/u', $plain, $m)) {
            $value = $this->normalizeTechnikaEuro($m[1]);
            if ($value !== null) {
                return $value;
            }
        }

        // взима само първата цена след "Цена:"
        if (preg_match('/Цена:\s*€\s*([0-9\.,\s]+)/u', $plain, $m)) {
            $value = $this->normalizeTechnikaEuro($m[1]);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function extractGenericPrice(string $html): ?float
    {
        $patterns = [
            '/<meta[^>]+property=["\']product:price:amount["\'][^>]+content=["\']([^"\']+)["\']/iu',
            '/<meta[^>]+itemprop=["\']price["\'][^>]+content=["\']([^"\']+)["\']/iu',
            '/<meta[^>]+name=["\']price["\'][^>]+content=["\']([^"\']+)["\']/iu',
            '/"price"\s*:\s*"([^"]+)"/iu',
            '/"price"\s*:\s*([0-9\.,]+)/iu',
            '/([0-9]{1,6}[.,][0-9]{2})\s*(€|EUR|лв\.?|BGN)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $price = $this->normalizePriceWithCurrency($matches[1], $matches[2] ?? null);

                if ($price !== null && $price > 0) {
                    return $price;
                }
            }
        }

        return null;
    }

    private function normalizeTechnikaEuro(string $raw): ?float
    {
        $price = trim($raw);

        // 1,153^{00} -> 1,153.00
        $price = str_replace(['^{', '}'], ['.', ''], $price);

        // махаме интервали
        $price = preg_replace('/\s+/u', '', $price);

        // пазим само цифри, точки и запетаи
        $price = preg_replace('/[^\d\.,]/u', '', $price);

        if ($price === '') {
            return null;
        }

        if (str_contains($price, ',') && str_contains($price, '.')) {
            $lastComma = strrpos($price, ',');
            $lastDot = strrpos($price, '.');

            if ($lastComma < $lastDot) {
                // 1,153.00
                $price = str_replace(',', '', $price);
            } else {
                // 1.153,00
                $price = str_replace('.', '', $price);
                $price = str_replace(',', '.', $price);
            }
        } elseif (str_contains($price, ',')) {
            $parts = explode(',', $price);

            if (count($parts) > 1 && strlen(end($parts)) === 2) {
                // 1153,00
                $price = str_replace(',', '.', $price);
            } else {
                // 1,153
                $price = str_replace(',', '', $price);
            }
        }

        $price = preg_replace('/[^\d\.]/u', '', $price);

        if ($price === '' || !is_numeric($price)) {
            return null;
        }

        $value = (float) $price;

        if ($value <= 0 || $value > 99999.99) {
            return null;
        }

        return round($value, 2);
    }

    private function normalizePriceWithCurrency(string $rawPrice, ?string $currency = null): ?float
    {
        $original = trim($rawPrice);

        if ($original === '') {
            return null;
        }

        $price = html_entity_decode($original, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $price = strip_tags($price);
        $price = str_replace(["\xc2\xa0", ' '], '', $price);

        $currencyText = mb_strtolower((string) $currency . ' ' . $original);

        $isBGN = str_contains($currencyText, 'лв') || str_contains($currencyText, 'bgn');
        $isEUR = str_contains($currencyText, '€') || str_contains($currencyText, 'eur');

        if (str_contains($price, ',') && str_contains($price, '.')) {
            $lastComma = strrpos($price, ',');
            $lastDot = strrpos($price, '.');

            if ($lastComma > $lastDot) {
                $price = str_replace('.', '', $price);
                $price = str_replace(',', '.', $price);
            } else {
                $price = str_replace(',', '', $price);
            }
        } elseif (str_contains($price, ',')) {
            $parts = explode(',', $price);

            if (count($parts) > 1 && strlen(end($parts)) === 2) {
                $price = str_replace(',', '.', $price);
            } else {
                $price = str_replace(',', '', $price);
            }
        }

        $price = preg_replace('/[^\d\.]/', '', $price);

        if ($price === '' || !is_numeric($price)) {
            return null;
        }

        $price = (float) $price;

        if ($isBGN && !$isEUR) {
            $price = $price / 1.95583;
        }

        return round($price, 2);
    }
}