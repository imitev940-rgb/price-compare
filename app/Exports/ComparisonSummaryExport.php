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
            'Technomarket (€)',
            'Zora (€)',
            'Pazaruvaj Lowest (€)',
            'Lowest Market Price (€)',
            'Offers',
            'Our Position',
            'Position Label',
            'Difference (€)',
            'Difference (%)',
        ];
    }

    public function collection(): Collection
    {
        return $this->products->map(function ($product) {
            return [
                'product' => $product->name,
                'sku' => $product->sku,
                'ean' => $product->ean,
                'brand' => $product->brand,
                'status' => $product->is_active ? 'Active' : 'Inactive',
                'our_price' => $product->our_price,
                'technopolis' => $product->technopolis_link?->last_price,
                'technomarket' => $product->technomarket_link?->last_price,
                'zora' => $product->zora_link?->last_price,
                'pazaruvaj_lowest' => $product->pazaruvaj_lowest_price,
                'lowest_market_price' => $product->lowest_market_price,
                'offers' => $product->offers_count,
                'our_position' => $product->pazaruvaj_our_position,
                'position_label' => $product->position_label,
                'difference_amount' => $product->difference_amount,
                'difference_percent' => $product->difference_percent,
            ];
        });
    }
}