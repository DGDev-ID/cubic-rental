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
        } elseif ($request->month) {
            [$y, $m] = explode('-', $request->month);
            $query->whereYear('started_at', (int) $y)->whereMonth('started_at', (int) $m);
            $fnbQuery->whereYear('paid_at', (int) $y)->whereMonth('paid_at', (int) $m);
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

        // Filtered summary (revenue & customers for the current filter, excluding cancelled)
        $summaryRevenue = $query->clone()->whereIn('status', ['finished', 'paid', 'half_paid'])->sum('total_amount')
            + $fnbQuery->clone()->sum('total_amount');
        $summaryCustomers = $query->clone()->whereIn('status', ['finished', 'paid', 'half_paid'])->count()
            + $fnbQuery->clone()->count();

        // Today stats (always today, unaffected by filters)
        $today = now()->toDateString();
        $todayRevenue = Rental::whereIn('status', ['finished', 'paid', 'half_paid'])
                ->whereDate('started_at', $today)->sum('total_amount')
            + \App\Models\FnbOrder::where('status', 'paid')->whereDate('paid_at', $today)->sum('total_amount');
        $todayCustomers = Rental::whereDate('started_at', $today)->count()
            + \App\Models\FnbOrder::where('status', 'paid')->whereDate('paid_at', $today)->count();

        // Monthly stats for the selected year
        $statsYear = (int) ($request->stats_year ?? now()->year);
        $mRentals = Rental::whereIn('status', ['finished', 'paid', 'half_paid'])
            ->whereYear('started_at', $statsYear)
            ->selectRaw('EXTRACT(MONTH FROM started_at) as month, SUM(total_amount) as revenue, COUNT(*) as customers')
            ->groupByRaw('EXTRACT(MONTH FROM started_at)')
            ->get()->keyBy(fn($r) => (int) $r->month);
        $mFnb = \App\Models\FnbOrder::where('status', 'paid')
            ->whereYear('paid_at', $statsYear)
            ->selectRaw('EXTRACT(MONTH FROM paid_at) as month, SUM(total_amount) as revenue, COUNT(*) as customers')
            ->groupByRaw('EXTRACT(MONTH FROM paid_at)')
            ->get()->keyBy(fn($r) => (int) $r->month);
        $monthlyStats = collect(range(1, 12))->map(fn($m) => [
            'month'     => $m,
            'revenue'   => (float) (($mRentals->get($m)?->revenue ?? 0) + ($mFnb->get($m)?->revenue ?? 0)),
            'customers' => (int) (($mRentals->get($m)?->customers ?? 0) + ($mFnb->get($m)?->customers ?? 0)),
        ])->values();

        return Inertia::render('Rentals/History', [
            'rentals'           => $query->orderByDesc('started_at')->paginate(20)->withQueryString(),
            'fnb_orders'        => $fnbQuery->orderByDesc('paid_at')->paginate(20, ['*'], 'fnb_page')->withQueryString(),
            'employees'         => Employee::orderBy('name')->get(),
            'consoles'          => Console::orderBy('name')->get(),
            'filters'           => $request->only(['search', 'date', 'month', 'employee_id', 'console_id', 'payment_method']),
            'today_revenue'     => (float) $todayRevenue,
            'today_customers'   => (int) $todayCustomers,
            'summary_revenue'   => (float) $summaryRevenue,
            'summary_customers' => (int) $summaryCustomers,
            'monthly_stats'     => $monthlyStats,
            'stats_year'        => $statsYear,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = Rental::with(['console'])
            ->whereIn('status', ['finished', 'paid', 'half_paid']);

        $fnbQuery = \App\Models\FnbOrder::with(['console'])
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
        } elseif ($request->month) {
            [$y, $m] = explode('-', $request->month);
            $query->whereYear('started_at', (int) $y)->whereMonth('started_at', (int) $m);
            $fnbQuery->whereYear('paid_at', (int) $y)->whereMonth('paid_at', (int) $m);
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

        $fmt = fn($v) => number_format((float) $v, 0, ',', '.');
        $row = fn($handle, array $cols) => fputcsv($handle, $cols, ';');

        $callback = function () use ($rentals, $fnbOrders, $fmt, $row) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            $row($file, ['Tanggal', 'Console', 'Total FNB (Rp)', 'Total Rental (Rp)', 'Total Semua (Rp)']);

            $totalFnb = $totalRental = $totalSemua = 0;

            foreach ($rentals as $r) {
                $totalFnb    += $r->fnb_amount;
                $totalRental += $r->rental_amount;
                $totalSemua  += $r->total_amount;
                $row($file, [
                    $r->started_at?->format('d/m/Y') ?? '-',
                    $r->console->name ?? '-',
                    $fmt($r->fnb_amount),
                    $fmt($r->rental_amount),
                    $fmt($r->total_amount),
                ]);
            }

            foreach ($fnbOrders as $f) {
                $totalFnb   += $f->total_amount;
                $totalSemua += $f->total_amount;
                $row($file, [
                    $f->paid_at?->format('d/m/Y') ?? '-',
                    $f->console->name ?? '-',
                    $fmt($f->total_amount),
                    $fmt(0),
                    $fmt($f->total_amount),
                ]);
            }

            $row($file, []);
            $row($file, ['TOTAL', '', $fmt($totalFnb), $fmt($totalRental), $fmt($totalSemua)]);
            $row($file, []);
            $row($file, ['Diekspor pada', now()->format('d/m/Y H:i:s')]);

            fclose($file);
        };

        return new \Symfony\Component\HttpFoundation\StreamedResponse($callback, 200, $headers);
    }
}