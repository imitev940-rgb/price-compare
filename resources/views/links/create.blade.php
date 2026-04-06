@extends('layouts.app')

@section('content')

<div class="cmp-page-head cmp-page-head-modern">
    <div>
        <h1 class="cmp-title">Add Competitor Link</h1>
        <p class="cmp-subtitle">Добави URL на конкурентен продукт към конкретен магазин.</p>
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
    <form action="{{ route('links.store') }}" method="POST">
        @csrf

        <div style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:20px;">
            <div class="mb-4">
                <label>Product</label>

                {{-- Search input --}}
                <div style="position:relative;">
                    <input
                        type="text"
                        id="productSearch"
                        placeholder="Търси по продукт..."
                        autocomplete="off"
                        readonly
                        style="cursor:pointer;"
                    >
                    <input type="hidden" name="product_id" id="productId" value="{{ old('product_id') }}" required>

                    <div id="productDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; z-index:999;
                        background:#fff; border:1px solid #dbe3ef; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.10);
                        max-height:280px; overflow-y:auto; margin-top:4px;">

                        <div style="padding:8px;">
                            <input type="text" id="productSearchInner" placeholder="Търси..."
                                autocomplete="off"
                                style="width:100%; box-sizing:border-box; margin:0;">
                        </div>

                        <div id="productOptions">
                            <div data-value="" style="padding:8px 12px; color:#94a3b8; font-size:13px;">— Избери продукт —</div>
                            @foreach($products as $product)
                                <div class="product-opt" data-value="{{ $product->id }}"
                                    data-name="{{ strtolower($product->name) }} {{ strtolower($product->sku ?? '') }}"
                                    style="padding:8px 12px; cursor:pointer; font-size:13px; border-radius:6px;">
                                    {{ $product->name }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <small style="display:block; margin-top:6px; color:#667085;">
                    Избери продукта, към който ще вържеш конкурентния линк.
                </small>
            </div>

            <div class="mb-4">
                <label>Store</label>
                <select name="store_id" required>
                    <option value="">Select store</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" {{ old('store_id') == $store->id ? 'selected' : '' }}>
                            {{ $store->name }}
                        </option>
                    @endforeach
                </select>
                <small style="display:block; margin-top:6px; color:#667085;">
                    Избери магазина, към който принадлежи URL адресът.
                </small>
            </div>
        </div>

        <div class="mb-4">
            <label>Product URL</label>
            <input
                type="text"
                name="product_url"
                value="{{ old('product_url') }}"
                placeholder="https://example.com/product"
                required
            >
            <small style="display:block; margin-top:6px; color:#667085;">
                Пълен URL към страницата на конкурентния продукт.
            </small>
        </div>

        <div style="display:grid; grid-template-columns: 260px; gap:20px;">
            <div class="mb-4">
                <label>Last Price</label>
                <input
                    type="text"
                    name="last_price"
                    value="{{ old('last_price') }}"
                    placeholder="199.99"
                >
                <small style="display:block; margin-top:6px; color:#667085;">
                    По желание — можеш да оставиш празно.
                </small>
            </div>
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px;">
            <button type="submit" class="btn">Save Link</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const trigger      = document.getElementById('productSearch');
    const dropdown     = document.getElementById('productDropdown');
    const searchInner  = document.getElementById('productSearchInner');
    const productId    = document.getElementById('productId');
    const allOpts      = Array.from(document.querySelectorAll('.product-opt'));

    // Open dropdown
    trigger.addEventListener('click', () => {
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        if (dropdown.style.display === 'block') {
            searchInner.focus();
        }
    });

    // Filter options
    searchInner.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        allOpts.forEach(opt => {
            const match = !q || (opt.dataset.name || '').includes(q);
            opt.style.display = match ? '' : 'none';
        });
    });

    // Select option
    allOpts.forEach(opt => {
        opt.addEventListener('click', () => {
            productId.value = opt.dataset.value;
            trigger.value   = opt.textContent.trim();
            dropdown.style.display = 'none';
            searchInner.value = '';
            allOpts.forEach(o => o.style.display = '');
        });

        opt.addEventListener('mouseenter', () => {
            opt.style.background = '#eff6ff';
        });
        opt.addEventListener('mouseleave', () => {
            opt.style.background = '';
        });
    });

    // Close on outside click
    document.addEventListener('click', (e) => {
        if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });

    // Prefill if old value
    const oldVal = productId.value;
    if (oldVal) {
        const found = allOpts.find(o => o.dataset.value == oldVal);
        if (found) trigger.value = found.textContent.trim();
    }
});
</script>

@endsection