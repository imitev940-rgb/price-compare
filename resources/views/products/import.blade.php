@extends('layouts.app')

@section('content')

<div class="cmp-page-head cmp-page-head-modern">
    <div>
        <h1 class="cmp-title">Import Products</h1>
        <p class="cmp-subtitle">CSV / Excel import с progress</p>
    </div>
</div>

@if(session('success'))
    <div class="alert-success">
        {{ session('success') }}
    </div>
@endif

<a href="{{ route('products.import.template') }}" class="btn" style="margin-bottom:10px;">
    Download Template
</a>

<div class="cmp-toolbar-shell" style="margin-bottom: 20px;">
    <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="cmp-toolbar-form">
        @csrf

        <div class="cmp-toolbar-main">
            <div class="cmp-toolbar-field">
                <label class="cmp-toolbar-label">Файл</label>
                <input type="file" name="file" required class="cmp-toolbar-input">
            </div>
        </div>

        <div class="cmp-toolbar-side">
            <button type="submit" class="btn">Start Import</button>
        </div>
    </form>
</div>

<div class="cmp-table-wrap">
    <table class="cmp-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>File</th>
                <th>Status</th>
                <th>Progress</th>
                <th>Imported</th>
                <th>Updated</th>
                <th>Errors</th>
                <th>Started</th>
                <th>Finished</th>
            </tr>
        </thead>
        <tbody>
            @forelse($latestImports as $job)
                <tr class="import-job-row" data-job-id="{{ $job->id }}">
                    <td>#{{ $job->id }}</td>
                    <td>{{ $job->original_name }}</td>
                    <td class="job-status">{{ $job->status }}</td>
                    <td style="min-width: 220px;">
                        <div style="background:#e5e7eb;border-radius:999px;overflow:hidden;height:10px;">
                            <div class="job-progress-bar" style="height:10px;width:{{ $job->progress_percent }}%;background:#2563eb;"></div>
                        </div>
                        <div class="job-progress-text" style="margin-top:6px;font-size:12px;">
                            {{ $job->progress_percent }}% ({{ $job->processed_rows }}/{{ $job->total_rows }})
                        </div>
                    </td>
                    <td class="job-imported">{{ $job->imported_count }}</td>
                    <td class="job-updated">{{ $job->updated_count }}</td>
                    <td class="job-errors">{{ $job->error_count }}</td>
                    <td class="job-started">{{ optional($job->started_at)->format('d.m.Y H:i:s') }}</td>
                    <td class="job-finished">{{ optional($job->finished_at)->format('d.m.Y H:i:s') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align:center;">Няма import-и.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const rows = document.querySelectorAll('.import-job-row');

        const refreshJob = async (row) => {
            const jobId = row.dataset.jobId;

            try {
                const response = await fetch(`/products/import/${jobId}/status`);
                const data = await response.json();

                row.querySelector('.job-status').textContent = data.status;
                row.querySelector('.job-progress-bar').style.width = data.progress_percent + '%';
                row.querySelector('.job-progress-text').textContent =
                    `${data.progress_percent}% (${data.processed_rows}/${data.total_rows})`;
                row.querySelector('.job-imported').textContent = data.imported_count;
                row.querySelector('.job-updated').textContent = data.updated_count;
                row.querySelector('.job-errors').textContent = data.error_count;
                row.querySelector('.job-started').textContent = data.started_at ?? '';
                row.querySelector('.job-finished').textContent = data.finished_at ?? '';

                return data.status;
            } catch (e) {
                return null;
            }
        };

        const startPolling = () => {
            setInterval(async () => {
                for (const row of rows) {
                    const status = row.querySelector('.job-status').textContent.trim();

                    if (status === 'pending' || status === 'processing') {
                        await refreshJob(row);
                    }
                }
            }, 3000);
        };

        startPolling();
    });
</script>

@endsection