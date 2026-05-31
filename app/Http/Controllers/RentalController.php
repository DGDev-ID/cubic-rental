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
        // Upcoming reservations per console (pending/confirmed, belum lewat)
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
        // For running rentals, use current estimated total
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

        // If paid mid-session (DP), go back to show page
        if ($rental->status === 'half_paid' && $rental->ended_at === null) {
            return redirect()->route('rentals.show', $rental)->with('success', 'DP berhasil dicatat.');
        }

        // If still half_paid after finish (partial), back to payment
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

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('transaction_code', 'ilike', "%{$request->search}%")
                  ->orWhere('customer_name', 'ilike', "%{$request->search}%");
            });
        }
        if ($request->date) {
            $query->whereDate('started_at', $request->date);
        }
        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->console_id) {
            $query->where('console_id', $request->console_id);
        }
        if ($request->payment_method) {
            $query->whereHas('payments', fn($q) => $q->where('method', $request->payment_method));
        }

        return Inertia::render('Rentals/History', [
            'rentals'   => $query->orderByDesc('started_at')->paginate(20)->withQueryString(),
            'employees' => Employee::orderBy('name')->get(),
            'consoles'  => Console::orderBy('name')->get(),
            'filters'   => $request->only(['search', 'date', 'employee_id', 'console_id', 'payment_method']),
        ]);
    }

    public function exportExcel(Request $request)
    {
        $query = Rental::with(['console', 'employee', 'payments'])
            ->whereIn('status', ['finished', 'paid', 'half_paid', 'cancelled']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('transaction_code', 'ilike', "%{$request->search}%")
                  ->orWhere('customer_name', 'ilike', "%{$request->search}%");
            });
        }
        if ($request->date) {
            $query->whereDate('started_at', $request->date);
        }
        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->console_id) {
            $query->where('console_id', $request->console_id);
        }
        if ($request->payment_method) {
            $query->whereHas('payments', fn($q) => $q->where('method', $request->payment_method));
        }

        $rentals = $query->orderByDesc('started_at')->get();

        $fileName = 'riwayat_transaksi_' . date('Y-m-d_H-i-s') . '.csv';

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($rentals) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF"); // Add BOM for UTF-8 Excel support
            fputcsv($file, ['Kode Transaksi', 'Customer', 'Console', 'Jenis', 'Mulai', 'Selesai', 'Status', 'Metode Bayar', 'Subtotal Rental', 'FNB', 'Extra', 'Total', 'Dibayar']);

            foreach ($rentals as $r) {
                $methods = $r->payments->pluck('method')->join(', ');
                fputcsv($file, [
                    $r->transaction_code,
                    $r->customer_name,
                    $r->console->name ?? '-',
                    $r->rental_type,
                    $r->started_at ? $r->started_at->format('Y-m-d H:i') : '-',
                    $r->ended_at ? $r->ended_at->format('Y-m-d H:i') : '-',
                    $r->status,
                    $methods,
                    $r->rental_amount,
                    $r->fnb_amount,
                    $r->extra_amount,
                    $r->total_amount,
                    $r->paid_amount,
                ]);
            }

            fclose($file);
        };

        return new \Symfony\Component\HttpFoundation\StreamedResponse($callback, 200, $headers);
    }
}
