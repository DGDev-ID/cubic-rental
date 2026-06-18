<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE rentals DROP CONSTRAINT IF EXISTS rentals_rental_type_check");
        DB::statement("ALTER TABLE rentals ADD CONSTRAINT rentals_rental_type_check CHECK (rental_type::text IN ('open_time', 'package', 'duration'))");

        DB::statement("UPDATE rentals SET rental_type = 'duration' WHERE rental_type = 'open_time' AND scheduled_end_at IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE rentals SET rental_type = 'open_time' WHERE rental_type = 'duration'");

        DB::statement("ALTER TABLE rentals DROP CONSTRAINT IF EXISTS rentals_rental_type_check");
        DB::statement("ALTER TABLE rentals ADD CONSTRAINT rentals_rental_type_check CHECK (rental_type::text IN ('open_time', 'package'))");
    }
};
