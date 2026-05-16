<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('genre')->nullable();
            $table->boolean('is_multiplayer')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('console_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('console_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('console_games');
        Schema::dropIfExists('games');
    }
};
