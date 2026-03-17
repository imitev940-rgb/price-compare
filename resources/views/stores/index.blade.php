@extends('layouts.app')

@section('content')

@if(session('success'))
    <div class="alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="cmp-page-head">
    <div>
        <h1 class="cmp-title">Stores</h1>
        <p class="cmp-subtitle">Manage competitor stores.</p>
    </div>

    <a href="{{ route('stores.create') }}" class="btn">
        Add Store
    </a>
</div>

<div class="cmp-table-wrap">

<table class="cmp-table">

<thead>
<tr>
<th>Name</th>
<th>URL</th>
<th style="width:220px;">Actions</th>
</tr>
</thead>

<tbody>

@forelse($stores as $store)

<tr>

<td>
{{ $store->name }}
</td>

<td style="max-width:420px; word-break:break-word;">

@if($store->base_url)

<a href="{{ $store->base_url }}" target="_blank">
{{ $store->base_url }}
</a>

@else
—
@endif

</td>

<td>

<div style="display:flex; gap:10px;">

<a href="{{ route('stores.edit',$store) }}" class="btn">
Edit
</a>

<form
action="{{ route('stores.destroy',$store) }}"
method="POST"
onsubmit="return confirm('Сигурен ли си, че искаш да изтриеш този store?');"
>

@csrf
@method('DELETE')

<button type="submit" class="btn">
Delete
</button>

</form>

</div>

</td>

</tr>

@empty

<tr>
<td colspan="3">
Няма добавени stores.
</td>
</tr>

@endforelse

</tbody>

</table>

</div>

<div style="margin-top:20px;">
{{ $stores->links() }}
</div>

@endsection