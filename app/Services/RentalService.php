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

        if (!empty($data['duration_hours'])) {
            $scheduledEnd = Carbon::now()->addMinutes((int) round($data['duration_hours'] * 60));
        }

        $rental = Rental::create([
            'transaction_code' => 'TRX-' . strtoupper(Str::random(8)),
            'customer_name'    => $data['customer_name'],
            'console_id'       => $data['console_id'],
            'employee_id'      => $data['employee_id'],
            'package_id'       => null,
            'rental_type'      => 'open_time',
            'status'           => 'running',
            'started_at'       => Carbon::now(),
            'scheduled_end_at' => $scheduledEnd,
            'rental_amount'    => 0,
            'fnb_amount'       => 0,
            'extra_amount'     => 0,
            'total_amount'     => 0,
            'paid_amount'      => 0,
            'notes'            => $data['notes'] ?? null,
        ]);

        $console->update(['status' => 'occupied']);

        return $rental;
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
            $rental->scheduled_end_at = Carbon::parse($rental->scheduled_end_at)->addMinutes($data['added_minutes']);
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
            // Open time — round up to nearest 30-min block, minimum 30 min
            $billedMinutes = (int)(ceil(max($minutes, 1) / 30) * 30);
        }

        $rentalAmount = round(($rental->console->price_per_hour / 60) * $billedMinutes);

        $rental->rental_amount = $rentalAmount;
        $rental->status        = 'finished';
        $rental->ended_at      = $endTime;
        $rental->total_amount  = $rentalAmount + $rental->fnb_amount + $rental->extra_amount;
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

        $paidAmount = $rental->payments()->sum('amount');
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
                $paid = $rental->payments->sum('amount');
                return array_merge($rental->toArray(), [
                    'paid_amount'      => $paid,
                    'remaining_amount' => max(0, $rental->total_amount - $paid),
                ]);
            });
    }

    public function getActiveRentals()
    {
        return Rental::with(['console', 'employee', 'fnbItems.fnbItem'])
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
                if ($rental->rental_type === 'open_time') {
                    if ($rental->scheduled_end_at) {
                        // Fixed duration booking — price based on the scheduled duration
                        $scheduledMinutes = $rental->started_at->diffInMinutes($rental->scheduled_end_at);
                        $currentRentalAmount = round(($rental->console->price_per_hour / 60) * $scheduledMinutes);
                    } else {
                        // Pure open time — round up to nearest 30-min block (minimum 30 min)
                        $billedMinutes = (int)(ceil(max($durationMinutes, 1) / 30) * 30);
                        $currentRentalAmount = round(($rental->console->price_per_hour / 60) * $billedMinutes);
                    }
                }

                return array_merge($rental->toArray(), [
                    'duration_minutes'     => $durationMinutes,
                    'remaining_minutes'    => $remainingMinutes,
                    'live_status'          => $status,
                    'current_total'        => $currentRentalAmount + $rental->fnb_amount + $rental->extra_amount,
                    'is_fixed_duration'    => !is_null($rental->scheduled_end_at),
                    'fixed_rental_amount'  => $currentRentalAmount,
                    'is_dp'                => $rental->status === 'half_paid',
                    'paid_amount'          => (float) $rental->paid_amount,
                ]);
            });
    }
}
