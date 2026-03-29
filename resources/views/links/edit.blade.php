@extends('layouts.app')

@section('content')

<div class="cmp-page-head cmp-page-head-modern">
    <div>
        <h1 class="cmp-title">Edit Competitor Link</h1>
        <p class="cmp-subtitle">Редактирай връзката към конкурентен продукт и магазина ѝ.</p>
    </div>

    <div class="cmp-page-head-actions">
        <a href="{{ route('links.index') }}" class="btn">Cancel</a>
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

<div class="panel-card" style="max-width: 900px;">
    <form action="{{ route('links.update', $link) }}" method="POST">
        @csrf
        @method('PUT')

        <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:20px;">
            <div class="mb-4">
                <label>Product</label>
                <select name="product_id" required>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}"
                            {{ old('product_id', $link->product_id) == $product->id ? 'selected' : '' }}>
                            {{ $product->name }}
                        </option>
                    @endforeach
                </select>
                <small style="display:block; margin-top:6px; color:#667085;">
                    Избери продукта, към който е вързан този линк.
                </small>
            </div>

            <div class="mb-4">
                <label>Store</label>
                <select name="store_id" required>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}"
                            {{ old('store_id', $link->store_id) == $store->id ? 'selected' : '' }}>
                            {{ $store->name }}
                        </option>
                    @endforeach
                </select>
                <small style="display:block; margin-top:6px; color:#667085;">
                    Избери магазина, към който принадлежи линкът.
                </small>
            </div>
        </div>

        <div class="mb-4">
            <label>Product URL</label>
            <input
                type="text"
                name="product_url"
                value="{{ old('product_url', $link->product_url) }}"
                required
            >
            <small style="display:block; margin-top:6px; color:#667085;">
                Пълен URL към конкурентната продуктова страница.
            </small>
        </div>

        <div style="display:grid; grid-template-columns: 260px; gap:20px;">
            <div class="mb-4">
                <label>Last Price</label>
                <input
                    type="text"
                    name="last_price"
                    value="{{ old('last_price', $link->last_price) }}"
                >
                <small style="display:block; margin-top:6px; color:#667085;">
                    По желание — можеш да оставиш празно.
                </small>
            </div>
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px;">
            <button type="submit" class="btn">Save Changes</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const firstSelect = document.querySelector('select[name="product_id"]');
    if (firstSelect) {
        firstSelect.focus();
    }
});
</script>

@endsection