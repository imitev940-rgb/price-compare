<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ComparisonPazaruvajOffersSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(private Collection $products)
    {
    }

    public function title(): string
    {
        return 'Pazaruvaj Offers';
    }

    public function headings(): array
    {
        return [
            'Product ID',
            'Product',
            'SKU',
            'EAN',
            'Brand',
            'Our Price (€)',
            'Pazaruvaj Lowest (€)',
            'Our Position',
            'Offer Position',
            'Store Name',
            'Offer Price (€)',
            'Difference vs Offer (€)',
            'Difference vs Offer (%)',
            'Is Lowest',
            'Offer URL',
        ];
    }

    public function collection(): Collection
    {
        $rows = collect();

        foreach ($this->products as $product) {
            $offers = $product->pazaruvaj_offers_list ?? collect();

            if ($offers->count() === 0) {
                $rows->push([
                    'product_id' => $product->id,
                    'product' => $product->name,
                    'sku' => $product->sku,
                    'ean' => $product->ean,
                    'brand' => $product->brand,
                    'our_price' => $this->formatNumber($product->our_price),
                    'pazaruvaj_lowest' => $this->formatNumber($product->pazaruvaj_lowest_price),
                    'our_position' => $product->pazaruvaj_our_position,
                    'offer_position' => null,
                    'store_name' => null,
                    'offer_price' => null,
                    'difference_vs_offer_euro' => null,
                    'difference_vs_offer_percent' => null,
                    'is_lowest' => null,
                    'offer_url' => null,
                ]);

                continue;
            }

            foreach ($offers as $offer) {
                $offerPrice = isset($offer->price) ? (float) $offer->price : null;
                $ourPrice = $product->our_price !== null ? (float) $product->our_price : null;

                $diffEuro = null;
                $diffPercent = null;

                if ($ourPrice !== null && $offerPrice !== null && $offerPrice > 0) {
                    $diffEuro = round($ourPrice - $offerPrice, 2);
                    $diffPercent = round((($ourPrice - $offerPrice) / $offerPrice) * 100, 2);
                }

                $rows->push([
                    'product_id' => $product->id,
                    'product' => $product->name,
                    'sku' => $product->sku,
                    'ean' => $product->ean,
                    'brand' => $product->brand,
                    'our_price' => $this->formatNumber($product->our_price),
                    'pazaruvaj_lowest' => $this->formatNumber($product->pazaruvaj_lowest_price),
                    'our_position' => $product->pazaruvaj_our_position,
                    'offer_position' => $offer->position,
                    'store_name' => $offer->store_name,
                    'offer_price' => $this->formatNumber($offer->price),
                    'difference_vs_offer_euro' => $this->formatNumber($diffEuro),
                    'difference_vs_offer_percent' => $this->formatNumber($diffPercent),
                    'is_lowest' => !empty($offer->is_lowest) ? 'Yes' : 'No',
                    'offer_url' => $offer->offer_url,
                ]);
            }
        }

        return $rows;
    }

    private function formatNumber($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}