<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Rental;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Perbaiki rental non-member dengan penambahan waktu (extensions) yang terhitung dobel
        $rentals = Rental::with(['extensions', 'console', 'payments'])
            ->whereNull('member_id')
            ->whereHas('extensions')
            ->get();

        foreach ($rentals as $rental) {
            $console = $rental->console;
            if (!$console || (float) $console->price_per_hour <= 0) {
                continue;
            }

            $rate = (float) $console->price_per_hour;
            $totalExtMinutes = (int) $rental->extensions->sum('added_minutes');
            $totalExtPrice = (float) $rental->extensions->sum('additional_price');

            // Hitung total durasi bermain
            $endTime = $rental->ended_at ?: ($rental->scheduled_end_at ?: null);
            if (!$endTime || !$rental->started_at) {
                continue;
            }

            $totalMinutes = Carbon::parse($rental->started_at)->diffInMinutes(Carbon::parse($endTime));
            
            // Base minutes seharusnya totalMinutes dikurangi extension minutes
            $baseMinutes = max(0, $totalMinutes - $totalExtMinutes);
            $correctBaseRental = round((($rate / 60) * $baseMinutes) / 1000) * 1000;

            // Jika rental_amount saat ini lebih besar dari correctBaseRental
            if ((float) $rental->rental_amount > $correctBaseRental && $correctBaseRental > 0) {
                $rental->rental_amount = $correctBaseRental;
                $rental->extra_amount  = $totalExtPrice;
                $rental->total_amount  = $correctBaseRental + (float) $rental->fnb_amount + $totalExtPrice;

                $paid = (float) $rental->payments->sum('amount');
                $rental->paid_amount = $paid;
                if ($rental->ended_at) {
                    if ($paid >= $rental->total_amount && ($paid > 0 || $rental->total_amount == 0)) {
                        $rental->status = 'paid';
                    } elseif ($paid > 0) {
                        $rental->status = 'half_paid';
                    } else {
                        $rental->status = 'finished';
                    }
                }

                $rental->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed
    }
};
