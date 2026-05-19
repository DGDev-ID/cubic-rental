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
        Schema::create('fnb_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('customer_name', 100)->nullable();
            $table->foreignId('employee_id')->constrained();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->enum('payment_method', ['cash', 'qris', 'transfer'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fnb_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fnb_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fnb_item_id')->constrained();
            $table->integer('qty')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->json('addons')->nullable();
            $table->decimal('addons_price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fnb_order_items');
        Schema::dropIfExists('fnb_orders');
    }
};
