<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fnb_orders', function (Blueprint $table) {
            $table->foreignId('console_id')->nullable()->constrained()->nullOnDelete()->after('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fnb_orders', function (Blueprint $table) {
            $table->dropForeign(['console_id']);
            $table->dropColumn('console_id');
        });
    }
};
