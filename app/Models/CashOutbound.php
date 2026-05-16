<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashOutbound extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['nominal', 'notes', 'employee_id', 'date'];

    protected $casts = [
        'nominal' => 'decimal:2',
        'date'    => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
