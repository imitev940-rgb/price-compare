@extends('layouts.app')

@section('content')

<h1>Add Store</h1>

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
    <form action="{{ route('stores.store') }}" method="POST">
        @csrf

        <div class="mb-4">
            <label>Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required>
        </div>

        <div class="mb-4">
            <label>URL</label>
            <input type="url" name="url" value="{{ old('url') }}">
        </div>

        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button type="submit" class="btn">Create Store</button>
            <a href="{{ route('stores.index') }}" class="btn">Cancel</a>
        </div>
    </form>
</div>

@endsection