<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->integer('added_minutes');
            $table->decimal('additional_price', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rental_fnb_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fnb_item_id')->constrained();
            $table->integer('qty')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->json('addons')->nullable(); // [{addon_id, name, price}]
            $table->decimal('addons_price', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('rental_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['cash', 'qris', 'split', 'half_paid'])->default('cash');
            $table->decimal('amount', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_outbounds', function (Blueprint $table) {
            $table->id();
            $table->decimal('nominal', 10, 2);
            $table->text('notes');
            $table->foreignId('employee_id')->constrained();
            $table->date('date');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_outbounds');
        Schema::dropIfExists('rental_payments');
        Schema::dropIfExists('rental_fnb_items');
        Schema::dropIfExists('rental_extensions');
    }
};
