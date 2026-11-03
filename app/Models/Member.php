<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Member extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'member_number', 'name', 'phone', 'console_type',
        'remaining_minutes', 'status',
    ];

    public function deposits(): HasMany
    {
        return $this->hasMany(MemberDeposit::class)->orderByDesc('created_at');
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class)->orderByDesc('started_at');
    }

    /** Tambahkan kuota dari deposit, update status */
    public function creditMinutes(int $minutes): void
    {
        $this->increment('remaining_minutes', $minutes);
        $this->update(['status' => 'active']);
    }

    /** Kurangi kuota saat sesi bermain, kembalikan sisa kuota terpakai */
    public function deductMinutes(int $minutes): int
    {
        $deducted = min($this->remaining_minutes, $minutes);
        $this->decrement('remaining_minutes', $deducted);
        $this->refresh();

        if ($this->remaining_minutes <= 0) {
            $this->update(['status' => 'exhausted']);
        }

        return $deducted;
    }

    public function getSisaJamAttribute(): string
    {
        $h = intdiv($this->remaining_minutes, 60);
        $m = $this->remaining_minutes % 60;
        return $h > 0 ? "{$h}j {$m}m" : "{$m}m";
    }
}

