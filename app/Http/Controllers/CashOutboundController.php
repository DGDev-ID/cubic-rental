<?php

namespace App\Http\Controllers;

use App\Models\CashOutbound;
use App\Models\Employee;
use App\Http\Requests\CashOutboundRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class CashOutboundController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('CashOutbound/Index', [
            'outbounds' => CashOutbound::with('employee')
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->paginate(20),
            'employees' => Employee::where('status', 'active')->orderBy('name')->get(),
            'total_this_month' => CashOutbound::whereYear('date', now()->year)
                ->whereMonth('date', now()->month)
                ->sum('nominal'),
        ]);
    }

    public function store(CashOutboundRequest $request): RedirectResponse
    {
        CashOutbound::create($request->validated());
        return back()->with('success', 'Pengeluaran berhasil dicatat.');
    }

    public function update(CashOutboundRequest $request, CashOutbound $cashOutbound): RedirectResponse
    {
        $cashOutbound->update($request->validated());
        return back()->with('success', 'Pengeluaran berhasil diperbarui.');
    }

    public function destroy(CashOutbound $cashOutbound): RedirectResponse
    {
        $cashOutbound->delete();
        return back()->with('success', 'Pengeluaran berhasil dihapus.');
    }
}
