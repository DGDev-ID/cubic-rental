<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Models\Console;
use App\Models\Employee;
use App\Models\FnbItem;
use App\Models\FnbAddon;
use App\Models\RentalFnbItem;
use App\Models\Reservation;
use App\Services\RentalService;
use App\Http\Requests\CreateRentalRequest;
use App\Http\Requests\AddTimeRequest;
use App\Http\Requests\AddFnbRequest;
use App\Http\Requests\PaymentRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RentalController extends Controller
{
    public function __construct(private RentalService $rentalService) {}

    public function index(): Response
    {
        $upcomingReservations = Reservation::with('console')
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('reserved_at', '>=', now())
            ->orderBy('reserved_at')
            ->get()
            ->groupBy('console_id')
            ->map(fn($items) => $items->map(fn($r) => [
                'id'            => $r->id,
                'customer_name' => $r->customer_name,
                'reserved_at'   => $r->reserved_at->format('d M Y, H:i'),
                'duration_hours'=> $r->duration_hours,
                'status'        => $r->status,
            ])->values());

        return Inertia::render('Rentals/Index', [
            'consoles'             => Console::where('status', 'available')->orderBy('name')->get(),
            'employees'            => Employee::where('status', 'active')->orderBy('name')->get(),
            'upcoming_reservations'=> $upcomingReservations,
        ]);
    }

    public function store(CreateRentalRequest $request): RedirectResponse
    {
        $rental = $this->rentalService->create($request->validated());
        return redirect()->route('rentals.show', $rental)->with('success', 'Rental berhasil dimulai.');
    }

    public function show(Rental $rental): Response
    {
        $rental->load(['console', 'employee', 'fnbItems.fnbItem', 'extensions', 'payments']);
        return Inertia::render('Rentals/Show', [
            'rental'     => $rental,
            'fnb_items'  => FnbItem::orderBy('category')->orderBy('name')->get(),
            'fnb_addons' => FnbAddon::orderBy('name')->get(),
        ]);
    }

    public function addTime(AddTimeRequest $request, Rental $rental): RedirectResponse
    {
        $this->rentalService->addTime($rental, $request->validated());
        return back()->with('success', 'Waktu berhasil ditambahkan.');
    }

    public function addFnb(AddFnbRequest $request, Rental $rental): RedirectResponse
    {
        $this->rentalService->addFnb($rental, $request->items);
        return back()->with('success', 'FNB berhasil ditambahkan.');
    }

    public function removeFnb(Rental $rental, RentalFnbItem $fnbItem): RedirectResponse
    {
        abort_if($fnbItem->rental_id !== $rental->id, 403);
        $rental->fnb_amount = max(0, $rental->fnb_amount - $fnbItem->subtotal);
        $rental->total_amount = $rental->rental_amount + $rental->fnb_amount + $rental->extra_amount;
        $rental->save();
        $fnbItem->delete();
        return back()->with('success', 'Item FNB dihapus.');
    }

    public function finish(Rental $rental): RedirectResponse
    {
        $this->rentalService->finish($rental);
        return redirect()->route('rentals.payment', $rental)->with('success', 'Rental selesai. Lanjutkan pembayaran.');
    }

    public function payment(Rental $rental): Response
    {
        $rental->load(['console', 'employee', 'fnbItems', 'extensions', 'payments']);
        $paidAmount = $rental->payments->sum('amount');
        $total = $rental->total_amount ?? 0;
        return Inertia::render('Rentals/Payment', [
            'rental' => array_merge($rental->toArray(), [
                'paid_amount'      => $paidAmount,
                'remaining_amount' => max(0, $total - $paidAmount),
                'is_running'       => $rental->status === 'running' || ($rental->status === 'half_paid' && $rental->ended_at === null),
            ]),
        ]);
    }

    public function pay(PaymentRequest $request, Rental $rental): RedirectResponse
    {
        $this->rentalService->pay($rental, $request->payments);
        $rental->refresh();

        if ($rental->status === 'half_paid' && $rental->ended_at === null) {
            return redirect()->route('rentals.show', $rental)->with('success', 'DP berhasil dicatat.');
        }

        if ($rental->status === 'half_paid') {
            return redirect()->route('rentals.payment', $rental)->with('success', 'Pembayaran sebagian dicatat. Lunasi sisa tagihan.');
        }

        return redirect()->route('rentals.receipt', $rental)->with('success', 'Pembayaran berhasil.');
    }

    public function receipt(Rental $rental): Response
    {
        $rental->load(['console', 'employee', 'fnbItems', 'extensions', 'payments']);
        return Inertia::render('Rentals/Receipt', [
            'rental'     => $rental,
            'store_name' => config('app.name'),
        ]);
    }

    public function history(Request $request): Response
    {
        $query = Rental::with(['console', 'employee', 'payments'])
            ->whereIn('status', ['finished', 'paid', 'half_paid', 'cancelled']);

        $fnbQuery = \App\Models\FnbOrder::with(['employee', 'console', 'items.fnbItem'])
            ->where('status', 'paid');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('transaction_code', 'ilike', "%{$request->search}%")
                  ->orWhere('customer_name', 'ilike', "%{$request->search}%");
            });
            $fnbQuery->where(function ($q) use ($request) {
                $q->where('code', 'ilike', "%{$request->search}%")
                  ->orWhere('customer_name', 'ilike', "%{$request->search}%");
            });
        }
        if ($request->date) {
            $query->whereDate('started_at', $request->date);
            $fnbQuery->whereDate('paid_at', $request->date);
        }
        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
            $fnbQuery->where('employee_id', $request->employee_id);
        }
        if ($request->console_id) {
            $query->where('console_id', $request->console_id);
            $fnbQuery->where('console_id', $request->console_id);
        }
        if ($request->payment_method) {
            $query->whereHas('payments', fn($q) => $q->where('method', $request->payment_method));
            $fnbQuery->where('payment_method', $request->payment_method);
        }

        return Inertia::render('Rentals/History', [
            'rentals'   => $query->orderByDesc('started_at')->paginate(20)->withQueryString(),
            'fnb_orders'=> $fnbQuery->orderByDesc('paid_at')->paginate(20, ['*'], 'fnb_page')->withQueryString(),
            'employees' => Employee::orderBy('name')->get(),
            'consoles'  => Console::orderBy('name')->get(),
            'filters'   => $request->only(['search', 'date', 'employee_id', 'console_id', 'payment_method']),
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = Rental::with(['console', 'employee', 'payments'])
            ->whereIn('status', ['finished', 'paid', 'half_paid', 'cancelled']);

        $fnbQuery = \App\Models\FnbOrder::with(['employee', 'console'])
            ->where('status', 'paid');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('transaction_code', 'ilike', "%{$request->search}%")
                  ->orWhere('customer_name', 'ilike', "%{$request->search}%");
            });
            $fnbQuery->where(function ($q) use ($request) {
                $q->where('code', 'ilike', "%{$request->search}%")
                  ->orWhere('customer_name', 'ilike', "%{$request->search}%");
            });
        }
        if ($request->date) {
            $query->whereDate('started_at', $request->date);
            $fnbQuery->whereDate('paid_at', $request->date);
        }
        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
            $fnbQuery->where('employee_id', $request->employee_id);
        }
        if ($request->console_id) {
            $query->where('console_id', $request->console_id);
            $fnbQuery->where('console_id', $request->console_id);
        }
        if ($request->payment_method) {
            $query->whereHas('payments', fn($q) => $q->where('method', $request->payment_method));
            $fnbQuery->where('payment_method', $request->payment_method);
        }

        $rentals   = $query->orderByDesc('started_at')->get();
        $fnbOrders = $fnbQuery->orderByDesc('paid_at')->get();

        $fileName = 'riwayat_transaksi_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=\"$fileName\"",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0",
        ];

        // Helper: format rupiah tanpa simbol, pakai titik sebagai pemisah ribuan
        $fmt = fn($v) => number_format((float)$v, 0, ',', '.');

        // Helper: tulis satu baris CSV dengan semicolon delimiter (standar Excel Indonesia)
        $row = fn($handle, array $cols) => fputcsv($handle, $cols, ';');

        $statusLabel = [
            'paid'      => 'Lunas',
            'half_paid' => 'Sebagian',
            'finished'  => 'Selesai',
            'cancelled' => 'Dibatalkan',
            'running'   => 'Berjalan',
        ];

        $callback = function () use ($rentals, $fnbOrders, $fmt, $row, $statusLabel) {
            $file = fopen('php://output', 'w');

            // BOM agar Excel buka UTF-8 dengan benar
            fputs($file, "\xEF\xBB\xBF");

            // ──────────────────────────────────────────────
            // BAGIAN 1 — RENTAL
            // ──────────────────────────────────────────────
            $row($file, ['=== RIWAYAT RENTAL ===']);
            $row($file, []);  // baris kosong

            // Header kolom rental
            $row($file, [
                'No',
                'Kode Transaksi',
                'Customer',
                'Console',
                'Tipe Console',
                'Operator',
                'Tipe Rental',
                'Mulai',
                'Selesai',
                'Status',
                'Metode Bayar',
                'Subtotal Rental (Rp)',
                'FNB (Rp)',
                'Extra (Rp)',
                'Total (Rp)',
                'Dibayar (Rp)',
                'Sisa (Rp)',
            ]);

            $rentalTotal       = 0;
            $rentalFnbTotal    = 0;
            $rentalExtraTotal  = 0;
            $rentalGrandTotal  = 0;
            $rentalPaidTotal   = 0;

            foreach ($rentals as $i => $r) {
                $methods  = $r->payments->pluck('method')->map(fn($m) => strtoupper($m))->join(' + ');
                $paid     = $r->payments->sum('amount');
                $sisa     = max(0, $r->total_amount - $paid);

                $rentalTotal      += $r->rental_amount;
                $rentalFnbTotal   += $r->fnb_amount;
                $rentalExtraTotal += $r->extra_amount;
                $rentalGrandTotal += $r->total_amount;
                $rentalPaidTotal  += $paid;

                $row($file, [
                    $i + 1,
                    $r->transaction_code,
                    $r->customer_name,
                    $r->console->name ?? '-',
                    strtoupper($r->console->type ?? '-'),
                    $r->employee->name ?? '-',
                    strtoupper($r->rental_type),
                    $r->started_at ? $r->started_at->format('d/m/Y H:i') : '-',
                    $r->ended_at   ? $r->ended_at->format('d/m/Y H:i')   : '-',
                    $statusLabel[$r->status] ?? $r->status,
                    $methods ?: '-',
                    $fmt($r->rental_amount),
                    $fmt($r->fnb_amount),
                    $fmt($r->extra_amount),
                    $fmt($r->total_amount),
                    $fmt($paid),
                    $fmt($sisa),
                ]);
            }

            // Baris kosong + subtotal rental
            $row($file, []);
            $row($file, [
                '', '', '', '', '', '', '', '', '',
                'SUBTOTAL RENTAL',
                '',
                $fmt($rentalTotal),
                $fmt($rentalFnbTotal),
                $fmt($rentalExtraTotal),
                $fmt($rentalGrandTotal),
                $fmt($rentalPaidTotal),
                $fmt(max(0, $rentalGrandTotal - $rentalPaidTotal)),
            ]);
            $row($file, []);

            // ──────────────────────────────────────────────
            // BAGIAN 2 — FNB ONLY
            // ──────────────────────────────────────────────
            $row($file, ['=== RIWAYAT FNB ONLY ===']);
            $row($file, []);

            $row($file, [
                'No',
                'Kode Transaksi',
                'Customer',
                'Console',
                'Operator',
                'Waktu',
                'Status',
                'Metode Bayar',
                'Total FNB (Rp)',
            ]);

            $fnbGrandTotal = 0;

            foreach ($fnbOrders as $i => $f) {
                $fnbGrandTotal += $f->total_amount;

                $row($file, [
                    $i + 1,
                    $f->code,
                    $f->customer_name ?: '-',
                    $f->console->name ?? '-',
                    $f->employee->name ?? '-',
                    $f->paid_at ? $f->paid_at->format('d/m/Y H:i') : '-',
                    'Lunas',
                    strtoupper($f->payment_method ?? '-'),
                    $fmt($f->total_amount),
                ]);
            }

            $row($file, []);
            $row($file, [
                '', '', '', '', '', '',
                'SUBTOTAL FNB',
                '',
                $fmt($fnbGrandTotal),
            ]);
            $row($file, []);

            // ──────────────────────────────────────────────
            // GRAND TOTAL
            // ──────────────────────────────────────────────
            $row($file, ['=== RINGKASAN ===']);
            $row($file, []);
            $row($file, ['Keterangan', 'Jumlah Transaksi', 'Total (Rp)']);
            $row($file, ['Rental', $rentals->count(), $fmt($rentalGrandTotal)]);
            $row($file, ['FNB Only', $fnbOrders->count(), $fmt($fnbGrandTotal)]);
            $row($file, ['GRAND TOTAL', $rentals->count() + $fnbOrders->count(), $fmt($rentalGrandTotal + $fnbGrandTotal)]);
            $row($file, []);
            $row($file, ['Diekspor pada', now()->format('d/m/Y H:i:s')]);

            fclose($file);
        };

        return new \Symfony\Component\HttpFoundation\StreamedResponse($callback, 200, $headers);
    }
}