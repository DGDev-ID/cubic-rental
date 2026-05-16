<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fnb_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->default('food'); // food, drink, snack
            $table->decimal('price', 10, 2);
            $table->integer('stock')->default(0);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fnb_addons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fnb_addons');
        Schema::dropIfExists('fnb_items');
    }
};
