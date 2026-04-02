@extends('layouts.app')

@section('content')

<div class="cmp-page-head cmp-page-head-modern">
    <div>
        <h1 class="cmp-title">{{ __('messages.add_product') }}</h1>
        <p class="cmp-subtitle">Добави продукт и цената ще се зареди автоматично от Product URL.</p>
    </div>

    <div class="cmp-page-head-actions">
        <a href="{{ route('products.index') }}" class="btn">{{ __('messages.cancel') }}</a>
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

@if(session('error'))
    <div class="alert-success" style="background:#fff1f1;color:#b42318;border-color:#f3c1c1; margin-bottom:16px;">
        {{ session('error') }}
    </div>
@endif

@if(session('warning'))
    <div class="alert-success" style="background:#fff8e6;color:#9a6700;border-color:#f2d48a; margin-bottom:16px;">
        {{ session('warning') }}
    </div>
@endif

<div class="panel-card" style="max-width: 980px;">
    <form action="{{ route('products.store') }}" method="POST" class="loader-form">
        @csrf

        <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:20px;">
            <div class="mb-4">
                <label>{{ __('messages.name') }}</label>
                <input type="text" name="name" value="{{ old('name') }}" required>
            </div>

            <div class="mb-4">
                <label>{{ __('messages.brand') }}</label>
                <input type="text" name="brand" value="{{ old('brand') }}">
            </div>

            <div class="mb-4">
                <label>{{ __('messages.sku') }}</label>
                <input type="text" name="sku" value="{{ old('sku') }}">
            </div>

            <div class="mb-4">
                <label>{{ __('messages.ean') }}</label>
                <input type="text" name="ean" value="{{ old('ean') }}">
            </div>
        </div>

        <div class="mb-4">
            <label>{{ __('messages.product_url') }}</label>
            <input
                type="url"
                id="product_url"
                name="product_url"
                value="{{ old('product_url') }}"
                placeholder="https://example.com/product-page"
            >
            <small style="display:block; margin-top:6px; color:#667085;">
                {{ __('messages.product_url_help') }}
            </small>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 260px; gap:20px; align-items:start;">
            <div class="mb-4">
                <label>{{ __('messages.our_price') }}</label>
                <input
                    type="text"
                    id="our_price"
                    name="our_price"
                    value="{{ old('our_price') !== null && old('our_price') !== '' ? number_format((float) old('our_price'), 2, '.', '') : '' }}"
                    readonly
                    inputmode="decimal"
                >
                <small id="price_status" class="price-status" style="display:block; margin-top:6px;">
                    {{ __('messages.price_field_auto') }}
                </small>
            </div>

            <div class="mb-4">
                <label>{{ __('messages.status') }}</label>
                <select name="is_active" required>
                    <option value="1" {{ old('is_active', 1) == 1 ? 'selected' : '' }}>{{ __('messages.active') }}</option>
                    <option value="0" {{ old('is_active') == 0 ? 'selected' : '' }}>{{ __('messages.inactive') }}</option>
                </select>
            </div>
        </div>

        <div style="display:grid; grid-template-columns: 260px; gap:20px; align-items:start;">
            <div class="mb-4">
                <label>Scan Priority</label>
                <select name="scan_priority">
                    <option value="normal" {{ old('scan_priority', 'normal') === 'normal' ? 'selected' : '' }}>
                        Normal Product
                    </option>
                    <option value="top" {{ old('scan_priority', 'normal') === 'top' ? 'selected' : '' }}>
                        Top Product
                    </option>
                </select>
                <small style="display:block; margin-top:6px; color:#667085;">
                    Top Product = по-често сканиране. Normal Product = стандартни правила.
                </small>
            </div>
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:8px;">
            <button type="submit" class="btn" id="createProductBtn">Create & Start Tracking</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const urlInput = document.getElementById('product_url');
    const priceInput = document.getElementById('our_price');
    const priceStatus = document.getElementById('price_status');
    const submitBtn = document.getElementById('createProductBtn');
    const nameInput = document.querySelector('input[name="name"]');
    let timeout = null;

    if (nameInput) {
        nameInput.focus();
    }

    if (submitBtn && !priceInput.value) {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.6';
        submitBtn.style.cursor = 'not-allowed';
    }

    async function fetchPrice() {
        const url = urlInput.value.trim();

        if (!url) {
            priceInput.value = '';
            priceStatus.className = 'price-status';
            priceStatus.textContent = @json(__('messages.price_field_auto'));

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.6';
                submitBtn.style.cursor = 'not-allowed';
            }
            return;
        }

        priceStatus.className = 'price-status loading';
        priceStatus.textContent = 'Loading price...';

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
                priceStatus.className = 'price-status success';
                priceStatus.textContent = 'Price loaded • Ready to start tracking';

                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                }
            } else {
                priceInput.value = '';
                priceStatus.className = 'price-status error';
                priceStatus.textContent = data.message || 'Failed to read price';

                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.6';
                    submitBtn.style.cursor = 'not-allowed';
                }
            }
        } catch (error) {
            priceInput.value = '';
            priceStatus.className = 'price-status error';
            priceStatus.textContent = 'Price loading error';

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.6';
                submitBtn.style.cursor = 'not-allowed';
            }
        }
    }

    function delayedFetch() {
        clearTimeout(timeout);
        timeout = setTimeout(fetchPrice, 600);
    }

    urlInput.addEventListener('paste', function () {
        setTimeout(fetchPrice, 200);
    });

    urlInput.addEventListener('blur', fetchPrice);
    urlInput.addEventListener('input', delayedFetch);
});
</script>

@endsection