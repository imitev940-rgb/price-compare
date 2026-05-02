<?php

namespace App\Exports;

use App\Models\PriceChangeSnapshot;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PriceChangesExport implements FromArray, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    protected PriceChangeSnapshot $snapshot;

    public function __construct(PriceChangeSnapshot $snapshot)
    {
        $this->snapshot = $snapshot;
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->snapshot->data ?? [] as $change) {
            $rows[] = [
                $change['sku'] ?? '',
                $change['name'] ?? '',
                $change['old_price'] !== null ? number_format((float) $change['old_price'], 2, '.', '') : '',
                $change['new_price'] !== null ? number_format((float) $change['new_price'], 2, '.', '') : '',
                $change['lowest_price'] !== null ? number_format((float) $change['lowest_price'], 2, '.', '') : '',
                $change['lowest_store'] ?? '',
            ];
        }
        return $rows;
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Име на продукт',
            'Стара цена',
            'Нова цена',
            'ННЦ',
            'Магазин (ННЦ)',
        ];
    }

    public function title(): string
    {
        return 'Промени на цени';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,
            'B' => 50,
            'C' => 15,
            'D' => 15,
            'E' => 15,
            'F' => 25,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '185FA5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        return [];
    }
}
