<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\RentalExtension;
use App\Models\Rental;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Temukan semua extension non-member yang additional_price <= 0 tapi added_minutes > 0
        $extensions = RentalExtension::with(['rental.console'])
            ->where('additional_price', '<=', 0)
            ->where('added_minutes', '>', 0)
            ->get();

        $affectedRentalIds = [];

        foreach ($extensions as $ext) {
            $rental = $ext->rental;
            if (!$rental || $rental->member_id) {
                continue; // Member kuota/waktu tidak dikenakan tarif rupiah
            }

            $rate = $rental->console ? (float) $rental->console->price_per_hour : 0;
            if ($rate <= 0) {
                continue;
            }

            $price = (float) (round((($rate / 60) * $ext->added_minutes) / 1000) * 1000);
            $ext->update(['additional_price' => $price]);
            $affectedRentalIds[] = $rental->id;
        }

        foreach (array_unique($affectedRentalIds) as $rentalId) {
            $rental = Rental::with(['extensions', 'payments'])->find($rentalId);
            if (!$rental) continue;

            $rental->extra_amount = (float) $rental->extensions()->sum('additional_price');
            $rental->total_amount = (float) $rental->rental_amount + (float) $rental->fnb_amount + (float) $rental->extra_amount;

            $paid = (float) $rental->payments()->sum('amount');
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed
    }
};
