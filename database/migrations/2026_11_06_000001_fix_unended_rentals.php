<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Rental;
use App\Models\Console;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Perbaiki rental yang statusnya 'paid' tapi ended_at masih NULL (contoh: VIP 3)
        // Set ended_at ke scheduled_end_at jika ada, atau started_at + durasi default
        $paidUnendedRentals = Rental::whereNull('ended_at')
            ->where('status', 'paid')
            ->get();

        foreach ($paidUnendedRentals as $rental) {
            $endedAt = $rental->scheduled_end_at;
            if (!$endedAt) {
                // Jika tidak ada scheduled_end_at, hitung dari payments atau started_at
                $endedAt = Carbon::parse($rental->started_at)->addHour();
            }
            $rental->update(['ended_at' => $endedAt]);

            // Jika console masih occupied, bebaskan console
            if ($rental->console && $rental->console->status === 'occupied') {
                $rental->console->update(['status' => 'available']);
            }
        }

        // 2. Perbaiki rental yang statusnya 'half_paid' tapi ended_at masih NULL
        // Karena console tersebut sebenarnya masih aktif bermain di room monitor
        Rental::whereNull('ended_at')
            ->where('status', 'half_paid')
            ->update(['status' => 'running']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed
    }
};

