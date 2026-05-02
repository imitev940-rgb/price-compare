@extends('layouts.app')

@section('content')
<div style="padding: 24px; max-width: 1600px; margin: 0 auto;">

    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; flex-wrap:wrap; gap:16px;">
        <div>
            <h1 style="font-size:28px; font-weight:700; color:#111827; margin:0 0 6px;">Price Control</h1>
            <p style="font-size:14px; color:#6b7280; margin:0;">Управление на цени и отстъпки</p>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('priceControlHistory') }}" style="padding:10px 16px; background:#f3f4f6; color:#374151; text-decoration:none; border-radius:8px; font-weight:600; font-size:14px;">История на промените</a>
            <button id="saveAllBtn" disabled style="padding:10px 18px; background:#185fa5; color:white; border:none; border-radius:8px; font-weight:600; font-size:14px; cursor:pointer; opacity:0.5;">Save All (<span id="changesCount">0</span>)</button>
        </div>
    </div>

    <form method="GET" style="background:white; padding:16px; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.08); margin-bottom:20px; display:flex; gap:12px; flex-wrap:wrap;">
        <input type="text" name="search" value="{{ $search }}" placeholder="Търси по продукт, SKU или марка..." style="flex:1; min-width:250px; padding:10px 14px; border:1px solid #d1d5db; border-radius:8px; font-size:14px;">
        <select name="per_page" style="padding:10px 14px; border:1px solid #d1d5db; border-radius:8px; font-size:14px;">
            <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25 на страница</option>
            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 на страница</option>
            <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 на страница</option>
        </select>
        <button type="submit" style="padding:10px 18px; background:#185fa5; color:white; border:none; border-radius:8px; font-weight:600; font-size:14px; cursor:pointer;">Търси</button>
    </form>

    <div style="background:white; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.08); overflow:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:13px;">
            <thead>
                <tr style="background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                    <th style="padding:12px; text-align:left; font-weight:600; color:#374151;">Продукт</th>
                    <th style="padding:12px; text-align:right; font-weight:600; color:#374151; width:80px;">ПЦД</th>
                    <th style="padding:12px; text-align:right; font-weight:600; color:#374151; width:80px;">Our Price</th>
                    <th style="padding:12px; text-align:right; font-weight:600; color:#374151; width:90px;">% отст.</th>
                    <th style="padding:12px; text-align:right; font-weight:600; color:#374151; width:100px;">ДЦ</th>
                    <th style="padding:12px; text-align:right; font-weight:600; color:#374151; width:90px;">ННЦ</th>
                    <th style="padding:12px; text-align:right; font-weight:600; color:#374151; width:110px;">Нова цена</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                <tr data-product-id="{{ $product->id }}" data-pcd="{{ $product->pcd_price ?? '' }}" style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:10px 12px;">
                        <div style="font-weight:500; color:#111827;">{{ $product->name }}</div>
                        <div style="font-size:11px; color:#9ca3af; margin-top:2px;">SKU: {{ $product->sku ?? '—' }}</div>
                    </td>
                    <td style="padding:10px 12px; text-align:right; color:#6b7280;">{{ $product->pcd_price !== null ? number_format($product->pcd_price, 2) : '—' }}</td>
                    <td style="padding:10px 12px; text-align:right; font-weight:500;">{{ $product->our_price !== null ? number_format($product->our_price, 2) : '—' }}</td>
                    <td style="padding:10px 12px;">
                        <input type="number" step="0.01" min="0" max="100" class="pc-input pc-discount" value="{{ $product->discount_percent }}" placeholder="—" style="width:100%; padding:6px 8px; border:1px solid #d1d5db; border-radius:6px; text-align:right; font-size:13px;">
                    </td>
                    <td style="padding:10px 12px;">
                        <input type="number" step="0.01" min="0" class="pc-input pc-delivery" value="{{ $product->delivery_price }}" placeholder="—" style="width:100%; padding:6px 8px; border:1px solid #d1d5db; border-radius:6px; text-align:right; font-size:13px;">
                    </td>
                    <td style="padding:10px 12px; text-align:right; color:#185fa5; font-weight:500;">
                        @if($product->lowest_price)
                            {{ number_format($product->lowest_price, 2) }}
                            <div style="font-size:10px; color:#9ca3af; margin-top:2px;">{{ $product->lowest_store }}</div>
                        @else
                            —
                        @endif
                    </td>
                    <td style="padding:10px 12px;">
                        <input type="number" step="0.01" min="0" class="pc-input pc-newprice" value="{{ $product->new_price }}" placeholder="—" style="width:100%; padding:6px 8px; border:1px solid #d1d5db; border-radius:6px; text-align:right; font-size:13px; font-weight:600;">
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="margin-top:20px;">
        {{ $products->links() }}
    </div>
</div>

<script>
(function() {
    'use strict';
    var changes = {};
    var saveBtn = document.getElementById('saveAllBtn');
    var countSpan = document.getElementById('changesCount');

    function updateBtn() {
        var count = Object.keys(changes).length;
        countSpan.textContent = count;
        if (count > 0) {
            saveBtn.disabled = false;
            saveBtn.style.opacity = '1';
        } else {
            saveBtn.disabled = true;
            saveBtn.style.opacity = '0.5';
        }
    }

    document.querySelectorAll('tr[data-product-id]').forEach(function(row) {
        var productId = row.getAttribute('data-product-id');
        var pcdValue = parseFloat(row.getAttribute('data-pcd'));
        var discountInput = row.querySelector('.pc-discount');
        var deliveryInput = row.querySelector('.pc-delivery');
        var newPriceInput = row.querySelector('.pc-newprice');

        discountInput.addEventListener('input', function() {
            var pct = parseFloat(this.value);
            if (!isNaN(pct) && !isNaN(pcdValue)) {
                deliveryInput.value = (pcdValue * (1 - pct / 100)).toFixed(2);
            }
            trackChange(productId, row);
        });

        deliveryInput.addEventListener('input', function() {
            var dc = parseFloat(this.value);
            if (!isNaN(dc) && !isNaN(pcdValue) && pcdValue > 0) {
                discountInput.value = ((1 - dc / pcdValue) * 100).toFixed(2);
            }
            trackChange(productId, row);
        });

        newPriceInput.addEventListener('input', function() {
            trackChange(productId, row);
        });
    });

    function trackChange(productId, row) {
        changes[productId] = {
            product_id: parseInt(productId),
            discount_percent: parseFloat(row.querySelector('.pc-discount').value) || null,
            delivery_price: parseFloat(row.querySelector('.pc-delivery').value) || null,
            new_price: parseFloat(row.querySelector('.pc-newprice').value) || null,
        };
        updateBtn();
    }

    saveBtn.addEventListener('click', function() {
        if (saveBtn.disabled) return;
        var count = Object.keys(changes).length;
        if (!confirm('Сигурен ли сте, че искате да запазите ' + count + ' промени?')) return;

        saveBtn.disabled = true;
        saveBtn.textContent = 'Запазване...';

        fetch('{{ route("priceControlSave") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ changes: Object.values(changes) }),
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                alert('Запазени ' + data.count + ' промени');
                window.location.href = '{{ route("priceControlHistory") }}';
            } else {
                alert('Грешка: ' + (data.error || 'unknown'));
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save All (' + count + ')';
            }
        })
        .catch(function(e) {
            alert('Грешка: ' + e.message);
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save All (' + count + ')';
        });
    });
})();
</script>
@endsection
