<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;

class StoreController extends Controller
{
    public function index(Request $request)
    {
        $allowedPerPage = [10, 25, 50, 100];
        $perPage = (int) $request->get('per_page', 10);

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $search = trim((string) $request->get('search', ''));

        $stores = Store::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('base_url', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('stores.index', compact('stores', 'search', 'perPage'));
    }

    public function create()
    {
        return view('stores.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'base_url' => 'nullable|url|max:255',
        ]);

        Store::create($request->only([
            'name',
            'base_url',
        ]));

        return redirect()->route('stores.index')
            ->with('success', 'Магазинът беше създаден успешно.');
    }

    public function show(Store $store)
    {
        return view('stores.show', compact('store'));
    }

    public function edit(Store $store)
    {
        return view('stores.edit', compact('store'));
    }

    public function update(Request $request, Store $store)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'base_url' => 'nullable|url|max:255',
        ]);

        $store->update($request->only([
            'name',
            'base_url',
        ]));

        return redirect()->route('stores.index')
            ->with('success', 'Магазинът беше редактиран успешно.');
    }

    public function destroy(Store $store)
    {
        $store->competitorLinks()->delete();
        $store->delete();

        return redirect()->route('stores.index')
            ->with('success', 'Магазинът беше изтрит успешно.');
    }
}