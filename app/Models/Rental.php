<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Rental extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_code', 'customer_name', 'console_id', 'employee_id',
        'package_id', 'rental_type', 'status', 'started_at', 'ended_at',
        'scheduled_end_at', 'rental_amount', 'fnb_amount', 'extra_amount',
        'total_amount', 'paid_amount', 'notes',
    ];

    protected $casts = [
        'started_at'      => 'datetime',
        'ended_at'        => 'datetime',
        'scheduled_end_at' => 'datetime',
        'rental_amount'   => 'decimal:2',
        'fnb_amount'      => 'decimal:2',
        'extra_amount'    => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'paid_amount'     => 'decimal:2',
    ];

    public function console(): BelongsTo
    {
        return $this->belongsTo(Console::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(RentalExtension::class);
    }

    public function fnbItems(): HasMany
    {
        return $this->hasMany(RentalFnbItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RentalPayment::class);
    }

    public function getDurationMinutesAttribute(): int
    {
        $end = $this->ended_at ?? now();
        return (int) $this->started_at->diffInMinutes($end);
    }

    public function getRemainingMinutesAttribute(): ?int
    {
        if (!$this->scheduled_end_at) return null;
        $remaining = now()->diffInMinutes($this->scheduled_end_at, false);
        return $remaining;
    }

    public function getIsOvertimeAttribute(): bool
    {
        if (!$this->scheduled_end_at) return false;
        return now()->greaterThan($this->scheduled_end_at);
    }

    public function recalculateTotal(): void
    {
        $this->total_amount = $this->rental_amount + $this->fnb_amount + $this->extra_amount;
        $this->paid_amount  = $this->payments()->sum('amount');
        $this->save();
    }
}
