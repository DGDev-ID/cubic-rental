<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE rentals DROP CONSTRAINT IF EXISTS rentals_status_check");
        DB::statement("ALTER TABLE rentals ADD CONSTRAINT rentals_status_check CHECK (status IN ('running','finished','paid','half_paid','cancelled'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE rentals DROP CONSTRAINT IF EXISTS rentals_status_check");
        DB::statement("ALTER TABLE rentals ADD CONSTRAINT rentals_status_check CHECK (status IN ('running','finished','cancelled'))");
    }
};
