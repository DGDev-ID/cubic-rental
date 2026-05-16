<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Console extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'type', 'price_per_hour', 'description', 'status'];

    protected $casts = [
        'price_per_hour' => 'decimal:2',
    ];

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'console_games');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function activeRental()
    {
        return $this->hasOne(Rental::class)->where('status', 'running');
    }
}
