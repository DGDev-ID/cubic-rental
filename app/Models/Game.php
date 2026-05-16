<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Game extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'genre', 'is_multiplayer'];

    protected $casts = [
        'is_multiplayer' => 'boolean',
    ];

    public function consoles(): BelongsToMany
    {
        return $this->belongsToMany(Console::class, 'console_games');
    }
}
