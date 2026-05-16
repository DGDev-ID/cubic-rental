<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Http\Requests\PackageRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class PackageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Packages/Index', [
            'packages' => Package::withTrashed()->orderBy('name')->get(),
        ]);
    }

    public function store(PackageRequest $request): RedirectResponse
    {
        Package::create($request->validated());
        return back()->with('success', 'Paket berhasil ditambahkan.');
    }

    public function update(PackageRequest $request, Package $package): RedirectResponse
    {
        $package->update($request->validated());
        return back()->with('success', 'Paket berhasil diperbarui.');
    }

    public function destroy(Package $package): RedirectResponse
    {
        $package->delete();
        return back()->with('success', 'Paket berhasil dihapus.');
    }
}
