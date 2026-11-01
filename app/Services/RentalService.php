<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\Console;
use App\Models\RentalExtension;
use App\Models\RentalFnbItem;
use App\Models\FnbItem;
use Carbon\Carbon;
use Illuminate\Support\Str;

class RentalService
{
    public function create(array $data): Rental
    {
        $console = Console::findOrFail($data['console_id']);
        $scheduledEnd = null;
        $rentalType = 'open_time';
        $customerName = !empty($data['customer_name']) ? trim($data['customer_name']) : 'Customer';

        $durationHours = !empty($data['duration_hours']) ? (float) $data['duration_hours'] : null;

        if ($durationHours) {
            $scheduledEnd = Carbon::now()->addMinutes((int) round($durationHours * 60));
            $rentalType = 'duration';
        }

        $isPayUpfront = !empty($data['pay_upfront']) && $durationHours;
        $initialRentalAmount = 0;

        if ($isPayUpfront) {
            $initialRentalAmount = round(($console->price_per_hour * $durationHours) / 1000) * 1000;
        }

        $rental = Rental::create([
            'transaction_code' => 'TRX-' . strtoupper(Str::random(8)),
            'customer_name'    => $customerName,
            'console_id'       => $data['console_id'],
            'employee_id'      => $data['employee_id'],
            'package_id'       => null,
            'rental_type'      => $rentalType,
            'status'           => 'running',
            'started_at'       => Carbon::now(),
            'scheduled_end_at' => $scheduledEnd,
            'rental_amount'    => $initialRentalAmount,
            'fnb_amount'       => 0,
            'extra_amount'     => 0,
            'total_amount'     => $initialRentalAmount,
            'paid_amount'      => $isPayUpfront ? $initialRentalAmount : 0,
            'notes'            => $data['notes'] ?? null,
        ]);

        if ($isPayUpfront) {
            $rental->payments()->create([
                'method' => $data['payment_method'] ?? 'cash',
                'amount' => $initialRentalAmount,
                'notes'  => 'Bayar di awal (Lunas)',
            ]);
        }

        $console->update(['status' => 'occupied']);

        return $rental;
    }

    public function switchConsole(Rental $rental, int $newConsoleId): Rental
    {
        $newConsole = Console::findOrFail($newConsoleId);
        if ($newConsole->status !== 'available' && $newConsole->id !== $rental->console_id) {
            throw new \Exception('Ruangan tujuan sedang tidak tersedia.');
        }

        $oldConsole = $rental->console;
        if ($oldConsole && $oldConsole->id !== $newConsole->id) {
            $oldConsole->update(['status' => 'available']);
        }

        $newConsole->update(['status' => 'occupied']);

        $switchNote = "Pindah dari {$oldConsole->name} ke {$newConsole->name}";
        $existingNotes = $rental->notes ? $rental->notes . " | " : "";

        $rental->update([
            'console_id' => $newConsole->id,
            'notes'      => $existingNotes . $switchNote,
        ]);

        return $rental;
    }

    public function updateNotes(Rental $rental, ?string $notes): void
    {
        $rental->update(['notes' => $notes]);
    }

    public function resumeWithTime(Rental $rental, array $data): RentalExtension
    {
        $addedMinutes = (int) $data['added_minutes'];
        $additionalPrice = (float) (round((($rental->console->price_per_hour / 60) * $addedMinutes) / 1000) * 1000);

        $extension = RentalExtension::create([
            'rental_id'        => $rental->id,
            'added_minutes'    => $addedMinutes,
            'additional_price' => $additionalPrice,
            'notes'            => $data['notes'] ?? 'Lanjut main dari status selesai',
        ]);

        // Aktifkan kembali console
        $rental->console->update(['status' => 'occupied']);

        // Set waktu selesai baru dari saat ini
        $rental->scheduled_end_at = Carbon::now()->addMinutes($addedMinutes);
        $rental->ended_at = null;

        $paid = (float) $rental->payments()->sum('amount');
        $rental->extra_amount = (float) bcadd((string)$rental->extra_amount, (string)$additionalPrice, 2);
        $rental->total_amount = (float) bcadd(bcadd((string)$rental->rental_amount, (string)$rental->fnb_amount, 2), (string)$rental->extra_amount, 2);
        $rental->status = $paid > 0 ? 'half_paid' : 'running';
        $rental->save();

        return $extension;
    }

    public function addTime(Rental $rental, array $data): RentalExtension
    {
        $extension = RentalExtension::create([
            'rental_id'        => $rental->id,
            'added_minutes'    => $data['added_minutes'],
            'additional_price' => $data['additional_price'],
            'notes'            => $data['notes'] ?? null,
        ]);

        if ($rental->scheduled_end_at) {
            $currentEnd = Carbon::parse($rental->scheduled_end_at);
            // If already expired, start from now
            $baseTime = $currentEnd->isPast() ? Carbon::now() : $currentEnd;
            $rental->scheduled_end_at = $baseTime->addMinutes($data['added_minutes']);
        }

        $rental->extra_amount = bcadd($rental->extra_amount, $data['additional_price'], 2);
        $rental->total_amount = bcadd(bcadd($rental->rental_amount, $rental->fnb_amount, 2), $rental->extra_amount, 2);
        $rental->save();

        return $extension;
    }

    public function addFnb(Rental $rental, array $items): void
    {
        $totalFnb = 0;

        foreach ($items as $item) {
            $fnbItem  = FnbItem::findOrFail($item['fnb_item_id']);
            $addonsPrice = collect($item['addons'] ?? [])->sum('price');
            $subtotal    = ($fnbItem->price + $addonsPrice) * $item['qty'];

            RentalFnbItem::create([
                'rental_id'   => $rental->id,
                'fnb_item_id' => $fnbItem->id,
                'qty'         => $item['qty'],
                'unit_price'  => $fnbItem->price,
                'subtotal'    => $subtotal,
                'addons'      => $item['addons'] ?? null,
                'addons_price' => $addonsPrice * $item['qty'],
            ]);

            $totalFnb += $subtotal;
        }

        $rental->fnb_amount = bcadd($rental->fnb_amount, $totalFnb, 2);
        $rental->total_amount = bcadd(bcadd($rental->rental_amount, $rental->fnb_amount, 2), $rental->extra_amount, 2);
        $rental->save();
    }

    public function finish(Rental $rental): Rental
    {
        $endTime = Carbon::now();
        $minutes = $rental->started_at->diffInMinutes($endTime);

        if ($rental->scheduled_end_at) {
            // Fixed duration booking — charge based on scheduled duration
            $billedMinutes = $rental->started_at->diffInMinutes($rental->scheduled_end_at);
        } else {
            // Open time — round up to nearest 10-min block, minimum 60 min
            $billedMinutes = (int)(ceil(max($minutes, 60) / 10) * 10);
        }

        $rentalAmount = round((($rental->console->price_per_hour / 60) * $billedMinutes) / 1000) * 1000;
        $totalAmount  = $rentalAmount + $rental->fnb_amount + $rental->extra_amount;
        $paidAmount   = (float) $rental->payments()->sum('amount');

        $rental->rental_amount = $rentalAmount;
        $rental->total_amount  = $totalAmount;
        $rental->paid_amount   = $paidAmount;
        $rental->ended_at      = $endTime;

        // Jika sudah terbayar lunas (misal bayar di awal), status langsung 'paid' (tidak nyangkut di Menunggu Pembayaran)
        if ($paidAmount >= $totalAmount && $paidAmount > 0) {
            $rental->status = 'paid';
        } elseif ($paidAmount > 0) {
            $rental->status = 'half_paid';
        } else {
            $rental->status = 'finished';
        }

        $rental->save();

        $rental->console->update(['status' => 'available']);

        return $rental;
    }

    public function pay(Rental $rental, array $payments): void
    {
        foreach ($payments as $payment) {
            $rental->payments()->create([
                'method' => $payment['method'],
                'amount' => $payment['amount'],
                'notes'  => $payment['notes'] ?? null,
            ]);
        }

        $paidAmount = (float) $rental->payments()->sum('amount');
        $rental->paid_amount = $paidAmount;

        // Determine status based on how much has been paid and rental state
        if ($rental->status === 'running') {
            // Mid-session partial payment (DP)
            $rental->status = 'half_paid';
        } elseif ($paidAmount >= $rental->total_amount) {
            $rental->status = 'paid';
        } else {
            $rental->status = 'half_paid';
        }

        $rental->save();
    }

    public function getFinishedUnpaidRentals()
    {
        return Rental::with(['console', 'employee', 'payments'])
            ->where(function ($q) {
                // status 'finished' (belum bayar sama sekali)
                $q->where('status', 'finished')
                  // status 'half_paid' yang sudah selesai (ended_at ada, berarti sudah diklik Selesai)
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'half_paid')
                         ->whereNotNull('ended_at');
                  });
            })
            ->orderBy('ended_at', 'desc')
            ->get()
            ->map(function ($rental) {
                $paid = (float) $rental->payments->sum('amount');
                return array_merge($rental->toArray(), [
                    'paid_amount'      => $paid,
                    'remaining_amount' => max(0, $rental->total_amount - $paid),
                ]);
            })
            // Hanya tampilkan yang memang masih memiliki sisa tagihan
            ->filter(fn ($r) => $r['remaining_amount'] > 0)
            ->values();
    }

    public function getActiveRentals()
    {
        return Rental::with(['console', 'employee', 'fnbItems.fnbItem', 'payments'])
            ->where(function ($q) {
                // Masih aktif: status running, ATAU half_paid tapi belum selesai (DP mid-session)
                $q->where('status', 'running')
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'half_paid')
                         ->whereNull('ended_at');
                  });
            })
            ->orderBy('started_at', 'asc')
            ->get()
            ->map(function ($rental) {
                $now = Carbon::now();
                $durationMinutes = $rental->started_at->diffInMinutes($now);
                $remainingMinutes = $rental->scheduled_end_at
                    ? $now->diffInMinutes($rental->scheduled_end_at, false)
                    : null;

                $status = 'running';
                if ($remainingMinutes !== null) {
                    if ($remainingMinutes < 0) $status = 'overtime';
                    elseif ($remainingMinutes <= 15) $status = 'finishing_soon';
                }

                // Calculate current rental amount
                $currentRentalAmount = $rental->rental_amount;
                if ($rental->rental_type === 'open_time' || $rental->rental_type === 'duration') {
                    if ($rental->scheduled_end_at) {
                        // Fixed duration booking — price based on the scheduled duration
                        $scheduledMinutes = $rental->started_at->diffInMinutes($rental->scheduled_end_at);
                        $currentRentalAmount = round((($rental->console->price_per_hour / 60) * $scheduledMinutes) / 1000) * 1000;
                    } else {
                        // Pure open time — round up to nearest 10-min block (minimum 60 min)
                        $billedMinutes = (int)(ceil(max($durationMinutes, 60) / 10) * 10);
                        $currentRentalAmount = round((($rental->console->price_per_hour / 60) * $billedMinutes) / 1000) * 1000;
                    }
                }

                $paid = (float) $rental->payments->sum('amount');
                $currentTotal = $currentRentalAmount + $rental->fnb_amount + $rental->extra_amount;
                $isPaid = $paid >= $currentTotal && $paid > 0;

                return array_merge($rental->toArray(), [
                    'duration_minutes'     => $durationMinutes,
                    'remaining_minutes'    => $remainingMinutes,
                    'live_status'          => $status,
                    'current_total'        => $currentTotal,
                    'is_fixed_duration'    => !is_null($rental->scheduled_end_at),
                    'fixed_rental_amount'  => $currentRentalAmount,
                    'is_dp'                => $rental->status === 'half_paid' && !$isPaid,
                    'is_paid'              => $isPaid,
                    'paid_amount'          => $paid,
                ]);
            });
    }
}
