<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;

class StoreController extends Controller
{
    public function index()
    {
        $stores = Store::latest()->paginate(15);

        return view('stores.index', compact('stores'));
    }

    public function create()
    {
        return view('stores.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'nullable|url|max:255',
        ]);

        Store::create($request->only([
            'name',
            'url',
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
            'url' => 'nullable|url|max:255',
        ]);

        $store->update($request->only([
            'name',
            'url',
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