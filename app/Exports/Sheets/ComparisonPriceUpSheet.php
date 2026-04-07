<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ComparisonPriceUpSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    public function __construct(private Collection $products)
    {
    }

    public function title(): string
    {
        return 'Вдигане';
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Продукт',
            'ПЦД (€)',
            'Наша цена (€)',
            'Следваща цена (€)',
            'Следващ магазин',
            'Разлика (€)',
            'Разлика (%)',
        ];
    }

    public function collection(): Collection
    {
        return $this->products
            ->filter(function ($product) {
                if (($product->pazaruvaj_offers_count ?? 0) < 2) return false;
                if ((int) ($product->pazaruvaj_our_position ?? 0) !== 1) return false;

                $ourPrice = (float) ($product->our_price ?? 0);
                if ($ourPrice <= 0) return false;

                $offers = collect($product->pazaruvaj_offers_list ?? [])
                    ->filter(fn ($o) => $o->price !== null && (float) $o->price > 0)
                    ->sortBy('price')
                    ->values();

                $next = $offers->first(fn ($o) => (float) $o->price > $ourPrice);
                return $next && ((float) $next->price - $ourPrice) > 5;
            })
            ->map(function ($product) {
                $ourPrice = (float) ($product->our_price ?? 0);

                $offers = collect($product->pazaruvaj_offers_list ?? [])
                    ->filter(fn ($o) => $o->price !== null && (float) $o->price > 0)
                    ->sortBy('price')
                    ->values();

                $next      = $offers->first(fn ($o) => (float) $o->price > $ourPrice);
                $nextPrice = $next ? (float) $next->price : null;
                $nextStore = $next?->store_name ?? '—';

                $leadEuro    = $nextPrice !== null ? round($nextPrice - $ourPrice, 2) : null;
                $leadPercent = ($nextPrice !== null && $nextPrice > 0)
                    ? round((($nextPrice - $ourPrice) / $nextPrice) * 100, 2)
                    : null;

                return [
                    'sku'          => $product->sku ?? '—',
                    'product'      => $product->name,
                    'pcd_price'    => $this->formatNumber($product->pcd_price),
                    'our_price'    => $this->formatNumber($ourPrice),
                    'next_price'   => $this->formatNumber($nextPrice),
                    'next_store'   => $nextStore,
                    'lead_euro'    => $this->formatNumber($leadEuro),
                    'lead_percent' => $this->formatNumber($leadPercent),
                ];
            })
            ->sortByDesc(fn ($r) => (float) ($r['lead_euro'] ?? 0))
            ->values();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '16A34A']],
                'alignment' => ['horizontal' => 'center'],
            ],
        ];
    }

    private function formatNumber($value): ?string
    {
        if ($value === null || $value === '') return null;
        return number_format((float) $value, 2, '.', '');
    }
}