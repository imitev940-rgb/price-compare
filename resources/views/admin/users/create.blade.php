@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:900px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:20px;">
        <div>
            <h1 style="margin:0;font-size:28px;font-weight:700;">Create User</h1>
            <p style="margin:6px 0 0;color:#6b7280;">Add a new user account.</p>
        </div>

        <a href="{{ route('admin.users.index') }}"
           style="padding:10px 16px;border-radius:12px;background:#f3f4f6;color:#111827;text-decoration:none;font-weight:600;">
            ← Back to Users
        </a>
    </div>

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
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf

            <div style="display:grid;grid-template-columns:repeat(2, minmax(0,1fr));gap:16px;">
                <div>
                    <label style="display:block;margin-bottom:8px;font-weight:700;">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}"
                           style="width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:12px;">
                </div>

                <div>
                    <label style="display:block;margin-bottom:8px;font-weight:700;">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           style="width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:12px;">
                </div>

                <div>
                    <label style="display:block;margin-bottom:8px;font-weight:700;">Password</label>
                    <input type="password" name="password"
                           style="width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:12px;">
                </div>

                <div>
                    <label style="display:block;margin-bottom:8px;font-weight:700;">Confirm Password</label>
                    <input type="password" name="password_confirmation"
                           style="width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:12px;">
                </div>

                <div>
                    <label style="display:block;margin-bottom:8px;font-weight:700;">Role</label>
                    <select name="role"
                            style="width:100%;padding:12px 14px;border:1px solid #d1d5db;border-radius:12px;">
                        @foreach($roles as $value => $label)
                            @if(auth()->user()->isAdmin() && $value === \App\Models\User::ROLE_SUPER_ADMIN)
                                @continue
                            @endif

                            <option value="{{ $value }}" @selected(old('role') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div style="display:flex;align-items:end;">
                    <label style="display:flex;align-items:center;gap:10px;font-weight:700;">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        Active account
                    </label>
                </div>
            </div>

            <div style="margin-top:22px;display:flex;gap:12px;flex-wrap:wrap;">
                <button type="submit"
                        style="padding:12px 18px;border:none;border-radius:12px;background:#2563eb;color:#fff;font-weight:700;cursor:pointer;">
                    Create User
                </button>

                <a href="{{ route('admin.users.index') }}"
                   style="padding:12px 18px;border-radius:12px;background:#f3f4f6;color:#111827;text-decoration:none;font-weight:700;">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection