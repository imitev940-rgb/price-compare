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
            'Product',
            'SKU',
            'EAN',
            'Brand',
            'Our Price (€)',
            'Offer Position',
            'Store Name',
            'Offer Price (€)',
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
                    'product' => $product->name,
                    'sku' => $product->sku,
                    'ean' => $product->ean,
                    'brand' => $product->brand,
                    'our_price' => $product->our_price,
                    'offer_position' => null,
                    'store_name' => null,
                    'offer_price' => null,
                    'is_lowest' => null,
                    'offer_url' => null,
                ]);

                continue;
            }

            foreach ($offers as $offer) {
                $rows->push([
                    'product' => $product->name,
                    'sku' => $product->sku,
                    'ean' => $product->ean,
                    'brand' => $product->brand,
                    'our_price' => $product->our_price,
                    'offer_position' => $offer->position,
                    'store_name' => $offer->store_name,
                    'offer_price' => $offer->price,
                    'is_lowest' => $offer->is_lowest ? 'Yes' : 'No',
                    'offer_url' => $offer->offer_url,
                ]);
            }
        }

        return $rows;
    }
}