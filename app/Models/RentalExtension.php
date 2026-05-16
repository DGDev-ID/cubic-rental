<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalExtension extends Model
{
    use HasFactory;

    protected $fillable = ['rental_id', 'added_minutes', 'additional_price', 'notes'];

    protected $casts = [
        'additional_price' => 'decimal:2',
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}
