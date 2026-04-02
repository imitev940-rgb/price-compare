@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:900px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:20px;">
        <div>
            <h1 style="margin:0;font-size:28px;font-weight:700;">My Profile</h1>
            <p style="margin:6px 0 0;color:#6b7280;">Update your account details and password.</p>
        </div>
    </div>

    @if(session('success'))
        <div style="margin-bottom:16px;padding:12px 14px;border-radius:12px;background:#dcfce7;color:#166534;font-weight:600;">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div style="margin-bottom:16px;padding:12px 14px;border-radius:12px;background:#fee2e2;color:#991b1b;">
            <strong>Please fix the errors below:</strong>
            <ul style="margin:8px 0 0 18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:22px;box-shadow:0 10px 30px rgba(0,0,0,0.04);">
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PUT')

            <div style="display:grid;grid-template-columns:repeat(2, minmax(0,1fr));gap:16px;">
                <div>
                    <label style="display:block;margin-bottom:8px;font-weight:700;">Name</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}"
                           style="width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:12px;">
                </div>

                <div>
                    <label style="display:block;margin-bottom:8px;font-weight:700;">Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}"
                           style="width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:12px;">
                </div>

                <div>
                    <label style="display:block;margin-bottom:8px;font-weight:700;">Current Password</label>
                    <input type="password" name="current_password"
                           style="width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:12px;">
                    <small style="display:block;margin-top:6px;color:#6b7280;">Required only if you change password.</small>
                </div>

                <div>
                    <label style="display:block;margin-bottom:8px;font-weight:700;">New Password</label>
                    <input type="password" name="new_password"
                           style="width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:12px;">
                </div>

                <div>
                    <label style="display:block;margin-bottom:8px;font-weight:700;">Confirm New Password</label>
                    <input type="password" name="new_password_confirmation"
                           style="width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:12px;">
                </div>

                <div>
                    <label style="display:block;margin-bottom:8px;font-weight:700;">Role</label>
                    <input type="text" value="{{ \App\Models\User::roles()[$user->role] ?? $user->role }}"
                           disabled
                           style="width:100%;padding:12px 14px;border:1px solid #e5e7eb;border-radius:12px;background:#f9fafb;color:#6b7280;">
                </div>
            </div>

            <div style="margin-top:22px;display:flex;gap:12px;flex-wrap:wrap;">
                <button type="submit"
                        style="padding:12px 18px;border:none;border-radius:12px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer;">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection