<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Console;
use App\Models\Employee;
use App\Services\RentalService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function __construct(private RentalService $rentalService) {}

    public function index(Request $request): Response
    {
        $query = Reservation::with(['console', 'employee'])
            ->orderBy('reserved_at', 'asc');

        if ($request->status) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ['pending', 'confirmed']);
        }

        if ($request->date) {
            $query->whereDate('reserved_at', $request->date);
        }

        return Inertia::render('Reservations/Index', [
            'reservations' => $query->get(),
            'consoles'     => Console::orderBy('name')->get(),
            'employees'    => Employee::where('status', 'active')->orderBy('name')->get(),
            'filters'      => $request->only(['status', 'date']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'console_id'     => 'required|exists:consoles,id',
            'employee_id'    => 'required|exists:employees,id',
            'customer_name'  => 'required|string|max:100',
            'customer_phone' => 'nullable|string|max:20',
            'reserved_at'    => 'required|date',
            'duration_hours' => 'nullable|numeric|min:0.5|max:24',
            'notes'          => 'nullable|string|max:500',
        ]);

        Reservation::create($data + ['status' => 'pending']);
        return back()->with('success', 'Reservasi berhasil ditambahkan.');
    }

    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $data = $request->validate([
            'console_id'     => 'required|exists:consoles,id',
            'employee_id'    => 'required|exists:employees,id',
            'customer_name'  => 'required|string|max:100',
            'customer_phone' => 'nullable|string|max:20',
            'reserved_at'    => 'required|date',
            'duration_hours' => 'nullable|numeric|min:0.5|max:24',
            'notes'          => 'nullable|string|max:500',
            'status'         => 'required|in:pending,confirmed,cancelled',
        ]);

        $reservation->update($data);
        return back()->with('success', 'Reservasi berhasil diperbarui.');
    }

    public function destroy(Reservation $reservation): RedirectResponse
    {
        $reservation->delete();
        return back()->with('success', 'Reservasi dihapus.');
    }

    public function convert(Reservation $reservation): RedirectResponse
    {
        if ($reservation->status === 'converted') {
            return back()->with('error', 'Reservasi sudah dikonversi.');
        }

        $rental = $this->rentalService->create([
            'console_id'     => $reservation->console_id,
            'employee_id'    => $reservation->employee_id,
            'customer_name'  => $reservation->customer_name,
            'duration_hours' => $reservation->duration_hours,
            'notes'          => $reservation->notes,
        ]);

        $reservation->update(['status' => 'converted', 'rental_id' => $rental->id]);

        return redirect()->route('rentals.show', $rental)->with('success', 'Reservasi berhasil dikonversi ke rental.');
    }
}
