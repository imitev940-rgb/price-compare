@extends('layouts.app')

@section('content')

<h1>Add Product</h1>

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
    <form action="{{ route('products.store') }}" method="POST">
        @csrf

        <div class="mb-4">
            <label>Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required>
        </div>

        <div class="mb-4">
            <label>SKU</label>
            <input type="text" name="sku" value="{{ old('sku') }}">
        </div>

        <div class="mb-4">
            <label>EAN</label>
            <input type="text" name="ean" value="{{ old('ean') }}">
        </div>

        <div class="mb-4">
            <label>Brand</label>
            <input type="text" name="brand" value="{{ old('brand') }}">
        </div>

        <div class="mb-4">
            <label>Our Price</label>
            <input type="number" step="0.01" name="our_price" value="{{ old('our_price') }}" required>
        </div>

        <div class="mb-4">
            <label>Status</label>
            <select name="is_active" required>
                <option value="1" selected>Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button type="submit" class="btn">Create Product</button>
            <a href="{{ route('products.index') }}" class="btn">Cancel</a>
        </div>
    </form>
</div>

@endsection