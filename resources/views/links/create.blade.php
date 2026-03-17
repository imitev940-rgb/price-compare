@extends('layouts.app')

@section('content')

<div class="cmp-page-head">
    <h1 class="cmp-title">Add Competitor Link</h1>
</div>

@if ($errors->any())
<div class="alert-error">
<ul>
@foreach ($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul>
</div>
@endif

<div class="cmp-form-card">

<form action="{{ route('links.store') }}" method="POST">
@csrf

<div class="cmp-form-group">
<label>Product</label>
<select name="product_id" required>
<option value="">Select product</option>

@foreach($products as $product)
<option value="{{ $product->id }}">
{{ $product->name }}
</option>
@endforeach

</select>
</div>


<div class="cmp-form-group">
<label>Store</label>
<select name="store_id" required>

<option value="">Select store</option>

@foreach($stores as $store)
<option value="{{ $store->id }}">
{{ $store->name }}
</option>
@endforeach

</select>
</div>


<div class="cmp-form-group">
<label>Product URL</label>
<input
type="text"
name="product_url"
placeholder="https://example.com/product"
required
>
</div>


<div class="cmp-form-group">
<label>Last Price</label>
<input
type="text"
name="last_price"
placeholder="199.99"
>
</div>


<div class="cmp-form-actions">
<button type="submit" class="btn">Save</button>
<a href="{{ route('links.index') }}" class="btn btn-secondary">Cancel</a>
</div>

</form>

</div>

@endsection