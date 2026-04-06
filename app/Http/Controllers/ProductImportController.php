<?php

namespace App\Http\Controllers;

use App\Imports\ProductsImport;
use App\Jobs\ImportProductsJob;
use App\Models\ImportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ProductImportController extends Controller
{
    // ================================================================
    // CREATE (списък с импорти)
    // ================================================================

    public function create(Request $request)
    {
        $allowedPerPage = [10, 20, 50];
        $perPage = (int) $request->get('per_page', 20);
        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 20;
        }

        $search = trim((string) $request->get('search', ''));
        $status = trim((string) $request->get('status', ''));

        $validStatuses = ['pending', 'processing', 'completed', 'failed'];

        $latestImports = ImportJob::query()
            ->when($search !== '', fn ($q) => $q
                ->where('original_name', 'like', '%' . $search . '%')
            )
            ->when($status !== '' && in_array($status, $validStatuses, true), fn ($q) => $q
                ->where('status', $status)
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('products.import', compact(
            'latestImports',
            'search',
            'status',
            'perPage',
            'validStatuses'
        ));
    }

    // ================================================================
    // STORE (качване на файл)
    // ================================================================

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $file       = $request->file('file');
        $importsDir = storage_path('app/imports');

        if (! is_dir($importsDir)) {
            mkdir($importsDir, 0755, true);
        }

        $extension     = $file->getClientOriginalExtension();
        $generatedName = Str::random(30) . '.' . $extension;
        $absolutePath  = $importsDir . DIRECTORY_SEPARATOR . $generatedName;
        $relativePath  = 'imports/' . $generatedName;

        $file->move($importsDir, $generatedName);

        Log::info('Import file moved', [
            'relative_path' => $relativePath,
            'exists'        => file_exists($absolutePath),
        ]);

        $importJob = ImportJob::create([
            'file_path'      => $relativePath,
            'original_name'  => $file->getClientOriginalName(),
            'status'         => 'pending',
            'total_rows'     => 0,
            'processed_rows' => 0,
            'imported_count' => 0,
            'updated_count'  => 0,
            'error_count'    => 0,
            'last_error'     => null,
            'started_at'     => null,
            'finished_at'    => null,
        ]);

        ImportProductsJob::dispatch($importJob->id);

        Log::info('Import dispatched', [
            'import_job_id' => $importJob->id,
            'file_path'     => $relativePath,
        ]);

        return redirect()
            ->route('products.import.create')
            ->with('success', 'Импортът стартира успешно. (Job ID: ' . $importJob->id . ')');
    }

    // ================================================================
    // SHOW (детайли за импорт)
    // ================================================================

    public function show(ImportJob $importJob)
    {
        $importJob->load('errors');
        return view('products.import-show', compact('importJob'));
    }

    // ================================================================
    // STATUS (AJAX polling)
    // ================================================================

    public function status(ImportJob $importJob)
    {
        return response()->json([
            'id'               => $importJob->id,
            'status'           => $importJob->status,
            'total_rows'       => $importJob->total_rows,
            'processed_rows'   => $importJob->processed_rows,
            'imported_count'   => $importJob->imported_count,
            'updated_count'    => $importJob->updated_count,
            'error_count'      => $importJob->error_count,
            'progress_percent' => $importJob->progress_percent,
            'last_error'       => $importJob->last_error,
            'started_at'       => optional($importJob->started_at)->format('d.m.Y H:i:s'),
            'finished_at'      => optional($importJob->finished_at)->format('d.m.Y H:i:s'),
        ]);
    }

    // ================================================================
    // DESTROY
    // ================================================================

    public function destroy(ImportJob $importJob)
    {
        if ($importJob->status === 'processing') {
            return back()->with('error', 'Не може да се изтрие активен импорт.');
        }

        $absolutePath = storage_path('app/' . $importJob->file_path);
        if (file_exists($absolutePath)) {
            unlink($absolutePath);
        }

        $importJob->delete();

        return redirect()
            ->route('products.import.create')
            ->with('success', 'Импортът беше изтрит.');
    }

    // ================================================================
    // DOWNLOAD TEMPLATE (XLS)
    // ================================================================

    public function downloadTemplate()
    {
        $headers = [
            'name',
            'sku',
            'ean',
            'brand',
            'model',
            'product_url',
            'our_price',
            'is_active',
            'scan_priority',
        ];

        $example = [
            'Кафеавтомат Philips EP2330',
            'EP2330/10',
            '8710103867883',
            'Philips',
            'EP2330/10',
            'https://technika.bg/product/example',
            '499.99',
            '1',
            'normal',
        ];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header ред
        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        // Пример ред
        foreach ($example as $col => $value) {
            $sheet->setCellValueByColumnAndRow($col + 1, 2, $value);
        }

        // Стил на header-а
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DDEBF7'],
            ],
        ]);

        // Auto width
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'products_import_template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}