<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use App\Models\Console;
use App\Models\Employee;
use App\Models\Member;
use App\Models\MemberPackage;
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
            'members'              => Member::where('status', 'active')->orderBy('name')->get(['id','member_number','name','console_type','remaining_minutes']),
            'member_packages'      => MemberPackage::where('is_active', true)->orderBy('price')->get(),
        ]);
    }

    public function store(CreateRentalRequest $request): RedirectResponse
    {
        $rental = $this->rentalService->create($request->validated());
        return redirect()->route('rentals.show', $rental)->with('success', 'Rental berhasil dimulai.');
    }

    public function show(Rental $rental): Response
    {
        $rental->load(['console', 'employee', 'cashier', 'fnbItems.fnbItem', 'extensions', 'payments']);

        $now = \Carbon\Carbon::now();
        $durationMinutes = $rental->started_at->diffInMinutes($now);

        if ($rental->member_id) {
            $displayRentalAmount = 0;
        } elseif ($rental->rental_amount > 0) {
            $displayRentalAmount = (float) $rental->rental_amount;
        } elseif ($rental->scheduled_end_at) {
            $totalExtensionMinutes = (int) $rental->extensions->sum('added_minutes');
            $scheduledMinutes = $rental->started_at->diffInMinutes($rental->scheduled_end_at);
            $baseMinutes = max(0, $scheduledMinutes - $totalExtensionMinutes);
            $displayRentalAmount = round((($rental->console->price_per_hour / 60) * $baseMinutes) / 1000) * 1000;
        } else {
            $billedMinutes = (int)(ceil(max($durationMinutes, 60) / 10) * 10);
            $displayRentalAmount = round((($rental->console->price_per_hour / 60) * $billedMinutes) / 1000) * 1000;
        }

        $displayTotalAmount = $displayRentalAmount + (float) $rental->fnb_amount + (float) $rental->extra_amount;
        $paidAmount = (float) $rental->payments->sum('amount');

        $rentalData = array_merge($rental->toArray(), [
            'rental_amount' => $displayRentalAmount,
            'total_amount'  => $displayTotalAmount,
            'paid_amount'   => $paidAmount,
        ]);

        return Inertia::render('Rentals/Show', [
            'rental'             => $rentalData,
            'fnb_items'          => FnbItem::orderBy('category')->orderBy('name')->get(),
            'fnb_addons'         => FnbAddon::orderBy('name')->get(),
            'available_consoles' => Console::where('status', 'available')->orderBy('name')->get(),
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

    public function updateFnb(Request $request, Rental $rental, RentalFnbItem $fnbItem): RedirectResponse
    {
        $request->validate([
            'fnb_item_id'               => 'required|exists:fnb_items,id',
            'qty'                       => 'required|integer|min:1',
            'addons'                    => 'nullable|array',
            'addons.*.addon_id'         => 'nullable|integer',
            'addons.*.name'             => 'required|string',
            'addons.*.price'            => 'required|numeric|min:0',
        ]);

        $this->rentalService->updateFnbItem($rental, $fnbItem, $request->all());
        return back()->with('success', 'Pesanan FNB berhasil diperbarui.');
    }

    public function removeFnb(Rental $rental, RentalFnbItem $fnbItem): RedirectResponse
    {
        $this->rentalService->removeFnbItem($rental, $fnbItem);
        return back()->with('success', 'Item FNB berhasil dihapus.');
    }

    public function finish(Rental $rental): RedirectResponse
    {
        $this->rentalService->finish($rental);
        $rental->refresh();
        if ($rental->status === 'paid') {
            return back()->with('success', 'Rental selesai dan tagihan sudah lunas.');
        }
        return redirect()->route('rentals.payment', $rental)->with('success', 'Rental selesai. Lanjutkan pembayaran.');
    }

    public function switchConsole(Request $request, Rental $rental): RedirectResponse
    {
        $request->validate([
            'new_console_id' => 'required|exists:consoles,id',
        ]);

        try {
            $this->rentalService->switchConsole($rental, (int) $request->new_console_id);
            return back()->with('success', 'Berhasil memindahkan console / ruangan.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function updateNotes(Request $request, Rental $rental): RedirectResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $this->rentalService->updateNotes($rental, $request->notes);
        return back()->with('success', 'Catatan console berhasil diperbarui.');
    }

    public function destroy(Rental $rental): RedirectResponse
    {
        if ($rental->console && $rental->status === 'running') {
            $rental->console->update(['status' => 'available']);
        }
        $rental->delete();

        return back()->with('success', 'Data transaksi rental berhasil dihapus.');
    }

    public function resumeWithTime(Request $request, Rental $rental): RedirectResponse
    {
        $request->validate([
            'added_minutes' => 'required|integer|min:10|max:1440',
            'notes'         => 'nullable|string|max:255',
        ]);

        $this->rentalService->resumeWithTime($rental, $request->only(['added_minutes', 'notes']));
        return back()->with('success', 'Waktu bermain berhasil ditambahkan. Console aktif kembali!');
    }

    public function payment(Rental $rental): Response
    {
        $rental->load(['console', 'employee', 'cashier', 'fnbItems.fnbItem', 'extensions', 'payments']);
        $paidAmount = $rental->payments->sum('amount');
        $total = $rental->total_amount ?? 0;
        return Inertia::render('Rentals/Payment', [
            'rental' => array_merge($rental->toArray(), [
                'paid_amount'      => $paidAmount,
                'remaining_amount' => max(0, $total - $paidAmount),
                'is_running'       => $rental->status === 'running' || ($rental->status === 'half_paid' && $rental->ended_at === null),
            ]),
            'employees' => Employee::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function pay(PaymentRequest $request, Rental $rental): RedirectResponse
    {
        $this->rentalService->pay($rental, $request->payments, $request->cashier_id);
        $rental->refresh();

        // Jika rental masih aktif bermain (ended_at belum diisi):
        if ($rental->ended_at === null) {
            return redirect()->route('rentals.show', $rental)->with('success', 'Pembayaran berhasil dicatat. Console masih aktif bermain.');
        }

        if ($rental->status === 'half_paid') {
            return redirect()->route('rentals.payment', $rental)->with('success', 'Pembayaran sebagian dicatat. Lunasi sisa tagihan.');
        }

        return redirect()->route('rentals.receipt', $rental)->with('success', 'Pembayaran berhasil.');
    }

    public function updatePaymentMethod(Request $request, Rental $rental): RedirectResponse
    {
        $request->validate([
            'payment_id' => 'nullable|exists:rental_payments,id',
            'method'     => 'required|in:cash,qris,transfer,debit',
        ]);

        if ($request->payment_id) {
            $payment = $rental->payments()->findOrFail($request->payment_id);
            $payment->update([
                'method' => $request->method,
            ]);
        } else {
            $rental->payments()->update([
                'method' => $request->method,
            ]);
        }

        return back()->with('success', 'Metode pembayaran berhasil diubah ke ' . strtoupper($request->method) . '.');
    }

    public function receipt(Rental $rental): Response
    {
        $rental->load(['console', 'employee', 'cashier', 'fnbItems.fnbItem', 'extensions', 'payments']);
        return Inertia::render('Rentals/Receipt', [
            'rental'     => $rental,
            'store_name' => config('app.name'),
        ]);
    }

    public function history(Request $request): Response
    {
        $query = Rental::with(['console', 'employee', 'cashier', 'payments', 'fnbItems.fnbItem', 'extensions', 'member'])
            ->where(function ($q) {
                $q->whereNotNull('ended_at')
                  ->orWhere('status', 'cancelled');
            })
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
        } elseif ($request->year) {
            $query->whereYear('started_at', (int) $request->year);
            $fnbQuery->whereYear('paid_at', (int) $request->year);
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
        $rentalQuerySummary = $query->clone()->whereIn('status', ['finished', 'paid', 'half_paid']);
        $fnbQuerySummary    = $fnbQuery->clone();

        $summaryRentalRevenue = (float) ($rentalQuerySummary->clone()->sum('rental_amount') + $rentalQuerySummary->clone()->sum('extra_amount'));
        $summaryFnbFromRental = (float) $rentalQuerySummary->clone()->sum('fnb_amount');
        $summaryFnbStandalone = (float) $fnbQuerySummary->clone()->sum('total_amount');
        $summaryFnbRevenue    = $summaryFnbFromRental + $summaryFnbStandalone;
        $summaryRevenue       = $summaryRentalRevenue + $summaryFnbRevenue;

        $summaryCustomers = $rentalQuerySummary->count() + $fnbQuerySummary->count();

        // Today stats (always today, unaffected by filters)
        $today = now()->toDateString();
        $todayRentals = Rental::whereNotNull('ended_at')->whereIn('status', ['finished', 'paid', 'half_paid'])->whereDate('started_at', $today);
        $todayRevenue = $todayRentals->sum('total_amount')
            + \App\Models\FnbOrder::where('status', 'paid')->whereDate('paid_at', $today)->sum('total_amount');
        $todayCustomers = Rental::whereNotNull('ended_at')->whereDate('started_at', $today)->count()
            + \App\Models\FnbOrder::where('status', 'paid')->whereDate('paid_at', $today)->count();

        // Yearly stats (all available years)
        $yRentals = Rental::whereNotNull('ended_at')->whereIn('status', ['finished', 'paid', 'half_paid'])
            ->selectRaw('EXTRACT(YEAR FROM started_at) as year, SUM(COALESCE(rental_amount, 0) + COALESCE(extra_amount, 0)) as rental_revenue, SUM(COALESCE(fnb_amount, 0)) as fnb_revenue, SUM(total_amount) as revenue, COUNT(*) as customers')
            ->groupByRaw('EXTRACT(YEAR FROM started_at)')
            ->orderByRaw('EXTRACT(YEAR FROM started_at)')
            ->get()->keyBy(fn($r) => (int) $r->year);
        $yFnb = \App\Models\FnbOrder::where('status', 'paid')
            ->selectRaw('EXTRACT(YEAR FROM paid_at) as year, SUM(total_amount) as revenue, COUNT(*) as customers')
            ->groupByRaw('EXTRACT(YEAR FROM paid_at)')
            ->orderByRaw('EXTRACT(YEAR FROM paid_at)')
            ->get()->keyBy(fn($r) => (int) $r->year);
        $allYears = $yRentals->keys()->merge($yFnb->keys())->unique()->sort()->values();
        if ($allYears->isEmpty()) $allYears = collect([now()->year]);
        $yearlyStats = $allYears->map(fn($yr) => [
            'year'           => (int) $yr,
            'revenue'        => (float) (($yRentals->get($yr)?->revenue ?? 0) + ($yFnb->get($yr)?->revenue ?? 0)),
            'rental_revenue' => (float) ($yRentals->get($yr)?->rental_revenue ?? 0),
            'fnb_revenue'    => (float) (($yRentals->get($yr)?->fnb_revenue ?? 0) + ($yFnb->get($yr)?->revenue ?? 0)),
            'customers'      => (int) (($yRentals->get($yr)?->customers ?? 0) + ($yFnb->get($yr)?->customers ?? 0)),
        ])->values();

        // Monthly stats for the selected year
        $statsYear = (int) ($request->stats_year ?? now()->year);
        $mRentals = Rental::whereNotNull('ended_at')->whereIn('status', ['finished', 'paid', 'half_paid'])
            ->whereYear('started_at', $statsYear)
            ->selectRaw('EXTRACT(MONTH FROM started_at) as month, SUM(COALESCE(rental_amount, 0) + COALESCE(extra_amount, 0)) as rental_revenue, SUM(COALESCE(fnb_amount, 0)) as fnb_revenue, SUM(total_amount) as revenue, COUNT(*) as customers')
            ->groupByRaw('EXTRACT(MONTH FROM started_at)')
            ->get()->keyBy(fn($r) => (int) $r->month);
        $mFnb = \App\Models\FnbOrder::where('status', 'paid')
            ->whereYear('paid_at', $statsYear)
            ->selectRaw('EXTRACT(MONTH FROM paid_at) as month, SUM(total_amount) as revenue, COUNT(*) as customers')
            ->groupByRaw('EXTRACT(MONTH FROM paid_at)')
            ->get()->keyBy(fn($r) => (int) $r->month);
        $monthlyStats = collect(range(1, 12))->map(fn($m) => [
            'month'          => $m,
            'revenue'        => (float) (($mRentals->get($m)?->revenue ?? 0) + ($mFnb->get($m)?->revenue ?? 0)),
            'rental_revenue' => (float) ($mRentals->get($m)?->rental_revenue ?? 0),
            'fnb_revenue'    => (float) (($mRentals->get($m)?->fnb_revenue ?? 0) + ($mFnb->get($m)?->revenue ?? 0)),
            'customers'      => (int) (($mRentals->get($m)?->customers ?? 0) + ($mFnb->get($m)?->customers ?? 0)),
        ])->values();

        // Daily stats for the selected year+month
        $statsMonth = (int) ($request->stats_month ?? now()->month);
        $daysInMonth = \Carbon\Carbon::create($statsYear, $statsMonth)->daysInMonth;
        $dRentals = Rental::whereNotNull('ended_at')->whereIn('status', ['finished', 'paid', 'half_paid'])
            ->whereYear('started_at', $statsYear)
            ->whereMonth('started_at', $statsMonth)
            ->selectRaw('EXTRACT(DAY FROM started_at) as day, SUM(COALESCE(rental_amount, 0) + COALESCE(extra_amount, 0)) as rental_revenue, SUM(COALESCE(fnb_amount, 0)) as fnb_revenue, SUM(total_amount) as revenue, COUNT(*) as customers')
            ->groupByRaw('EXTRACT(DAY FROM started_at)')
            ->get()->keyBy(fn($r) => (int) $r->day);
        $dFnb = \App\Models\FnbOrder::where('status', 'paid')
            ->whereYear('paid_at', $statsYear)
            ->whereMonth('paid_at', $statsMonth)
            ->selectRaw('EXTRACT(DAY FROM paid_at) as day, SUM(total_amount) as revenue, COUNT(*) as customers')
            ->groupByRaw('EXTRACT(DAY FROM paid_at)')
            ->get()->keyBy(fn($r) => (int) $r->day);
        $dailyStats = collect(range(1, $daysInMonth))->map(fn($d) => [
            'day'            => $d,
            'revenue'        => (float) (($dRentals->get($d)?->revenue ?? 0) + ($dFnb->get($d)?->revenue ?? 0)),
            'rental_revenue' => (float) ($dRentals->get($d)?->rental_revenue ?? 0),
            'fnb_revenue'    => (float) (($dRentals->get($d)?->fnb_revenue ?? 0) + ($dFnb->get($d)?->revenue ?? 0)),
            'customers'      => (int) (($dRentals->get($d)?->customers ?? 0) + ($dFnb->get($d)?->customers ?? 0)),
        ])->values();

        return Inertia::render('Rentals/History', [
            'rentals'                => $query->orderByDesc('started_at')->paginate(20)->withQueryString(),
            'fnb_orders'             => $fnbQuery->orderByDesc('paid_at')->paginate(20, ['*'], 'fnb_page')->withQueryString(),
            'employees'              => Employee::orderBy('name')->get(),
            'consoles'               => Console::orderBy('name')->get(),
            'filters'                => $request->only(['search', 'date', 'month', 'year', 'employee_id', 'console_id', 'payment_method']),
            'today_revenue'          => (float) $todayRevenue,
            'today_customers'        => (int) $todayCustomers,
            'summary_revenue'        => (float) $summaryRevenue,
            'summary_rental_revenue' => (float) $summaryRentalRevenue,
            'summary_fnb_revenue'    => (float) $summaryFnbRevenue,
            'summary_customers'      => (int) $summaryCustomers,
            'yearly_stats'           => $yearlyStats,
            'monthly_stats'          => $monthlyStats,
            'daily_stats'            => $dailyStats,
            'stats_year'             => $statsYear,
            'stats_month'            => $statsMonth,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = Rental::with(['console', 'employee', 'cashier', 'fnbItems.fnbItem', 'payments', 'member'])
            ->whereNotNull('ended_at')
            ->whereIn('status', ['finished', 'paid', 'half_paid']);

        $fnbQuery = \App\Models\FnbOrder::with(['console', 'employee', 'items.fnbItem'])
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
        } elseif ($request->year) {
            $query->whereYear('started_at', (int) $request->year);
            $fnbQuery->whereYear('paid_at', (int) $request->year);
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

        $rentals   = $query->orderBy('started_at', 'asc')->get();
        $fnbOrders = $fnbQuery->orderBy('paid_at', 'asc')->get();

        // Build period label for title
        $periodLabel = 'Semua Waktu';
        if ($request->date) {
            $periodLabel = \Carbon\Carbon::parse($request->date)->translatedFormat('d F Y');
        } elseif ($request->month) {
            [$y, $m] = explode('-', $request->month);
            $periodLabel = \Carbon\Carbon::create($y, $m)->translatedFormat('F Y');
        } elseif ($request->year) {
            $periodLabel = 'Tahun ' . $request->year;
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Riwayat Transaksi');

        // ---------- Column headers (Matching manual accounting sheet) ----------
        $headers = [
            'Tanggal',
            'Operator',
            'Jam Main',
            'Jumlah Jam',
            'Table',
            'Harga Per-Jam',
            'FNB',
            'QTY',
            'Harga FNB',
            'Total FNB (Rp)',
            'Total PS (Rp)',
            'Total (Rp)',
            'Pay',
            'Kasir',
            'Cash (Rp)',
            'QRIS (Rp)',
        ];
        $colCount = count($headers);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        // ---------- Header branding ----------
        $sheet->setCellValue('A1', config('app.name', 'Rental PS') . ' – PEMBUKUAN TRANSAKSI');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '0284C7']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A2', 'Periode: ' . $periodLabel);
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '0EA5E9']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A3', 'Diekspor pada: ' . now()->format('d/m/Y H:i:s'));
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle('A3')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],
        ]);

        $headerRow = 5;
        foreach ($headers as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}{$headerRow}", $h);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => '0C4A6E'], 'size' => 10],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'BAE6FD']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '7DD3FC']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(26);

        $row = $headerRow + 1;
        $totalFnb = 0;
        $totalRental = 0;
        $totalSemua = 0;
        $totalCash = 0;
        $totalQris = 0;

        $numFmt = '#,##0';
        $altFill = ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0F9FF']];
        $normFill = ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']];
        $fontColor = ['color' => ['rgb' => '0F172A']];
        $borderStyle = ['borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]]];

        // Format rentals
        foreach ($rentals as $idx => $r) {
            // Include extra_amount (penambahan waktu) into Total Rental PS
            $rentalPSAmount = (float) ($r->rental_amount + $r->extra_amount);
            $fnbAmount      = (float) $r->fnb_amount;
            $grandTotal     = (float) $r->total_amount;

            $totalFnb    += $fnbAmount;
            $totalRental += $rentalPSAmount;
            $totalSemua  += $grandTotal;

            // FnB details breakdown
            $fnbNames = [];
            $fnbQtys = [];
            $fnbPrices = [];
            foreach ($r->fnbItems as $item) {
                $baseName = $item->fnbItem?->name ?? 'FnB';
                $addonNames = !empty($item->addons) ? collect($item->addons)->pluck('name')->filter()->join(', ') : '';
                $fnbNames[]  = $addonNames ? "{$baseName} (+ {$addonNames})" : $baseName;
                $fnbQtys[]   = $item->qty;
                $fnbPrices[] = number_format((float) $item->subtotal, 0, ',', '.');
            }

            // Payments breakdown
            $cashAmount = (float) $r->payments->where('method', 'cash')->sum('amount');
            $qrisAmount = (float) $r->payments->whereIn('method', ['qris', 'transfer', 'debit'])->sum('amount');
            if ($cashAmount == 0 && $qrisAmount == 0 && $r->paid_amount > 0) {
                $cashAmount = (float) $r->paid_amount;
            }
            $totalCash += $cashAmount;
            $totalQris += $qrisAmount;

            $payMethods = $r->payments->pluck('method')->unique()->map(fn($m) => strtoupper($m))->values();
            if ($payMethods->count() > 1) {
                $payLabel = 'SPLIT';
            } elseif ($payMethods->count() === 1) {
                $payLabel = $payMethods->first();
            } else {
                $payLabel = 'CASH';
            }

            // Duration and Jam Main
            $jamStart = $r->started_at ? $r->started_at->format('H.i') : '-';
            $jamEnd   = $r->ended_at ? $r->ended_at->format('H.i') : ($r->scheduled_end_at ? $r->scheduled_end_at->format('H.i') : 'Selesai');
            $jamMain  = "{$jamStart} - {$jamEnd}";

            if ($r->scheduled_end_at && $r->started_at) {
                $durHours = round($r->started_at->diffInMinutes($r->scheduled_end_at) / 60, 1);
                $durLabel = $durHours . ' Jam';
            } elseif ($r->ended_at && $r->started_at) {
                $durHours = round($r->started_at->diffInMinutes($r->ended_at) / 60, 1);
                $durLabel = $r->rental_type === 'open_time' ? 'OPN (' . $durHours . 'j)' : $durHours . ' Jam';
            } else {
                $durLabel = $r->rental_type === 'open_time' ? 'OPN' : '-';
            }

            $fill = $idx % 2 === 0 ? $normFill : $altFill;

            $isMemberRental = !empty($r->member_id);
            $memberLabel = $isMemberRental ? 'MEMBER ' . strtoupper($r->member->name ?? $r->customer_name) : null;

            $sheet->setCellValue("A{$row}", $r->started_at?->format('d/m/Y') ?? '-');
            $sheet->setCellValue("B{$row}", $r->employee->name ?? '-');
            $sheet->setCellValue("C{$row}", $jamMain);
            $sheet->setCellValue("D{$row}", $durLabel);
            $sheet->setCellValue("E{$row}", $r->console->name ?? '-');

            if ($isMemberRental) {
                $sheet->setCellValue("F{$row}", $memberLabel);
                $sheet->setCellValue("K{$row}", $memberLabel);
            } else {
                $sheet->setCellValue("F{$row}", (float) ($r->console->price_per_hour ?? 0));
                $sheet->setCellValue("K{$row}", $rentalPSAmount);
            }

            $sheet->setCellValue("G{$row}", count($fnbNames) ? implode("\n", $fnbNames) : '-');
            $sheet->setCellValue("H{$row}", count($fnbQtys) ? implode("\n", $fnbQtys) : '-');
            $sheet->setCellValue("I{$row}", count($fnbPrices) ? implode("\n", $fnbPrices) : '-');
            $sheet->setCellValue("J{$row}", $fnbAmount);
            $sheet->setCellValue("L{$row}", $grandTotal);
            $sheet->setCellValue("M{$row}", $payLabel);
            $sheet->setCellValue("N{$row}", $r->cashier->name ?? $r->employee->name ?? '-');
            $sheet->setCellValue("O{$row}", $cashAmount);
            $sheet->setCellValue("P{$row}", $qrisAmount);

            // Styling
            $sheet->getStyle("A{$row}:P{$row}")->applyFromArray(array_merge(['font' => $fontColor, 'fill' => $fill], $borderStyle));
            $sheet->getStyle("A{$row}:E{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("G{$row}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("H{$row}")->getAlignment()->setWrapText(true)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I{$row}")->getAlignment()->setWrapText(true)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("J{$row}:L{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("M{$row}:N{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("O{$row}:P{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("A{$row}:P{$row}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode($numFmt);
            $sheet->getStyle("J{$row}:L{$row}")->getNumberFormat()->setFormatCode($numFmt);
            $sheet->getStyle("O{$row}:P{$row}")->getNumberFormat()->setFormatCode($numFmt);

            if ($isMemberRental) {
                $memberBadgeStyle = [
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E11D48']],
                    'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true, 'size' => 9],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
                ];
                $sheet->getStyle("F{$row}")->applyFromArray($memberBadgeStyle);
                $sheet->getStyle("K{$row}")->applyFromArray($memberBadgeStyle);
            }

            $row++;
        }

        // Format standalone FnB orders
        foreach ($fnbOrders as $idx => $f) {
            $fnbAmount  = (float) $f->total_amount;
            $grandTotal = (float) $f->total_amount;

            $totalFnb   += $fnbAmount;
            $totalSemua += $grandTotal;

            $fnbNames = [];
            $fnbQtys = [];
            $fnbPrices = [];
            foreach ($f->items as $item) {
                $baseName = $item->fnbItem?->name ?? 'FnB';
                $addonNames = !empty($item->addons) ? collect($item->addons)->pluck('name')->filter()->join(', ') : '';
                $fnbNames[]  = $addonNames ? "{$baseName} (+ {$addonNames})" : $baseName;
                $fnbQtys[]   = $item->qty;
                $fnbPrices[] = number_format((float) $item->subtotal, 0, ',', '.');
            }

            $method = strtolower($f->payment_method ?? 'cash');
            $isCash = $method === 'cash';
            $cashAmount = $isCash ? $grandTotal : 0;
            $qrisAmount = !$isCash ? $grandTotal : 0;
            $totalCash += $cashAmount;
            $totalQris += $qrisAmount;

            $fill = ($idx + count($rentals)) % 2 === 0 ? $normFill : $altFill;

            $sheet->setCellValue("A{$row}", $f->paid_at?->format('d/m/Y') ?? '-');
            $sheet->setCellValue("B{$row}", $f->employee->name ?? '-');
            $sheet->setCellValue("C{$row}", '-');
            $sheet->setCellValue("D{$row}", '-');
            $sheet->setCellValue("E{$row}", $f->console->name ?? 'Kasir / FnB');
            $sheet->setCellValue("F{$row}", 0);
            $sheet->setCellValue("G{$row}", count($fnbNames) ? implode("\n", $fnbNames) : '-');
            $sheet->setCellValue("H{$row}", count($fnbQtys) ? implode("\n", $fnbQtys) : '-');
            $sheet->setCellValue("I{$row}", count($fnbPrices) ? implode("\n", $fnbPrices) : '-');
            $sheet->setCellValue("J{$row}", $fnbAmount);
            $sheet->setCellValue("K{$row}", 0);
            $sheet->setCellValue("L{$row}", $grandTotal);
            $sheet->setCellValue("M{$row}", strtoupper($method));
            $sheet->setCellValue("N{$row}", $f->employee->name ?? '-');
            $sheet->setCellValue("O{$row}", $cashAmount);
            $sheet->setCellValue("P{$row}", $qrisAmount);

            $sheet->getStyle("A{$row}:P{$row}")->applyFromArray(array_merge(['font' => $fontColor, 'fill' => $fill], $borderStyle));
            $sheet->getStyle("A{$row}:E{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("G{$row}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("H{$row}")->getAlignment()->setWrapText(true)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I{$row}")->getAlignment()->setWrapText(true)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("J{$row}:L{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("M{$row}:N{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("O{$row}:P{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("A{$row}:P{$row}")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode($numFmt);
            $sheet->getStyle("J{$row}:L{$row}")->getNumberFormat()->setFormatCode($numFmt);
            $sheet->getStyle("O{$row}:P{$row}")->getNumberFormat()->setFormatCode($numFmt);

            if ($isMemberRental) {
                $memberBadgeStyle = [
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E11D48']],
                    'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true, 'size' => 9],
                    'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
                ];
                $sheet->getStyle("F{$row}")->applyFromArray($memberBadgeStyle);
                $sheet->getStyle("K{$row}")->applyFromArray($memberBadgeStyle);
            }

            $row++;
        }

        // ---------- Summary row ----------
        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->mergeCells("A{$row}:I{$row}");
        $sheet->setCellValue("J{$row}", (float) $totalFnb);
        $sheet->setCellValue("K{$row}", (float) $totalRental);
        $sheet->setCellValue("L{$row}", (float) $totalSemua);
        $sheet->setCellValue("M{$row}", '');
        $sheet->setCellValue("N{$row}", '');
        $sheet->mergeCells("M{$row}:N{$row}");
        $sheet->setCellValue("O{$row}", (float) $totalCash);
        $sheet->setCellValue("P{$row}", (float) $totalQris);

        $sheet->getStyle("A{$row}:P{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '0284C7']],
            'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM, 'color' => ['rgb' => '0369A1']]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("J{$row}:L{$row}")->getNumberFormat()->setFormatCode($numFmt);
        $sheet->getStyle("O{$row}:P{$row}")->getNumberFormat()->setFormatCode($numFmt);
        $sheet->getRowDimension($row)->setRowHeight(24);

        // ---------- Column widths ----------
        $widths = [
            'A' => 13, // Tanggal
            'B' => 15, // Operator
            'C' => 16, // Jam Main
            'D' => 13, // Jumlah Jam
            'E' => 16, // Table
            'F' => 15, // Harga Per-Jam
            'G' => 26, // FNB
            'H' => 8,  // QTY
            'I' => 14, // Harga FNB
            'J' => 17, // Total FNB
            'K' => 17, // Total PS
            'L' => 17, // Total
            'M' => 12, // Pay
            'N' => 15, // Kasir
            'O' => 16, // Cash
            'P' => 16, // QRIS
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // Freeze pane
        $sheet->freezePane("A" . ($headerRow + 1));

        $sheet->getTabColor()->setRGB('0284C7');

        // ---------- Sheet 2: Rekap Member Cubic ----------
        $memberSheet = $spreadsheet->createSheet();
        $memberSheet->setTitle('Rekap Member');

        $allMembers = \App\Models\Member::with(['deposits'])->orderBy('member_number')->get();

        $memberSheet->setCellValue('A2', 'REKAPITULASI MEMBER CUBIC');
        $memberSheet->mergeCells('A2:H2');
        $memberSheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'D97706']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $mHeaderRow = 4;
        $mHeaders = [
            'A' => 'NO. MEMBER',
            'B' => 'NO. TELP',
            'C' => 'NAMA',
            'D' => 'TIPE ROOM',
            'E' => 'TOTAL DEPOSIT',
            'F' => 'METODE',
            'G' => 'SISA JAM',
            'H' => 'STATUS',
        ];
        foreach ($mHeaders as $col => $title) {
            $memberSheet->setCellValue("{$col}{$mHeaderRow}", $title);
        }
        $memberSheet->getStyle("A{$mHeaderRow}:H{$mHeaderRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D97706']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'B45309']]],
        ]);
        $memberSheet->getRowDimension($mHeaderRow)->setRowHeight(26);

        $mRow = $mHeaderRow + 1;
        $totalMemberDeposit = 0;
        foreach ($allMembers as $mIdx => $m) {
            $depositSum = (float) $m->deposits->sum('amount');
            $totalMemberDeposit += $depositSum;
            $payMethod = $m->deposits->first()?->payment_method ? strtoupper($m->deposits->first()->payment_method) : '-';
            $sisaJamText = floor($m->remaining_minutes / 60) . 'j ' . ($m->remaining_minutes % 60) . 'm';

            $mFill = $mIdx % 2 === 0 ? $normFill : ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']];

            $memberSheet->setCellValue("A{$mRow}", $m->member_number);
            $memberSheet->setCellValue("B{$mRow}", $m->phone ?? '-');
            $memberSheet->setCellValue("C{$mRow}", $m->name);
            $memberSheet->setCellValue("D{$mRow}", strtoupper($m->console_type));
            $memberSheet->setCellValue("E{$mRow}", $depositSum);
            $memberSheet->setCellValue("F{$mRow}", $payMethod);
            $memberSheet->setCellValue("G{$mRow}", $sisaJamText);
            $memberSheet->setCellValue("H{$mRow}", $m->status === 'active' ? 'AKTIF' : 'HABIS');

            $memberSheet->getStyle("A{$mRow}:H{$mRow}")->applyFromArray(array_merge(['font' => $fontColor, 'fill' => $mFill], $borderStyle));
            $memberSheet->getStyle("A{$mRow}:D{$mRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $memberSheet->getStyle("E{$mRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $memberSheet->getStyle("E{$mRow}")->getNumberFormat()->setFormatCode($numFmt);
            $memberSheet->getStyle("F{$mRow}:H{$mRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $mRow++;
        }

        // Summary row for Member Sheet
        $memberSheet->setCellValue("A{$mRow}", 'TOTAL DEPOSIT MEMBER');
        $memberSheet->mergeCells("A{$mRow}:D{$mRow}");
        $memberSheet->setCellValue("E{$mRow}", (float) $totalMemberDeposit);
        $memberSheet->setCellValue("F{$mRow}", '');
        $memberSheet->setCellValue("G{$mRow}", '');
        $memberSheet->setCellValue("H{$mRow}", '');
        $memberSheet->mergeCells("F{$mRow}:H{$mRow}");
        $memberSheet->getStyle("A{$mRow}:H{$mRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D97706']],
            'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM, 'color' => ['rgb' => 'B45309']]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ]);
        $memberSheet->getStyle("A{$mRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $memberSheet->getStyle("E{$mRow}")->getNumberFormat()->setFormatCode($numFmt);

        $mWidths = ['A' => 16, 'B' => 16, 'C' => 20, 'D' => 14, 'E' => 18, 'F' => 12, 'G' => 14, 'H' => 12];
        foreach ($mWidths as $col => $w) {
            $memberSheet->getColumnDimension($col)->setWidth($w);
        }

        // Set active sheet back to Sheet 1
        $spreadsheet->setActiveSheetIndex(0);

        $fileName = 'pembukuan_transaksi_' . date('Y-m-d_H-i-s') . '.xlsx';

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        $headers = [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control'       => 'max-age=0',
        ];

        return new \Symfony\Component\HttpFoundation\StreamedResponse(
            function () use ($writer) { $writer->save('php://output'); },
            200,
            $headers
        );
    }
}