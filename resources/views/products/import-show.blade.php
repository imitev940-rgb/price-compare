@extends('layouts.app')

@section('content')

<div class="cmp-page-head cmp-page-head-modern">
    <div>
        <h1 class="cmp-title">Import #{{ $importJob->id }}</h1>
        <p class="cmp-subtitle">{{ $importJob->original_name }}</p>
    </div>
</div>

<a href="{{ route('products.import.create') }}" class="btn" style="margin-bottom:15px;">
    ← Back to imports
</a>

<div class="cmp-cards-grid" style="margin-bottom:20px;">

    <div class="cmp-stat-card">
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Status</div>
            <div class="cmp-stat-value">{{ $importJob->status }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Progress</div>
            <div class="cmp-stat-value">
                {{ $importJob->progress_percent }}%
            </div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Imported</div>
            <div class="cmp-stat-value">{{ $importJob->imported_count }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Updated</div>
            <div class="cmp-stat-value">{{ $importJob->updated_count }}</div>
        </div>
    </div>

    <div class="cmp-stat-card">
        <div class="cmp-stat-content">
            <div class="cmp-stat-label">Errors</div>
            <div class="cmp-stat-value" style="color:#dc2626;">
                {{ $importJob->error_count }}
            </div>
        </div>
    </div>

</div>

{{-- PROGRESS BAR --}}
<div style="background:#e5e7eb;border-radius:999px;overflow:hidden;height:12px;margin-bottom:20px;">
    <div id="progress-bar"
         style="height:12px;width:{{ $importJob->progress_percent }}%;background:#2563eb;transition:width .3s;">
    </div>
</div>

<div id="progress-text" style="margin-bottom:20px;">
    {{ $importJob->processed_rows }} / {{ $importJob->total_rows }}
</div>

{{-- LAST ERROR --}}
@if($importJob->last_error)
    <div style="background:#fee2e2;color:#991b1b;padding:10px;border-radius:8px;margin-bottom:20px;">
        {{ $importJob->last_error }}
    </div>
@endif

{{-- ERRORS TABLE --}}
@if($importJob->errors->count())
<div class="cmp-table-wrap">
    <h3 style="margin-bottom:10px;">Грешки</h3>

    <table class="cmp-table">
        <thead>
            <tr>
                <th>Row</th>
                <th>Error</th>
                <th>Data</th>
            </tr>
        </thead>
        <tbody>
            @foreach($importJob->errors as $error)
                <tr>
                    <td>#{{ $error->row_number }}</td>
                    <td style="color:#dc2626; font-weight:600;">
                        {{ $error->error_message }}
                    </td>
                    <td style="font-size:12px; max-width:400px; word-break:break-all;">
                        {{ $error->row_data }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<script>
    const jobId = {{ $importJob->id }};

    async function refresh() {
        try {
            const res = await fetch(`/products/import/${jobId}/status`);
            const data = await res.json();

            document.getElementById('progress-bar').style.width = data.progress_percent + '%';
            document.getElementById('progress-text').textContent =
                data.processed_rows + ' / ' + data.total_rows;

        } catch (e) {}
    }

    setInterval(refresh, 3000);
</script>

@endsection