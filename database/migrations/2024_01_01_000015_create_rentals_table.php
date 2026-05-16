<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->unique();
            $table->string('customer_name');
            $table->foreignId('console_id')->constrained();
            $table->foreignId('employee_id')->constrained();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('rental_type', ['open_time', 'package'])->default('open_time');
            $table->enum('status', ['running', 'finished', 'cancelled'])->default('running');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable(); // for package
            $table->decimal('rental_amount', 10, 2)->default(0);
            $table->decimal('fnb_amount', 10, 2)->default(0);
            $table->decimal('extra_amount', 10, 2)->default(0); // overtime/extensions
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
