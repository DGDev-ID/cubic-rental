<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->foreignId('cashier_id')->nullable()->constrained('employees')->nullOnDelete()->after('employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropForeign(['cashier_id']);
            $table->dropColumn('cashier_id');
        });
    }
};

