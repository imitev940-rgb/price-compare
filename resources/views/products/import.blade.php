@extends('layouts.app')

@section('content')

<div class="cmp-page-head cmp-page-head-modern">
    <div>
        <h1 class="cmp-title">Import Products</h1>
        <p class="cmp-subtitle">CSV import с progress, history и грешки</p>
    </div>
</div>

@if(session('success'))
    <div class="alert-success" style="margin-bottom:15px;">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="alert-danger" style="margin-bottom:15px;">
        {{ $errors->first() }}
    </div>
@endif

<div style="display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap;">
    <a href="{{ route('products.import.template') }}" class="btn">
        Download Template
    </a>
</div>

<div class="cmp-toolbar-shell" style="margin-bottom: 20px;">
    <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="cmp-toolbar-form">
        @csrf

        <div class="cmp-toolbar-main">
            <div class="cmp-toolbar-field">
                <label class="cmp-toolbar-label">Файл</label>
                <input type="file" name="file" required class="cmp-toolbar-input" accept=".csv,.txt">
                <small style="display:block; margin-top:6px; opacity:.75;">
                    Поддържани формати: .XLS, .XLSx
                </small>
            </div>
        </div>

        <div class="cmp-toolbar-side">
            <button type="submit" class="btn">Start Import</button>
        </div>
    </form>
</div>

<div class="cmp-toolbar-shell" style="margin-bottom: 20px;">
    <form method="GET" action="{{ route('products.import.create') }}" class="cmp-toolbar-form">
        <div class="cmp-toolbar-main" style="display:flex; gap:12px; flex-wrap:wrap;">
            <div class="cmp-toolbar-field" style="min-width:260px;">
                <label class="cmp-toolbar-label">Search file</label>
                <input
                    type="text"
                    name="search"
                    value="{{ $search ?? '' }}"
                    class="cmp-toolbar-input"
                    placeholder="Търси по име на файл..."
                >
            </div>

            <div class="cmp-toolbar-field" style="min-width:180px;">
                <label class="cmp-toolbar-label">Status</label>
                <select name="status" class="cmp-toolbar-input">
                    <option value="">Всички</option>
                    <option value="pending" {{ ($status ?? '') === 'pending' ? 'selected' : '' }}>pending</option>
                    <option value="processing" {{ ($status ?? '') === 'processing' ? 'selected' : '' }}>processing</option>
                    <option value="completed" {{ ($status ?? '') === 'completed' ? 'selected' : '' }}>completed</option>
                    <option value="failed" {{ ($status ?? '') === 'failed' ? 'selected' : '' }}>failed</option>
                </select>
            </div>
        </div>

        <div class="cmp-toolbar-side" style="display:flex; gap:10px;">
            <button type="submit" class="btn">Filter</button>

            <a href="{{ route('products.import.create') }}" class="btn" style="text-decoration:none;">
                Clear
            </a>
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
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($latestImports as $job)
                <tr class="import-job-row" data-job-id="{{ $job->id }}">
                    <td>#{{ $job->id }}</td>

                    <td>
                        <div style="font-weight:600;">{{ $job->original_name }}</div>

                        @if($job->last_error)
                            <div class="job-last-error" style="font-size:12px; color:#dc2626; margin-top:4px; max-width:320px;">
                                {{ $job->last_error }}
                            </div>
                        @else
                            <div class="job-last-error" style="display:none;"></div>
                        @endif
                    </td>

                    <td class="job-status">
                        @if($job->status === 'completed')
                            <span style="padding:4px 10px; border-radius:999px; background:#dcfce7; color:#166534; font-size:12px; font-weight:700;">
                                completed
                            </span>
                        @elseif($job->status === 'failed')
                            <span style="padding:4px 10px; border-radius:999px; background:#fee2e2; color:#991b1b; font-size:12px; font-weight:700;">
                                failed
                            </span>
                        @elseif($job->status === 'processing')
                            <span style="padding:4px 10px; border-radius:999px; background:#dbeafe; color:#1d4ed8; font-size:12px; font-weight:700;">
                                processing
                            </span>
                        @else
                            <span style="padding:4px 10px; border-radius:999px; background:#f3f4f6; color:#374151; font-size:12px; font-weight:700;">
                                {{ $job->status }}
                            </span>
                        @endif
                    </td>

                    <td style="min-width:240px;">
                        <div style="background:#e5e7eb;border-radius:999px;overflow:hidden;height:10px;">
                            <div class="job-progress-bar" style="height:10px;width:{{ $job->progress_percent }}%;background:#2563eb;transition: width .35s ease;"></div>
                        </div>
                        <div class="job-progress-text" style="margin-top:6px;font-size:12px;">
                            {{ $job->progress_percent }}% ({{ $job->processed_rows }}/{{ $job->total_rows }})
                        </div>
                    </td>

                    <td class="job-imported">{{ $job->imported_count }}</td>
                    <td class="job-updated">{{ $job->updated_count }}</td>

                    <td class="job-errors">
                        @if((int) $job->error_count > 0)
                            <span style="font-weight:700; color:#dc2626;">
                                {{ $job->error_count }}
                            </span>
                        @else
                            <span>{{ $job->error_count }}</span>
                        @endif
                    </td>

                    <td class="job-started">{{ optional($job->started_at)->format('d.m.Y H:i:s') }}</td>
                    <td class="job-finished">{{ optional($job->finished_at)->format('d.m.Y H:i:s') }}</td>

                    <td>
                        <a href="{{ route('products.import.show', $job) }}" class="btn" style="padding:7px 12px; font-size:12px;">
                            View
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align:center;">Няма import-и.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if(method_exists($latestImports, 'links'))
    <div style="margin-top:16px;">
        {{ $latestImports->links() }}
    </div>
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.querySelectorAll('.import-job-row');

    function renderStatusBadge(status) {
        if (status === 'completed') {
            return '<span style="padding:4px 10px; border-radius:999px; background:#dcfce7; color:#166534; font-size:12px; font-weight:700;">completed</span>';
        }

        if (status === 'failed') {
            return '<span style="padding:4px 10px; border-radius:999px; background:#fee2e2; color:#991b1b; font-size:12px; font-weight:700;">failed</span>';
        }

        if (status === 'processing') {
            return '<span style="padding:4px 10px; border-radius:999px; background:#dbeafe; color:#1d4ed8; font-size:12px; font-weight:700;">processing</span>';
        }

        if (status === 'pending') {
            return '<span style="padding:4px 10px; border-radius:999px; background:#f3f4f6; color:#374151; font-size:12px; font-weight:700;">pending</span>';
        }

        return `<span style="padding:4px 10px; border-radius:999px; background:#f3f4f6; color:#374151; font-size:12px; font-weight:700;">${status}</span>`;
    }

    const refreshJob = async (row) => {
        const jobId = row.dataset.jobId;

        try {
            const response = await fetch(`/products/import/${jobId}/status`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                return null;
            }

            const data = await response.json();

            row.querySelector('.job-status').innerHTML = renderStatusBadge(data.status);
            row.querySelector('.job-progress-bar').style.width = data.progress_percent + '%';
            row.querySelector('.job-progress-text').textContent =
                `${data.progress_percent}% (${data.processed_rows}/${data.total_rows})`;

            row.querySelector('.job-imported').textContent = data.imported_count;
            row.querySelector('.job-updated').textContent = data.updated_count;

            const errorsCell = row.querySelector('.job-errors');
            if (parseInt(data.error_count, 10) > 0) {
                errorsCell.innerHTML = `<span style="font-weight:700; color:#dc2626;">${data.error_count}</span>`;
            } else {
                errorsCell.textContent = data.error_count;
            }

            row.querySelector('.job-started').textContent = data.started_at ?? '';
            row.querySelector('.job-finished').textContent = data.finished_at ?? '';

            const lastErrorBox = row.querySelector('.job-last-error');
            if (data.last_error) {
                lastErrorBox.style.display = 'block';
                lastErrorBox.textContent = data.last_error;
            } else {
                lastErrorBox.style.display = 'none';
                lastErrorBox.textContent = '';
            }

            return data.status;
        } catch (e) {
            return null;
        }
    };

    setInterval(async () => {
        for (const row of rows) {
            const statusText = row.querySelector('.job-status').textContent.trim().toLowerCase();

            if (statusText === 'pending' || statusText === 'processing') {
                await refreshJob(row);
            }
        }
    }, 3000);
});
</script>

@endsection