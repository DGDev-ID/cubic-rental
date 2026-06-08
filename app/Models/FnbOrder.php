<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FnbOrder extends Model
{
    protected $fillable = [
        'code', 'customer_name', 'employee_id', 'console_id',
        'total_amount', 'status', 'payment_method', 'notes', 'paid_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_at'      => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function console(): BelongsTo
    {
        return $this->belongsTo(Console::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FnbOrderItem::class);
    }
}
