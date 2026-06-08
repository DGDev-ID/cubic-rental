<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FnbAddon;
use App\Models\FnbItem;
use App\Models\FnbOrder;
use App\Models\FnbOrderItem;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FnbOrderController extends Controller
{
    public function index(): Response
    {
        $orders = FnbOrder::with(['employee', 'console', 'items.fnbItem'])
            ->latest()
            ->paginate(20);

        return Inertia::render('FnbOrders/Index', [
            'orders'    => $orders,
            'fnbItems'  => FnbItem::where('is_available', true)->orderBy('category')->orderBy('name')->get(),
            'fnbAddons' => FnbAddon::where('is_available', true)->orderBy('name')->get(),
            'employees' => Employee::orderBy('name')->get(),
            'consoles'  => \App\Models\Console::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'employee_id'    => 'required|exists:employees,id',
            'console_id'     => 'nullable|exists:consoles,id',
            'customer_name'  => 'nullable|string|max:100',
            'notes'          => 'nullable|string',
            'payment_method' => 'required|in:cash,qris',
            'items'          => 'required|array|min:1',
            'items.*.fnb_item_id'  => 'required|exists:fnb_items,id',
            'items.*.qty'          => 'required|integer|min:1',
            'items.*.addons'       => 'nullable|array',
        ]);

        $totalAmount = 0;
        $itemsData   = [];

        foreach ($request->items as $item) {
            $fnbItem   = FnbItem::findOrFail($item['fnb_item_id']);
            $unitPrice = (float) $fnbItem->price;
            $qty       = (int) $item['qty'];

            $addonsPrice = 0;
            $addonsArr   = [];
            foreach ($item['addons'] ?? [] as $addon) {
                $addonsPrice += (float) $addon['price'];
                $addonsArr[]  = $addon;
            }

            $subtotal      = ($unitPrice + $addonsPrice) * $qty;
            $totalAmount  += $subtotal;

            $itemsData[] = [
                'fnb_item_id'  => $fnbItem->id,
                'qty'          => $qty,
                'unit_price'   => $unitPrice,
                'subtotal'     => $subtotal,
                'addons'       => $addonsArr ?: null,
                'addons_price' => $addonsPrice,
            ];
        }

        $code  = 'FNB-' . strtoupper(substr(uniqid(), -6));

        $order = FnbOrder::create([
            'code'           => $code,
            'customer_name'  => $request->customer_name,
            'employee_id'    => $request->employee_id,
            'console_id'     => $request->console_id,
            'total_amount'   => $totalAmount,
            'status'         => 'paid',
            'payment_method' => $request->payment_method,
            'paid_at'        => Carbon::now(),
            'notes'          => $request->notes,
        ]);

        foreach ($itemsData as $iData) {
            $order->items()->create($iData);
        }

        return redirect()->route('fnb-orders.index')
            ->with('success', 'Transaksi FnB berhasil disimpan.');
    }

    public function show(FnbOrder $fnbOrder): Response
    {
        $fnbOrder->load(['employee', 'items.fnbItem']);
        return Inertia::render('FnbOrders/Show', [
            'order' => $fnbOrder,
        ]);
    }

    public function pay(Request $request, FnbOrder $fnbOrder): RedirectResponse
    {
        $request->validate([
            'payment_method' => 'required|in:cash,qris,transfer',
        ]);

        if ($fnbOrder->status === 'paid') {
            return back()->with('error', 'Order sudah dibayar.');
        }

        $fnbOrder->update([
            'status'         => 'paid',
            'payment_method' => $request->payment_method,
            'paid_at'        => Carbon::now(),
        ]);

        return redirect()->route('fnb-orders.index')
            ->with('success', 'Pembayaran berhasil.');
    }

    public function destroy(FnbOrder $fnbOrder): RedirectResponse
    {
        if ($fnbOrder->status === 'paid') {
            return back()->with('error', 'Order yang sudah dibayar tidak bisa dihapus.');
        }
        $fnbOrder->delete();
        return back()->with('success', 'Order dibatalkan.');
    }
}
