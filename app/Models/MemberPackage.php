<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberPackage extends Model
{
    protected $fillable = [
        'name', 'console_type', 'price', 'duration_minutes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function deposits(): HasMany
    {
        return $this->hasMany(MemberDeposit::class);
    }

    public function getDurationHoursAttribute(): float
    {
        return round($this->duration_minutes / 60, 1);
    }
}

