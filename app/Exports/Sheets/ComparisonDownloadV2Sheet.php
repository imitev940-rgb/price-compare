<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ComparisonDownloadV2Sheet implements FromCollection, WithHeadings, WithTitle
{
    private array $pazaruvajStores;

    public function __construct(private Collection $products)
    {
        $this->pazaruvajStores = DB::table('pazaruvaj_offers')
            ->select('store_name')
            ->distinct()
            ->orderBy('store_name')
            ->pluck('store_name')
            ->filter()
            ->values()
            ->toArray();
    }

    public function title(): string
    {
        return 'Свалане V2';
    }

    public function headings(): array
    {
        $base = [
            'SKU',
            'Brand',
            'Product',
            'PCD (€)',
            'Our Price (€)',
            'Technopolis (€)',
            'Technomarket (€)',
            'Techmart (€)',
            'Zora (€)',
            'Tehnomix (€)',
        ];

        return array_merge($base, $this->pazaruvajStores);
    }

    public function collection(): Collection
    {
        return $this->products->map(function ($product) {
            $row = [
                'sku'          => $product->sku,
                'brand'        => $product->brand,
                'product'      => $product->name,
                'pcd'          => $this->formatNumber($product->pcd_price ?? null),
                'our_price'    => $this->formatNumber($product->our_price),
                'technopolis'  => $this->formatNumber($product->technopolis_price),
                'technomarket' => $this->formatNumber($product->technomarket_price),
                'techmart'     => $this->formatNumber($product->techmart_price),
                'zora'         => $this->formatNumber($product->zora_price),
                'tehnomix'     => $this->formatNumber($product->tehnomix_price),
            ];

            $offersByStore = collect($product->pazaruvaj_offers_list ?? [])
                ->groupBy('store_name')
                ->map(fn ($group) => $group->min('price'));

            foreach ($this->pazaruvajStores as $storeName) {
                $row[$storeName] = $this->formatNumber($offersByStore[$storeName] ?? null);
            }

            return $row;
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
