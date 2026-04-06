<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ComparisonPriceDownSheet implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    public function __construct(private Collection $products)
    {
    }

    public function title(): string
    {
        return 'Сваляне';
    }

    public function headings(): array
    {
        return [
            'Продукт',
            'Наша цена (€)',
            'Най-ниска цена (€)',
            'Магазин',
            'Позиция',
            'Разлика (€)',
            'Разлика (%)',
        ];
    }

    public function collection(): Collection
    {
        return $this->products
            ->filter(function ($product) {
                // Не сме на първо място ИЛИ сме равни с конкурента пред нас
                if (($product->pazaruvaj_offers_count ?? 0) === 0) return false;
                if ($product->pazaruvaj_our_position === null) return false;

                return (int) $product->pazaruvaj_our_position > 1;
            })
            ->sortByDesc(fn ($p) => abs((float) ($p->difference_amount ?? 0)))
            ->map(function ($product) {
                return [
                    'product'          => $product->name,
                    'our_price'        => $this->formatNumber($product->our_price),
                    'lowest_price'     => $this->formatNumber($product->pazaruvaj_lowest_price),
                    'store'            => $product->pazaruvaj_lowest_store ?? '—',
                    'position'         => $product->pazaruvaj_our_position ? '#' . $product->pazaruvaj_our_position : '—',
                    'diff_euro'        => $this->formatNumber($product->difference_amount),
                    'diff_percent'     => $this->formatNumber($product->difference_percent),
                ];
            })
            ->values();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => 'DC2626']],
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