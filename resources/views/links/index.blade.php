@extends('layouts.app')

@section('content')

@if(session('success'))
    <div class="alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="cmp-page-head">
    <div>
        <h1 class="cmp-title">Competitor Links</h1>
        <p class="cmp-subtitle">Manage product URLs from competitor stores.</p>
    </div>

    <a href="{{ route('links.create') }}" class="btn">Add Link</a>
</div>

<div class="cmp-table-wrap">
    <table class="cmp-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Store</th>
                <th>URL</th>
                <th>Last Price</th>
                <th style="width:260px;">Actions</th>
            </tr>
        </thead>

        <tbody>

            @forelse($links as $link)

            <tr>

                <td>
                    {{ $link->product->name ?? '—' }}
                </td>

                <td>
                    {{ $link->store->name ?? '—' }}
                </td>

                <td style="max-width:420px; word-break:break-word;">

                    @if($link->product_url)

                        <a href="{{ $link->product_url }}" target="_blank">
                            {{ $link->product_url }}
                        </a>

                    @else
                        —
                    @endif

                </td>

                <td>

                    {{ $link->last_price !== null ? number_format($link->last_price,2).' €' : '—' }}

                </td>

                <td>

                    <div style="display:flex; gap:10px; flex-wrap:wrap;">

                        <a href="{{ route('links.edit',$link) }}" class="btn">
                            Edit
                        </a>

                        <form
                            action="{{ route('links.destroy',$link) }}"
                            method="POST"
                            onsubmit="return confirm('Сигурен ли си, че искаш да изтриеш този competitor link?');"
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
                <td colspan="5">
                    Няма добавени competitor links.
                </td>
            </tr>

            @endforelse

        </tbody>

    </table>
</div>

<div style="margin-top:20px;">
    {{ $links->links() }}
</div>

@endsection