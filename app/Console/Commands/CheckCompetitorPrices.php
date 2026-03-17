<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CompetitorLink;
use Illuminate\Support\Facades\Http;

class CheckCompetitorPrices extends Command
{
    protected $signature = 'prices:check';
    protected $description = 'Check competitor prices';

    public function handle()
    {
        $links = CompetitorLink::where('is_active', 1)->get();

        foreach ($links as $link) {
            $this->info('Checking: ' . $link->product_url);

            try {
                $response = Http::timeout(20)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0 Safari/537.36',
                        'Accept-Language' => 'bg-BG,bg;q=0.9,en;q=0.8',
                    ])
                    ->get($link->product_url);

                if (! $response->successful()) {
                    $this->error('Could not load page.');
                    continue;
                }

                $html = $response->body();

                $price = null;

                if (str_contains($link->product_url, 'jarcomputers.com')) {
                    $price = $this->extractJarPrice($html);
                }

                if ($price !== null) {
                    $link->update([
                        'last_price' => $price,
                        'last_checked_at' => now(),
                    ]);

                    $this->info('Price updated: ' . $price);
                } else {
                    $this->warn('Price not found.');
                }

            } catch (\Throwable $e) {
                $this->error('Error: ' . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }

    private function extractJarPrice(string $html): ?float
    {
        $patterns = [
            '/"price"\s*:\s*"([\d\.,]+)"/i',
            '/property="product:price:amount"\s+content="([\d\.,]+)"/i',
            '/itemprop="price"\s+content="([\d\.,]+)"/i',
            '/class="[^"]*price[^"]*"[^>]*>\s*([\d\.,]+)\s*лв/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $price = $this->normalizePrice($matches[1]);

                if ($price !== null && $price > 0) {
                    return $price;
                }
            }
        }

        return null;
    }

    private function normalizePrice(string $rawPrice): ?float
    {
        $price = trim($rawPrice);
        $price = str_replace(["\xc2\xa0", ' '], '', $price);

        if (substr_count($price, ',') > 0 && substr_count($price, '.') > 0) {
            $price = str_replace('.', '', $price);
            $price = str_replace(',', '.', $price);
        } elseif (substr_count($price, ',') > 0) {
            $price = str_replace(',', '.', $price);
        }

        return is_numeric($price) ? round((float) $price, 2) : null;
    }
}