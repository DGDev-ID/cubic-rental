<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'phone', 'address', 'status'];

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function cashOutbounds(): HasMany
    {
        return $this->hasMany(CashOutbound::class);
    }
}
