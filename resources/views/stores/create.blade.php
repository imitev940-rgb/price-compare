@extends('layouts.app')

@section('content')

<div class="cmp-page-head cmp-page-head-modern">
    <div>
        <h1 class="cmp-title">Add Store</h1>
        <p class="cmp-subtitle">Добави нов конкурентен магазин за следене на цени.</p>
    </div>

    <div class="cmp-page-head-actions">
        <a href="{{ route('stores.index') }}" class="btn">Cancel</a>
    </div>
</div>

<div style="height:1px; background:rgba(0,0,0,0.05); margin:20px 0;"></div>

@if ($errors->any())
    <div class="alert-success" style="background:#fff1f1;color:#b42318;border-color:#f3c1c1; margin-bottom:16px;">
        <ul style="margin:0; padding-left:18px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="panel-card" style="max-width: 700px;">
    <form action="{{ route('stores.store') }}" method="POST">
        @csrf

        <div class="mb-4">
            <label>Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required>
            <small style="display:block; margin-top:6px; color:#667085;">
                Пример: Technopolis, Zora, Pazaruvaj
            </small>
        </div>

        <div class="mb-4">
            <label>URL</label>
            <input type="url" name="url" value="{{ old('url') }}" placeholder="https://example.com">
            <small style="display:block; margin-top:6px; color:#667085;">
                Основен URL на магазина (не е задължителен)
            </small>
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px;">
            <button type="submit" class="btn">Create Store</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const nameInput = document.querySelector('input[name="name"]');
    if (nameInput) {
        nameInput.focus();
    }
});
</script>

@endsection