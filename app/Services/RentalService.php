<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\Console;
use App\Models\Member;
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

        // === MEMBER RENTAL ===
        $memberId = !empty($data['member_id']) ? (int) $data['member_id'] : null;
        $isMember = $memberId !== null;
        $initialRentalAmount = 0;

        if ($isMember) {
            $member = Member::findOrFail($memberId);
            if ($member->remaining_minutes <= 0) {
                throw new \Exception("Kuota member {$member->name} sudah habis. Silakan lakukan deposit terlebih dahulu.");
            }
            if (strtolower($console->type) !== strtolower($member->console_type)) {
                throw new \Exception("Console {$console->name} ({$console->type}) tidak sesuai dengan tipe membership member ({$member->console_type}).");
            }
            if (!$durationHours) {
                throw new \Exception("Member wajib memilih durasi bermain.");
            }
            $requestedMin = (int) round($durationHours * 60);
            if ($requestedMin > $member->remaining_minutes) {
                $remText = $member->sisa_jam;
                throw new \Exception("Sisa kuota member hanya {$remText}. Durasi bermain tidak boleh melebihi sisa kuota, silakan perpanjang deposit atau kurangi durasi.");
            }
            $customerName = !empty($data['customer_name']) ? trim($data['customer_name']) : $member->name;
            $initialRentalAmount = 0;
        } else {
            // Non-member: harga dasar sewa durasi tetap sudah pasti di awal
            if ($durationHours) {
                $initialRentalAmount = (float) (round(($console->price_per_hour * $durationHours) / 1000) * 1000);
            }
        }

        $isPayUpfront = !$isMember && !empty($data['pay_upfront']) && $durationHours;

        $rental = Rental::create([
            'transaction_code'       => 'TRX-' . strtoupper(Str::random(8)),
            'customer_name'          => $customerName,
            'console_id'             => $data['console_id'],
            'employee_id'            => $data['employee_id'],
            'package_id'             => null,
            'member_id'              => $memberId,
            'member_deducted_minutes'=> null, // dihitung saat finish
            'rental_type'            => $rentalType,
            'status'                 => 'running',
            'started_at'             => Carbon::now(),
            'scheduled_end_at'       => $scheduledEnd,
            'rental_amount'          => $initialRentalAmount,
            'fnb_amount'             => 0,
            'extra_amount'           => 0,
            'total_amount'           => $initialRentalAmount,
            'paid_amount'            => $isPayUpfront ? $initialRentalAmount : 0,
            'notes'                  => $data['notes'] ?? null,
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

        // Pastikan rental_amount tetap mencerminkan base fee jika sebelumnya masih 0
        if ($rental->rental_amount <= 0 && !$rental->member_id) {
            if ($rental->rental_type === 'duration') {
                $totalExtMinutes = (int) $rental->extensions()->where('id', '!=', $extension->id)->sum('added_minutes');
                $totalSchedMinutes = $rental->started_at && $rental->scheduled_end_at ? $rental->started_at->diffInMinutes($rental->scheduled_end_at) : 0;
                $baseMin = max(0, $totalSchedMinutes - $totalExtMinutes - $addedMinutes);
                if ($baseMin > 0) {
                    $rental->rental_amount = round((($rental->console->price_per_hour / 60) * $baseMin) / 1000) * 1000;
                }
            }
        }

        $rental->extra_amount = (float) $rental->extensions()->sum('additional_price');
        $rental->total_amount = (float) $rental->rental_amount + (float) $rental->fnb_amount + (float) $rental->extra_amount;
        $rental->status = 'running';
        $rental->save();

        return $extension;
    }

    public function addTime(Rental $rental, array $data): RentalExtension
    {
        $addedMinutes = (int) $data['added_minutes'];
        $rate = $rental->console ? (float) $rental->console->price_per_hour : 0;
        $calculatedPrice = (float) (round((($rate / 60) * $addedMinutes) / 1000) * 1000);

        // Jika additional_price dikirim > 0 gunakan nilai tersebut, jika tidak / 0 hitung otomatis dari tarif console
        if (isset($data['additional_price']) && is_numeric($data['additional_price']) && (float) $data['additional_price'] > 0) {
            $additionalPrice = (float) $data['additional_price'];
        } elseif ($rental->member_id) {
            $additionalPrice = 0;
        } else {
            $additionalPrice = $calculatedPrice;
        }

        $extension = RentalExtension::create([
            'rental_id'        => $rental->id,
            'added_minutes'    => $addedMinutes,
            'additional_price' => $additionalPrice,
            'notes'            => $data['notes'] ?? null,
        ]);

        if ($rental->scheduled_end_at) {
            $currentEnd = Carbon::parse($rental->scheduled_end_at);
            // If already expired, start from now
            $baseTime = $currentEnd->isPast() ? Carbon::now() : $currentEnd;
            $rental->scheduled_end_at = $baseTime->addMinutes($addedMinutes);
        }

        $rental->extra_amount = (float) $rental->extensions()->sum('additional_price');
        $rental->total_amount = (float) $rental->rental_amount + (float) $rental->fnb_amount + (float) $rental->extra_amount;
        $rental->save();

        return $extension;
    }

    public function syncFnbAndTotals(Rental $rental): void
    {
        $rental->fnb_amount = (float) $rental->fnbItems()->sum('subtotal');
        $rental->extra_amount = (float) $rental->extensions()->sum('additional_price');
        $rental->total_amount = (float) $rental->rental_amount + (float) $rental->fnb_amount + (float) $rental->extra_amount;

        if ($rental->ended_at) {
            $paidAmount = (float) $rental->payments()->sum('amount');
            $rental->paid_amount = $paidAmount;
            if ($paidAmount >= $rental->total_amount && ($paidAmount > 0 || $rental->total_amount == 0)) {
                $rental->status = 'paid';
            } elseif ($paidAmount > 0) {
                $rental->status = 'half_paid';
            } else {
                $rental->status = 'finished';
            }
        }

        $rental->save();
    }

    public function addFnb(Rental $rental, array $items): void
    {
        foreach ($items as $item) {
            $fnbItem  = FnbItem::findOrFail($item['fnb_item_id']);
            $addonsPrice = collect($item['addons'] ?? [])->sum('price');
            $subtotal    = ($fnbItem->price + $addonsPrice) * $item['qty'];

            RentalFnbItem::create([
                'rental_id'    => $rental->id,
                'fnb_item_id'  => $fnbItem->id,
                'qty'          => $item['qty'],
                'unit_price'   => $fnbItem->price,
                'subtotal'     => $subtotal,
                'addons'       => $item['addons'] ?? null,
                'addons_price' => $addonsPrice * $item['qty'],
            ]);
        }

        $this->syncFnbAndTotals($rental);
    }

    public function updateFnbItem(Rental $rental, RentalFnbItem $fnbItem, array $data): void
    {
        abort_if($fnbItem->rental_id !== $rental->id, 403);

        $item = FnbItem::findOrFail($data['fnb_item_id']);
        $addonsPrice = collect($data['addons'] ?? [])->sum('price');
        $subtotal = ($item->price + $addonsPrice) * $data['qty'];

        $fnbItem->update([
            'fnb_item_id'  => $item->id,
            'qty'          => $data['qty'],
            'unit_price'   => $item->price,
            'addons'       => $data['addons'] ?? null,
            'addons_price' => $addonsPrice * $data['qty'],
            'subtotal'     => $subtotal,
        ]);

        $this->syncFnbAndTotals($rental);
    }

    public function removeFnbItem(Rental $rental, RentalFnbItem $fnbItem): void
    {
        abort_if($fnbItem->rental_id !== $rental->id, 403);
        $fnbItem->delete();
        $this->syncFnbAndTotals($rental);
    }

    public function finish(Rental $rental): Rental
    {
        $endTime = Carbon::now();
        $minutes = $rental->started_at->diffInMinutes($endTime);

        // === MEMBER LOGIC ===
        if ($rental->member_id) {
            $member = Member::find($rental->member_id);
            if ($member) {
                // Potong kuota sesuai durasi yang dijadwalkan
                $scheduledMinutes = $rental->scheduled_end_at
                    ? $rental->started_at->diffInMinutes($rental->scheduled_end_at)
                    : $rental->started_at->diffInMinutes($endTime);

                $deducted = $member->deductMinutes((int)$scheduledMinutes);
                $rental->member_deducted_minutes = $deducted;
            }
            // Rental amount untuk member SELALU Rp 0 (overtime tidak dikenakan biaya)
            $rentalAmount = 0;
            $rental->rental_amount = 0;
            $rental->extra_amount  = (float) $rental->extensions()->sum('additional_price');
            $rental->total_amount  = (float) $rental->fnb_amount + (float) $rental->extra_amount;
        } else {
            // Normal (non-member) rental
            if ($rental->rental_type === 'duration') {
                if ($rental->rental_amount > 0) {
                    $rentalAmount = (float) $rental->rental_amount;
                } else {
                    $totalExtensionMinutes = (int) $rental->extensions()->sum('added_minutes');
                    $totalScheduledMinutes = $rental->scheduled_end_at ? $rental->started_at->diffInMinutes($rental->scheduled_end_at) : 0;
                    $baseMinutes = max(0, $totalScheduledMinutes - $totalExtensionMinutes);
                    $rentalAmount = round((($rental->console->price_per_hour / 60) * $baseMinutes) / 1000) * 1000;
                }
            } else {
                // Open time — round up to nearest 10-min block, minimum 60 min
                $billedMinutes = (int)(ceil(max($minutes, 60) / 10) * 10);
                $rentalAmount = round((($rental->console->price_per_hour / 60) * $billedMinutes) / 1000) * 1000;
            }

            $rental->rental_amount = $rentalAmount;
            $rental->extra_amount  = (float) $rental->extensions()->sum('additional_price');
            $rental->total_amount  = $rentalAmount + (float) $rental->fnb_amount + (float) $rental->extra_amount;
        }

        $paidAmount = (float) $rental->payments()->sum('amount');
        $rental->paid_amount = $paidAmount;
        $rental->ended_at    = $endTime;

        if ($paidAmount >= $rental->total_amount && ($paidAmount > 0 || $rental->total_amount == 0)) {
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

    public function pay(Rental $rental, array $payments, ?int $cashierId = null): void
    {
        if ($cashierId) {
            $rental->cashier_id = $cashierId;
        }

        foreach ($payments as $payment) {
            $rental->payments()->create([
                'method' => $payment['method'],
                'amount' => $payment['amount'],
                'notes'  => $payment['notes'] ?? null,
            ]);
        }

        $paidAmount = (float) $rental->payments()->sum('amount');
        $rental->paid_amount = $paidAmount;

        // Jika rental masih aktif bermain (ended_at belum diisi), status TETAP 'running'!
        // Tidak boleh diubah ke 'paid' atau 'half_paid' sebelum sesi benar-benar selesai (ended_at).
        if ($rental->ended_at === null) {
            $rental->status = 'running';
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
            ->whereNotNull('ended_at')
            ->whereIn('status', ['finished', 'half_paid'])
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
        return Rental::with(['console', 'employee', 'fnbItems.fnbItem', 'payments', 'member'])
            ->whereNull('ended_at')
            ->where('status', '!=', 'cancelled')
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
                $currentRentalAmount = (float) $rental->rental_amount;
                if ($rental->member_id) {
                    $currentRentalAmount = 0;
                } elseif ($rental->rental_type === 'duration') {
                    if ($currentRentalAmount <= 0) {
                        $totalExtensionMinutes = (int) $rental->extensions->sum('added_minutes');
                        $totalScheduledMinutes = $rental->scheduled_end_at ? $rental->started_at->diffInMinutes($rental->scheduled_end_at) : 0;
                        $baseMinutes = max(0, $totalScheduledMinutes - $totalExtensionMinutes);
                        $currentRentalAmount = round((($rental->console->price_per_hour / 60) * $baseMinutes) / 1000) * 1000;
                    }
                } elseif ($rental->rental_type === 'open_time') {
                    // Pure open time — round up to nearest 10-min block (minimum 60 min)
                    $billedMinutes = (int)(ceil(max($durationMinutes, 60) / 10) * 10);
                    $currentRentalAmount = round((($rental->console->price_per_hour / 60) * $billedMinutes) / 1000) * 1000;
                }

                $paid = (float) $rental->payments->sum('amount');
                $currentTotal = $currentRentalAmount + (float) $rental->fnb_amount + (float) $rental->extra_amount;
                $isPaid = $paid >= $currentTotal && ($paid > 0 || $rental->member_id);

                return array_merge($rental->toArray(), [
                    'duration_minutes'     => $durationMinutes,
                    'remaining_minutes'    => $remainingMinutes,
                    'live_status'          => $status,
                    'current_total'        => $currentTotal,
                    'is_fixed_duration'    => !is_null($rental->scheduled_end_at),
                    'fixed_rental_amount'  => $currentRentalAmount,
                    'is_dp'                => $paid > 0 && !$isPaid,
                    'is_paid'              => $isPaid,
                    'paid_amount'          => $paid,
                ]);
            });
    }
}
