@extends('layouts.app')

@section('content')

<div class="cmp-page-head cmp-page-head-modern">
    <div>
        <h1 class="cmp-title">{{ __('messages.edit_product') }}</h1>
        <p class="cmp-subtitle">Редактирай продукта и обнови цената автоматично от Product URL.</p>
    </div>

    <div class="cmp-page-head-actions">
        <a href="{{ route('products.index') }}" class="btn">{{ __('messages.cancel') }}</a>
    </div>
</div>

@if ($errors->any())
    <div class="alert-success" style="background:#fff1f1;color:#b42318;border-color:#f3c1c1; margin-bottom:16px;">
        <ul style="margin:0; padding-left:18px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(session('error'))
    <div class="alert-success" style="background:#fff1f1;color:#b42318;border-color:#f3c1c1; margin-bottom:16px;">
        {{ session('error') }}
    </div>
@endif

@if(session('success'))
    <div class="alert-success" style="background:#ecfdf3;color:#067647;border-color:#a6f4c5; margin-bottom:16px;">
        {{ session('success') }}
    </div>
@endif

<div class="panel-card" style="max-width: 980px;">
    <form action="{{ route('products.update', $product) }}" method="POST" class="loader-form">
        @csrf
        @method('PUT')

        <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:20px;">
            <div class="mb-4">
                <label>{{ __('messages.name') }}</label>
                <input type="text" name="name" value="{{ old('name', $product->name) }}" required>
            </div>

            <div class="mb-4">
                <label>{{ __('messages.brand') }}</label>
                <input type="text" name="brand" value="{{ old('brand', $product->brand) }}">
            </div>

            <div class="mb-4">
                <label>{{ __('messages.sku') }}</label>
                <input type="text" name="sku" value="{{ old('sku', $product->sku) }}">
            </div>

            <div class="mb-4">
                <label>{{ __('messages.ean') }}</label>
                <input type="text" name="ean" value="{{ old('ean', $product->ean) }}">
            </div>
        </div>

        <div class="mb-4">
            <label>{{ __('messages.product_url') }}</label>
            <input
                type="url"
                id="product_url"
                name="product_url"
                value="{{ old('product_url', $product->product_url) }}"
            >
            <small style="display:block; margin-top:6px; color:#667085;">
                {{ __('messages.product_url_edit_help') }}
            </small>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 260px; gap:20px; align-items:start;">
            <div class="mb-4">
                <label>{{ __('messages.our_price') }}</label>
                <input
                    type="text"
                    id="our_price"
                    name="our_price"
                    value="{{ old('our_price', $product->our_price !== null ? number_format((float)$product->our_price, 2, '.', '') : '') }}"
                    readonly
                    inputmode="decimal"
                >
                <small id="price_status" style="display:block; margin-top:6px; color:#667085;">
                    {{ __('messages.price_field_auto_edit') }}
                </small>
            </div>

            <div class="mb-4">
                <label>{{ __('messages.status') }}</label>
                <select name="is_active" required>
                    <option value="1" {{ old('is_active', $product->is_active) == 1 ? 'selected' : '' }}>{{ __('messages.active') }}</option>
                    <option value="0" {{ old('is_active', $product->is_active) == 0 ? 'selected' : '' }}>{{ __('messages.inactive') }}</option>
                </select>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 260px; gap:20px; align-items:start;">
            <div class="mb-4">
                <label>Scan Priority</label>
                <select name="scan_priority">
                    <option value="normal" {{ old('scan_priority', $product->scan_priority ?? 'normal') === 'normal' ? 'selected' : '' }}>
                        Normal Product
                    </option>
                    <option value="top" {{ old('scan_priority', $product->scan_priority ?? 'normal') === 'top' ? 'selected' : '' }}>
                        Top Product
                    </option>
                </select>
                <small style="display:block; margin-top:6px; color:#667085;">
                    Top Product = по-често сканиране. Normal Product = стандартни правила.
                </small>
            </div>
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:8px;">
            <button type="submit" class="btn" id="saveProductBtn">{{ __('messages.save_changes') }}</button>
            <a href="{{ route('products.index') }}" class="btn">{{ __('messages.cancel') }}</a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const urlInput = document.getElementById('product_url');
    const priceInput = document.getElementById('our_price');
    const priceStatus = document.getElementById('price_status');
    const saveBtn = document.getElementById('saveProductBtn');
    let timeout = null;

    function disableSave() {
        if (!saveBtn) return;
        saveBtn.disabled = true;
        saveBtn.style.opacity = '0.6';
        saveBtn.style.cursor = 'not-allowed';
    }

    function enableSave() {
        if (!saveBtn) return;
        saveBtn.disabled = false;
        saveBtn.style.opacity = '1';
        saveBtn.style.cursor = 'pointer';
    }

    async function fetchPrice() {
        const url = urlInput.value.trim();

        if (!url) {
            if (priceInput.value.trim()) {
                priceStatus.textContent = @json(__('messages.price_field_auto_edit'));
                enableSave();
            } else {
                priceInput.value = '';
                priceStatus.textContent = @json(__('messages.price_field_auto_edit'));
                disableSave();
            }
            return;
        }

        priceStatus.textContent = @json(__('messages.loading_price'));
        disableSave();

        try {
            const response = await fetch('{{ route('products.fetch-price') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    product_url: url
                })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                priceInput.value = data.price;
                priceStatus.textContent = @json(__('messages.price_loaded'));
                enableSave();
            } else {
                priceInput.value = '';
                priceStatus.textContent = data.message || @json(__('messages.price_read_failed'));
                disableSave();
            }
        } catch (error) {
            priceInput.value = '';
            priceStatus.textContent = @json(__('messages.price_loading_error'));
            disableSave();
        }
    }

    function delayedFetch() {
        clearTimeout(timeout);
        timeout = setTimeout(fetchPrice, 600);
    }

    if (urlInput.value.trim()) {
        fetchPrice();
    } else if (priceInput.value.trim()) {
        enableSave();
        priceStatus.textContent = @json(__('messages.price_field_auto_edit'));
    } else {
        disableSave();
    }

    urlInput.addEventListener('paste', function () {
        setTimeout(fetchPrice, 200);
    });

    urlInput.addEventListener('blur', fetchPrice);
    urlInput.addEventListener('input', delayedFetch);
});
</script>

@endsection