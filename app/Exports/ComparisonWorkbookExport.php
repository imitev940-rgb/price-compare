<?php

namespace App\Exports;

use App\Exports\Sheets\ComparisonDownloadV2Sheet;
use App\Exports\Sheets\ComparisonPazaruvajOffersSheet;
use App\Exports\Sheets\ComparisonPriceDownSheet;
use App\Exports\Sheets\ComparisonPriceUpSheet;
use App\Exports\Sheets\ComparisonSummarySheet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ComparisonWorkbookExport implements WithMultipleSheets
{
    public function __construct(private Collection $products)
    {
    }

    public function sheets(): array
    {
        return [
            new ComparisonSummarySheet($this->products),
            new ComparisonDownloadV2Sheet($this->products),
            new ComparisonPriceDownSheet($this->products),
            new ComparisonPriceUpSheet($this->products),
            new ComparisonPazaruvajOffersSheet($this->products),
        ];
    }
}