<?php

namespace App\Http\Controllers;

use App\Jobs\ImportProductsJob;
use App\Models\ImportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductImportController extends Controller
{
    public function create(Request $request)
    {
        $search = trim((string) $request->get('search', ''));
        $status = trim((string) $request->get('status', ''));

        $latestImports = ImportJob::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('original_name', 'like', '%' . $search . '%');
            })
            ->when($status !== '' && in_array($status, ['pending', 'processing', 'completed', 'failed'], true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('products.import', compact('latestImports', 'search', 'status'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('file');

        $importsDir = storage_path('app/imports');

        if (!is_dir($importsDir)) {
            mkdir($importsDir, 0777, true);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'csv');
        $generatedName = Str::random(30) . '.' . $extension;
        $absolutePath = $importsDir . DIRECTORY_SEPARATOR . $generatedName;

        $file->move($importsDir, $generatedName);

        $relativePath = 'imports/' . $generatedName;

        \Log::info('IMPORT: file moved successfully', [
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
            'exists' => file_exists($absolutePath),
        ]);

        $importJob = ImportJob::create([
            'file_path' => $relativePath,
            'original_name' => $file->getClientOriginalName(),
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'imported_count' => 0,
            'updated_count' => 0,
            'error_count' => 0,
            'last_error' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);

        ImportProductsJob::dispatch($importJob->id);

        \Log::info('IMPORT: dispatch called', [
            'import_job_id' => $importJob->id,
            'file_path' => $relativePath,
        ]);

        return redirect()->route('products.import.create')
            ->with('success', 'Import стартира успешно.');
    }

    public function status(ImportJob $importJob)
    {
        return response()->json([
            'id' => $importJob->id,
            'status' => $importJob->status,
            'total_rows' => $importJob->total_rows,
            'processed_rows' => $importJob->processed_rows,
            'imported_count' => $importJob->imported_count,
            'updated_count' => $importJob->updated_count,
            'error_count' => $importJob->error_count,
            'progress_percent' => $importJob->progress_percent,
            'last_error' => $importJob->last_error,
            'started_at' => optional($importJob->started_at)->format('d.m.Y H:i:s'),
            'finished_at' => optional($importJob->finished_at)->format('d.m.Y H:i:s'),
        ]);
    }

    public function show(ImportJob $importJob)
    {
        $importJob->load('errors');

        return view('products.import-show', compact('importJob'));
    }

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="products_import_template.csv"',
        ];

        $columns = [
            'name',
            'sku',
            'ean',
            'brand',
            'model',
            'product_url',
            'our_price',
            'is_active',
        ];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');

            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, $columns);

            fputcsv($file, [
                'Пример продукт',
                '12345',
                '1234567890123',
                'Philips',
                'EP2330/10',
                'https://technika.bg/product/example',
                '499.99',
                '1',
            ]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}