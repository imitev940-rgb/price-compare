<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));

        $users = User::query()
            ->when(auth()->user()->isAdmin(), function ($query) {
                $query->where('role', '!=', User::ROLE_SUPER_ADMIN);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($subQuery) use ($q) {
                    $subQuery->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('role', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'q'));
    }

    public function create(): View
    {
        $roles = User::roles();

        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $allowedRoles = array_keys(User::roles());

        if (auth()->user()->isAdmin()) {
            $allowedRoles = array_values(array_filter($allowedRoles, fn ($role) => $role !== User::ROLE_SUPER_ADMIN));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in($allowedRoles)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(User $user): View
    {
        if (auth()->user()->isAdmin() && $user->isSuperAdmin()) {
            abort(403, 'You do not have permission to view this account.');
        }

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        if (auth()->user()->isAdmin() && $user->isSuperAdmin()) {
            abort(403, 'You do not have permission to edit this account.');
        }

        $roles = User::roles();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if (auth()->user()->isAdmin() && $user->isSuperAdmin()) {
            abort(403, 'You do not have permission to update this account.');
        }

        $allowedRoles = array_keys(User::roles());

        if (auth()->user()->isAdmin()) {
            $allowedRoles = array_values(array_filter($allowedRoles, fn ($role) => $role !== User::ROLE_SUPER_ADMIN));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in($allowedRoles)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (auth()->user()->isAdmin() && ($data['role'] ?? null) === User::ROLE_SUPER_ADMIN) {
            return back()->withErrors([
                'role' => 'Admin cannot assign Super Admin role.',
            ])->withInput();
        }

        $updateData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        if (auth()->id() === $user->id && empty($updateData['is_active'])) {
            return back()->withErrors([
                'is_active' => 'You cannot deactivate your own account.',
            ])->withInput();
        }

        $user->update($updateData);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return back()->withErrors([
                'delete' => 'You cannot delete your own account.',
            ]);
        }

        if (auth()->user()->isAdmin()) {
            abort(403, 'Admin cannot delete accounts.');
        }

        if ($user->isSuperAdmin() && !auth()->user()->isSuperAdmin()) {
            abort(403, 'You do not have permission to delete this account.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return back()->withErrors([
                'status' => 'You cannot deactivate your own account.',
            ]);
        }

        if (auth()->user()->isAdmin() && $user->isSuperAdmin()) {
            abort(403, 'You do not have permission to change this account.');
        }

        $user->update([
            'is_active' => !$user->is_active,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User status updated successfully.');
    }
}