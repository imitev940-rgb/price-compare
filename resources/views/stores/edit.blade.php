@extends('layouts.app')

@section('content')

<div class="cmp-page-head">
    <div>
        <h1 class="cmp-title">Edit Store</h1>
    </div>
</div>

@if ($errors->any())
    <div class="alert-error">
        <ul>
            @foreach ($errors->all() as $error)
                <li>• {{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="cmp-form-card">
    <form action="{{ route('stores.update', $store) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="cmp-form-group">
            <label>Name</label>
            <input
                type="text"
                name="name"
                value="{{ old('name', $store->name) }}"
                required
            >
        </div>

        <div class="cmp-form-group">
            <label>URL</label>
            <input
                type="text"
                name="base_url"
                value="{{ old('base_url', $store->base_url) }}"
                placeholder="https://example.com"
            >
        </div>

        <div class="cmp-form-actions">
            <button type="submit" class="btn">Save Changes</button>
            <a href="{{ route('stores.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@endsection