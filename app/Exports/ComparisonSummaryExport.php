<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ComparisonSummaryExport implements FromCollection, WithHeadings
{
    public function __construct(private Collection $products)
    {
    }

    public function headings(): array
    {
        return [
            'Product',
            'SKU',
            'EAN',
            'Brand',
            'Status',
            'Our Price (€)',

            'Technopolis (€)',
            'Technopolis Diff (€)',
            'Technopolis Diff (%)',

            'Technomarket (€)',
            'Technomarket Diff (€)',
            'Technomarket Diff (%)',

            'Techmart (€)',
            'Techmart Diff (€)',
            'Techmart Diff (%)',

            'Tehnomix (€)',
            'Tehnomix Diff (€)',
            'Tehnomix Diff (%)',

            'Zora (€)',
            'Zora Diff (€)',
            'Zora Diff (%)',

            'Pazaruvaj Lowest (€)',
            'Lowest Market Price (€)',
            'Lowest Direct Price (€)',
            'Offers',
            'Our Position',
            'Difference (€)',
            'Difference (%)',
            'Pazaruvaj Offers Details',
        ];
    }

    public function collection(): Collection
    {
        return $this->products->map(function ($product) {
            $offersText = collect($product->pazaruvaj_offers_list ?? [])
                ->map(function ($offer) {
                    $position = $offer->position ?? '-';
                    $store    = $offer->store_name ?? '-';
                    $price    = isset($offer->price) && $offer->price !== null
                        ? number_format((float) $offer->price, 2, '.', '')
                        : '-';

                    return "#{$position} {$store} - {$price} €";
                })
                ->implode(' | ');

            return [
                'product'                   => $product->name,
                'sku'                       => $product->sku,
                'ean'                       => $product->ean,
                'brand'                     => $product->brand,
                'status'                    => $product->is_active ? 'Active' : 'Inactive',
                'our_price'                 => $this->formatNumber($product->our_price),

                'technopolis'               => $this->formatNumber($product->technopolis_price),
                'technopolis_diff_euro'     => $this->formatNumber($product->technopolis_diff_euro),
                'technopolis_diff_percent'  => $this->formatNumber($product->technopolis_diff_percent),

                'technomarket'              => $this->formatNumber($product->technomarket_price),
                'technomarket_diff_euro'    => $this->formatNumber($product->technomarket_diff_euro),
                'technomarket_diff_percent' => $this->formatNumber($product->technomarket_diff_percent),

                'techmart'                  => $this->formatNumber($product->techmart_price),
                'techmart_diff_euro'        => $this->formatNumber($product->techmart_diff_euro),
                'techmart_diff_percent'     => $this->formatNumber($product->techmart_diff_percent),

                'tehnomix'                  => $this->formatNumber($product->tehnomix_price),
                'tehnomix_diff_euro'        => $this->formatNumber($product->tehnomix_diff_euro),
                'tehnomix_diff_percent'     => $this->formatNumber($product->tehnomix_diff_percent),

                'zora'                      => $this->formatNumber($product->zora_price),
                'zora_diff_euro'            => $this->formatNumber($product->zora_diff_euro),
                'zora_diff_percent'         => $this->formatNumber($product->zora_diff_percent),

                'pazaruvaj_lowest'          => $this->formatNumber($product->pazaruvaj_lowest_price),
                'lowest_market_price'       => $this->formatNumber($product->lowest_market_price),
                'lowest_direct_price'       => $this->formatNumber($product->lowest_direct_price),
                'offers'                    => $product->offers_count ?? 0,
                'our_position'              => $product->pazaruvaj_our_position,
                'difference_amount'         => $this->formatNumber($product->difference_amount),
                'difference_percent'        => $this->formatNumber($product->difference_percent),
                'pazaruvaj_offers_details'  => $offersText,
            ];
        });
    }

    private function formatNumber($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}