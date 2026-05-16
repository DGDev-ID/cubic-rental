<?php

namespace App\Http\Controllers;

use App\Models\FnbItem;
use App\Models\FnbAddon;
use App\Http\Requests\FnbItemRequest;
use App\Http\Requests\FnbAddonRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class FnbController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Fnb/Index', [
            'items'  => FnbItem::withTrashed()->orderBy('category')->orderBy('name')->get(),
            'addons' => FnbAddon::withTrashed()->orderBy('name')->get(),
        ]);
    }

    public function storeItem(FnbItemRequest $request): RedirectResponse
    {
        FnbItem::create($request->validated());
        return back()->with('success', 'Item FNB berhasil ditambahkan.');
    }

    public function updateItem(FnbItemRequest $request, FnbItem $fnbItem): RedirectResponse
    {
        $fnbItem->update($request->validated());
        return back()->with('success', 'Item FNB berhasil diperbarui.');
    }

    public function destroyItem(FnbItem $fnbItem): RedirectResponse
    {
        $fnbItem->delete();
        return back()->with('success', 'Item FNB berhasil dihapus.');
    }

    public function storeAddon(FnbAddonRequest $request): RedirectResponse
    {
        FnbAddon::create($request->validated());
        return back()->with('success', 'Add-on berhasil ditambahkan.');
    }

    public function updateAddon(FnbAddonRequest $request, FnbAddon $fnbAddon): RedirectResponse
    {
        $fnbAddon->update($request->validated());
        return back()->with('success', 'Add-on berhasil diperbarui.');
    }

    public function destroyAddon(FnbAddon $fnbAddon): RedirectResponse
    {
        $fnbAddon->delete();
        return back()->with('success', 'Add-on berhasil dihapus.');
    }
}
