<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Member packages (paket deposit)
        Schema::create('member_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // e.g. "Paket Reguler 12 Jam"
            $table->string('console_type');            // reguler | vip | vvip | suite
            $table->unsignedInteger('price');          // 100000
            $table->unsignedInteger('duration_minutes'); // 720 = 12 jam
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Members
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_number')->unique(); // e.g. MB-REG-001
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('console_type');            // tipe console yang diperbolehkan
            $table->unsignedInteger('remaining_minutes')->default(0);
            $table->enum('status', ['active', 'exhausted'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        // Member deposit history
        Schema::create('member_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_package_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->unsignedInteger('amount');         // Rp yang dibayar
            $table->unsignedInteger('minutes_credited'); // jam yang dikreditkan
            $table->string('payment_method');          // cash | qris
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_deposits');
        Schema::dropIfExists('members');
        Schema::dropIfExists('member_packages');
    }
};

