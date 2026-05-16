<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalPayment extends Model
{
    use HasFactory;

    protected $table = 'rental_payments';

    protected $fillable = ['rental_id', 'method', 'amount', 'notes'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}
