<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'console_id', 'employee_id', 'customer_name', 'customer_phone',
        'reserved_at', 'duration_hours', 'notes', 'status', 'rental_id',
    ];

    protected $casts = [
        'reserved_at'   => 'datetime',
        'duration_hours' => 'float',
    ];

    public function console(): BelongsTo  { return $this->belongsTo(Console::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function rental(): BelongsTo   { return $this->belongsTo(Rental::class); }
}
