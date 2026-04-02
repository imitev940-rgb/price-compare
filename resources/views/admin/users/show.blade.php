@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:900px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:20px;">
        <div>
            <h1 style="margin:0;font-size:28px;font-weight:700;">User Details</h1>
            <p style="margin:6px 0 0;color:#6b7280;">View account information.</p>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            @if(!auth()->user()->isAdmin() || !$user->isSuperAdmin())
                <a href="{{ route('admin.users.edit', $user) }}"
                   style="padding:10px 16px;border-radius:12px;background:#dbeafe;color:#1d4ed8;text-decoration:none;font-weight:600;">
                    Edit User
                </a>
            @endif

            <a href="{{ route('admin.users.index') }}"
               style="padding:10px 16px;border-radius:12px;background:#f3f4f6;color:#111827;text-decoration:none;font-weight:600;">
                ← Back
            </a>
        </div>
    </div>

    @if(session('success'))
        <div style="margin-bottom:16px;padding:12px 14px;border-radius:12px;background:#dcfce7;color:#166534;font-weight:600;">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div style="margin-bottom:16px;padding:12px 14px;border-radius:12px;background:#fee2e2;color:#991b1b;">
            <ul style="margin:0 0 0 18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:22px;box-shadow:0 10px 30px rgba(0,0,0,0.04);">
        <div style="display:grid;grid-template-columns:repeat(2, minmax(0,1fr));gap:18px;">
            <div>
                <div style="font-size:13px;color:#6b7280;margin-bottom:6px;">ID</div>
                <div style="font-size:16px;font-weight:700;">{{ $user->id }}</div>
            </div>

            <div>
                <div style="font-size:13px;color:#6b7280;margin-bottom:6px;">Name</div>
                <div style="font-size:16px;font-weight:700;">{{ $user->name }}</div>
            </div>

            <div>
                <div style="font-size:13px;color:#6b7280;margin-bottom:6px;">Email</div>
                <div style="font-size:16px;font-weight:700;">{{ $user->email }}</div>
            </div>

            <div>
                <div style="font-size:13px;color:#6b7280;margin-bottom:6px;">Role</div>
                <div style="font-size:16px;font-weight:700;">
                    {{ \App\Models\User::roles()[$user->role] ?? $user->role }}
                </div>
            </div>

            <div>
                <div style="font-size:13px;color:#6b7280;margin-bottom:6px;">Status</div>
                <div style="font-size:16px;font-weight:700;">
                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                </div>
            </div>

            <div>
                <div style="font-size:13px;color:#6b7280;margin-bottom:6px;">Last Login</div>
                <div style="font-size:16px;font-weight:700;">
                    {{ $user->last_login_at ? $user->last_login_at->format('d.m.Y H:i') : '—' }}
                </div>
            </div>

            <div>
                <div style="font-size:13px;color:#6b7280;margin-bottom:6px;">Created At</div>
                <div style="font-size:16px;font-weight:700;">
                    {{ $user->created_at ? $user->created_at->format('d.m.Y H:i') : '—' }}
                </div>
            </div>

            <div>
                <div style="font-size:13px;color:#6b7280;margin-bottom:6px;">Updated At</div>
                <div style="font-size:16px;font-weight:700;">
                    {{ $user->updated_at ? $user->updated_at->format('d.m.Y H:i') : '—' }}
                </div>
            </div>
        </div>

        @if(auth()->id() !== $user->id && auth()->user()->isSuperAdmin())
            <div style="margin-top:24px;padding-top:18px;border-top:1px solid #e5e7eb;">
                <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');">
                    @csrf
                    @method('DELETE')

                    <button type="submit"
                            style="padding:12px 18px;border:none;border-radius:12px;background:#fee2e2;color:#b91c1c;font-weight:700;cursor:pointer;">
                        Delete User
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection