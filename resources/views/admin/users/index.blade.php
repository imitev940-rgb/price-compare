@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4" style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;">
        <div>
            <h1 style="margin:0;font-size:28px;font-weight:700;">Users Management</h1>
            <p style="margin:6px 0 0;color:#6b7280;">Manage user accounts, roles and status.</p>
        </div>

        <a href="{{ route('admin.users.create') }}"
           style="display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:12px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600;">
            + Create User
        </a>
    </div>

    @if(session('success'))
        <div style="margin-bottom:16px;padding:12px 14px;border-radius:12px;background:#dcfce7;color:#166534;font-weight:600;">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div style="margin-bottom:16px;padding:12px 14px;border-radius:12px;background:#fee2e2;color:#991b1b;">
            <strong>There were some errors:</strong>
            <ul style="margin:8px 0 0 18px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:18px;box-shadow:0 10px 30px rgba(0,0,0,0.04);">
        <form method="GET" action="{{ route('admin.users.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
            <input
                type="text"
                name="q"
                value="{{ $q }}"
                placeholder="Search by name, email or role..."
                style="flex:1;min-width:260px;padding:12px 14px;border-radius:12px;border:1px solid #d1d5db;outline:none;"
            >

            <button type="submit"
                    style="padding:12px 16px;border:none;border-radius:12px;background:#111827;color:#fff;font-weight:600;cursor:pointer;">
                Search
            </button>

            <a href="{{ route('admin.users.index') }}"
               style="padding:12px 16px;border-radius:12px;background:#f3f4f6;color:#111827;text-decoration:none;font-weight:600;">
                Reset
            </a>
        </form>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                        <th style="text-align:left;padding:14px;">ID</th>
                        <th style="text-align:left;padding:14px;">Name</th>
                        <th style="text-align:left;padding:14px;">Email</th>
                        <th style="text-align:left;padding:14px;">Role</th>
                        <th style="text-align:left;padding:14px;">Status</th>
                        <th style="text-align:left;padding:14px;">Last Login</th>
                        <th style="text-align:left;padding:14px;">Created</th>
                        <th style="text-align:right;padding:14px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:14px;">{{ $user->id }}</td>
                            <td style="padding:14px;font-weight:600;">{{ $user->name }}</td>
                            <td style="padding:14px;">{{ $user->email }}</td>
                            <td style="padding:14px;">
                                <span style="display:inline-block;padding:6px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:13px;font-weight:700;">
                                    {{ \App\Models\User::roles()[$user->role] ?? $user->role }}
                                </span>
                            </td>
                            <td style="padding:14px;">
                                @if($user->is_active)
                                    <span style="display:inline-block;padding:6px 10px;border-radius:999px;background:#dcfce7;color:#166534;font-size:13px;font-weight:700;">
                                        Active
                                    </span>
                                @else
                                    <span style="display:inline-block;padding:6px 10px;border-radius:999px;background:#fee2e2;color:#991b1b;font-size:13px;font-weight:700;">
                                        Inactive
                                    </span>
                                @endif
                            </td>
                            <td style="padding:14px;">
                                {{ $user->last_login_at ? $user->last_login_at->format('d.m.Y H:i') : '—' }}
                            </td>
                            <td style="padding:14px;">
                                {{ $user->created_at ? $user->created_at->format('d.m.Y H:i') : '—' }}
                            </td>
                            <td style="padding:14px;text-align:right;">
                                <div style="display:inline-flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">

                                    {{-- VIEW --}}
                                    @if(!auth()->user()->isAdmin() || !$user->isSuperAdmin())
                                        <a href="{{ route('admin.users.show', $user) }}"
                                           style="padding:8px 12px;border-radius:10px;background:#f3f4f6;color:#111827;text-decoration:none;font-weight:600;">
                                            View
                                        </a>
                                    @endif

                                    {{-- EDIT --}}
                                    @if(!auth()->user()->isAdmin() || !$user->isSuperAdmin())
                                        <a href="{{ route('admin.users.edit', $user) }}"
                                           style="padding:8px 12px;border-radius:10px;background:#dbeafe;color:#1d4ed8;text-decoration:none;font-weight:600;">
                                            Edit
                                        </a>
                                    @endif

                                    @if(auth()->id() !== $user->id)

                                        {{-- ACTIVATE / DEACTIVATE --}}
                                        @if(!auth()->user()->isAdmin() || !$user->isSuperAdmin())
                                            <form action="{{ route('admin.users.toggle-status', $user) }}" method="POST" style="display:inline;">
                                                @csrf
                                                @method('PATCH')

                                                @if($user->is_active)
                                                    <button type="submit"
                                                            style="padding:8px 12px;border:none;border-radius:10px;background:#fef3c7;color:#92400e;font-weight:700;cursor:pointer;">
                                                        Deactivate
                                                    </button>
                                                @else
                                                    <button type="submit"
                                                            style="padding:8px 12px;border:none;border-radius:10px;background:#dcfce7;color:#166534;font-weight:700;cursor:pointer;">
                                                        Activate
                                                    </button>
                                                @endif
                                            </form>
                                        @endif

                                        {{-- DELETE: ONLY SUPER ADMIN --}}
                                        @if(auth()->user()->isSuperAdmin())
                                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" style="display:inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        style="padding:8px 12px;border:none;border-radius:10px;background:#fee2e2;color:#b91c1c;font-weight:700;cursor:pointer;">
                                                    Delete
                                                </button>
                                            </form>
                                        @endif

                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="padding:24px;text-align:center;color:#6b7280;">
                                No users found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:18px;">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection