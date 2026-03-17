@extends('layouts.app')

@section('content')

<h1>Edit Product</h1>

@if ($errors->any())
    <div class="alert-success" style="background:#fff1f1;color:#b42318;border-color:#f3c1c1;">
        <ul style="margin:0; padding-left:18px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="panel-card">
    <form action="{{ route('products.update', $product) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="mb-4">
            <label>Name</label>
            <input type="text" name="name" value="{{ old('name', $product->name) }}" required>
        </div>

        <div class="mb-4">
            <label>SKU</label>
            <input type="text" name="sku" value="{{ old('sku', $product->sku) }}">
        </div>

        <div class="mb-4">
            <label>EAN</label>
            <input type="text" name="ean" value="{{ old('ean', $product->ean) }}">
        </div>

        <div class="mb-4">
            <label>Brand</label>
            <input type="text" name="brand" value="{{ old('brand', $product->brand) }}">
        </div>

        <div class="mb-4">
            <label>Our Price</label>
            <input type="number" step="0.01" name="our_price" value="{{ old('our_price', $product->our_price) }}" required>
        </div>

        <div class="mb-4">
            <label>Status</label>
            <select name="is_active" required>
                <option value="1" {{ old('is_active', $product->is_active) == 1 ? 'selected' : '' }}>Active</option>
                <option value="0" {{ old('is_active', $product->is_active) == 0 ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button type="submit" class="btn">Save Changes</button>
            <a href="{{ route('products.index') }}" class="btn">Cancel</a>
        </div>
    </form>
</div>

@endsection