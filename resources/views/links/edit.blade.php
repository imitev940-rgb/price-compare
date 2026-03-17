@extends('layouts.app')

@section('content')

<div class="cmp-page-head">
<h1 class="cmp-title">Edit Competitor Link</h1>
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

<form action="{{ route('links.update',$link) }}" method="POST">
@csrf
@method('PUT')

<div class="cmp-form-group">
<label>Product</label>
<select name="product_id">

@foreach($products as $product)

<option value="{{ $product->id }}"
{{ $link->product_id == $product->id ? 'selected' : '' }}>
{{ $product->name }}
</option>

@endforeach

</select>
</div>


<div class="cmp-form-group">
<label>Store</label>

<select name="store_id">

@foreach($stores as $store)

<option value="{{ $store->id }}"
{{ $link->store_id == $store->id ? 'selected' : '' }}>
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
value="{{ $link->product_url }}"
required
>

</div>


<div class="cmp-form-group">
<label>Last Price</label>

<input
type="text"
name="last_price"
value="{{ $link->last_price }}"
>

</div>


<div class="cmp-form-actions">

<button type="submit" class="btn">Save Changes</button>

<a href="{{ route('links.index') }}" class="btn btn-secondary">Cancel</a>

</div>

</form>

</div>

@endsection