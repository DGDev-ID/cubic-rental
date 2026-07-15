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

        // Yearly stats (all available years)
        $yRentals = Rental::whereIn('status', ['finished', 'paid', 'half_paid'])
            ->selectRaw('EXTRACT(YEAR FROM started_at) as year, SUM(total_amount) as revenue, COUNT(*) as customers')
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
            'year'      => (int) $yr,
            'revenue'   => (float) (($yRentals->get($yr)?->revenue ?? 0) + ($yFnb->get($yr)?->revenue ?? 0)),
            'customers' => (int) (($yRentals->get($yr)?->customers ?? 0) + ($yFnb->get($yr)?->customers ?? 0)),
        ])->values();

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

        // Daily stats for the selected year+month
        $statsMonth = (int) ($request->stats_month ?? now()->month);
        $daysInMonth = \Carbon\Carbon::create($statsYear, $statsMonth)->daysInMonth;
        $dRentals = Rental::whereIn('status', ['finished', 'paid', 'half_paid'])
            ->whereYear('started_at', $statsYear)
            ->whereMonth('started_at', $statsMonth)
            ->selectRaw('EXTRACT(DAY FROM started_at) as day, SUM(total_amount) as revenue, COUNT(*) as customers')
            ->groupByRaw('EXTRACT(DAY FROM started_at)')
            ->get()->keyBy(fn($r) => (int) $r->day);
        $dFnb = \App\Models\FnbOrder::where('status', 'paid')
            ->whereYear('paid_at', $statsYear)
            ->whereMonth('paid_at', $statsMonth)
            ->selectRaw('EXTRACT(DAY FROM paid_at) as day, SUM(total_amount) as revenue, COUNT(*) as customers')
            ->groupByRaw('EXTRACT(DAY FROM paid_at)')
            ->get()->keyBy(fn($r) => (int) $r->day);
        $dailyStats = collect(range(1, $daysInMonth))->map(fn($d) => [
            'day'       => $d,
            'revenue'   => (float) (($dRentals->get($d)?->revenue ?? 0) + ($dFnb->get($d)?->revenue ?? 0)),
            'customers' => (int) (($dRentals->get($d)?->customers ?? 0) + ($dFnb->get($d)?->customers ?? 0)),
        ])->values();

        return Inertia::render('Rentals/History', [
            'rentals'           => $query->orderByDesc('started_at')->paginate(20)->withQueryString(),
            'fnb_orders'        => $fnbQuery->orderByDesc('paid_at')->paginate(20, ['*'], 'fnb_page')->withQueryString(),
            'employees'         => Employee::orderBy('name')->get(),
            'consoles'          => Console::orderBy('name')->get(),
            'filters'           => $request->only(['search', 'date', 'month', 'year', 'employee_id', 'console_id', 'payment_method']),
            'today_revenue'     => (float) $todayRevenue,
            'today_customers'   => (int) $todayCustomers,
            'summary_revenue'   => (float) $summaryRevenue,
            'summary_customers' => (int) $summaryCustomers,
            'yearly_stats'      => $yearlyStats,
            'monthly_stats'     => $monthlyStats,
            'daily_stats'       => $dailyStats,
            'stats_year'        => $statsYear,
            'stats_month'       => $statsMonth,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = Rental::with(['console', 'employee'])
            ->whereIn('status', ['finished', 'paid', 'half_paid']);

        $fnbQuery = \App\Models\FnbOrder::with(['console', 'employee'])
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

        $rentals   = $query->orderByDesc('started_at')->get();
        $fnbOrders = $fnbQuery->orderByDesc('paid_at')->get();

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

        // ---------- Column headers ----------
        $headers = ['Tanggal', 'Kode Transaksi', 'Customer', 'Console', 'Operator', 'Total FnB (Rp)', 'Total Rental (Rp)', 'Total Semua (Rp)'];
        $colCount = count($headers);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        // ---------- Header branding (merged exactly to table width) ----------
        $sheet->setCellValue('A1', config('app.name', 'Rental PS'));
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '0C2D5A']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A2', 'Riwayat Transaksi – ' . $periodLabel);
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1155A0']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('A3', 'Diekspor pada: ' . now()->format('d/m/Y H:i:s'));
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle('A3')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '888888']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT],
        ]);

        $headerRow = 5;
        foreach ($headers as $i => $h) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue("{$col}{$headerRow}", $h);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '0284C7']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '38BDF8']]],
        ]);

        $row = $headerRow + 1;
        $totalFnb = $totalRental = $totalSemua = 0;
        $totalCustomers = 0;

        $numFmt = '#,##0';
        $altFill = ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D1F35']];
        $normFill = ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '081729']];
        $fontColor = ['color' => ['rgb' => 'E2E8F0']];
        $amountColor = ['color' => ['rgb' => '7DD3FC']];
        $borderStyle = ['borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => '0D3B5E']]]];

        foreach ($rentals as $idx => $r) {
            $totalFnb    += $r->fnb_amount;
            $totalRental += $r->rental_amount;
            $totalSemua  += $r->total_amount;
            $totalCustomers++;

            $fill = $idx % 2 === 0 ? $normFill : $altFill;
            $sheet->setCellValue("A{$row}", $r->started_at?->format('d/m/Y') ?? '-');
            $sheet->setCellValue("B{$row}", $r->transaction_code ?? '-');
            $sheet->setCellValue("C{$row}", $r->customer_name ?? '-');
            $sheet->setCellValue("D{$row}", $r->console->name ?? '-');
            $sheet->setCellValue("E{$row}", $r->employee->name ?? '-');
            $sheet->setCellValue("F{$row}", (float) $r->fnb_amount);
            $sheet->setCellValue("G{$row}", (float) $r->rental_amount);
            $sheet->setCellValue("H{$row}", (float) $r->total_amount);

            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray(array_merge(['font' => $fontColor, 'fill' => $fill], $borderStyle));
            $sheet->getStyle("F{$row}:H{$row}")->applyFromArray(['font' => $amountColor]);
            $sheet->getStyle("F{$row}:H{$row}")->getNumberFormat()->setFormatCode($numFmt);
            $row++;
        }

        foreach ($fnbOrders as $idx => $f) {
            $totalFnb   += $f->total_amount;
            $totalSemua += $f->total_amount;
            $totalCustomers++;

            $fill = ($idx + count($rentals)) % 2 === 0 ? $normFill : $altFill;
            $sheet->setCellValue("A{$row}", $f->paid_at?->format('d/m/Y') ?? '-');
            $sheet->setCellValue("B{$row}", $f->code ?? '-');
            $sheet->setCellValue("C{$row}", $f->customer_name ?? '-');
            $sheet->setCellValue("D{$row}", $f->console->name ?? '-');
            $sheet->setCellValue("E{$row}", $f->employee->name ?? '-');
            $sheet->setCellValue("F{$row}", (float) $f->total_amount);
            $sheet->setCellValue("G{$row}", 0);
            $sheet->setCellValue("H{$row}", (float) $f->total_amount);

            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray(array_merge(['font' => $fontColor, 'fill' => $fill], $borderStyle));
            $sheet->getStyle("F{$row}:H{$row}")->applyFromArray(['font' => $amountColor]);
            $sheet->getStyle("F{$row}:H{$row}")->getNumberFormat()->setFormatCode($numFmt);
            $row++;
        }

        // ---------- Summary row ----------
        $row++;
        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->setCellValue("C{$row}", $totalCustomers . ' customer');
        $sheet->setCellValue("F{$row}", (float) $totalFnb);
        $sheet->setCellValue("G{$row}", (float) $totalRental);
        $sheet->setCellValue("H{$row}", (float) $totalSemua);
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '0C3B6E']],
            'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM, 'color' => ['rgb' => '38BDF8']]],
        ]);
        $sheet->getStyle("F{$row}:H{$row}")->getNumberFormat()->setFormatCode($numFmt);

        // ---------- Column widths ----------
        $widths = [14, 20, 22, 18, 18, 18, 18, 18];
        foreach ($widths as $ci => $w) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($ci + 1);
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // Fix row heights
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // Freeze header
        $sheet->freezePane("A{$headerRow}");

        // Tab color
        $sheet->getTabColor()->setRGB('0284C7');

        $fileName = 'riwayat_transaksi_' . date('Y-m-d_H-i-s') . '.xlsx';

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