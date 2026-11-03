<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberDeposit;
use App\Models\MemberPackage;
use Illuminate\Support\Str;

class MemberService
{
    /** Generate nomor member: MB-REG-001, MB-VIP-002, dst */
    public function generateMemberNumber(string $consoleType): string
    {
        $prefix = match (strtolower($consoleType)) {
            'vip'   => 'MB-VIP',
            'vvip'  => 'MB-VVIP',
            'suite' => 'MB-SUITE',
            default => 'MB-REG',
        };

        $latest = Member::withTrashed()
            ->where('member_number', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->first();

        $nextNum = $latest
            ? ((int) last(explode('-', $latest->member_number)) + 1)
            : 1;

        return $prefix . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    }

    /** Daftarkan member baru dengan deposit pertama */
    public function register(array $data): Member
    {
        $package = MemberPackage::findOrFail($data['member_package_id']);

        $member = Member::create([
            'member_number'    => $this->generateMemberNumber($package->console_type),
            'name'             => $data['name'],
            'phone'            => $data['phone'] ?? null,
            'console_type'     => $package->console_type,
            'remaining_minutes'=> $package->duration_minutes,
            'status'           => 'active',
        ]);

        MemberDeposit::create([
            'member_id'         => $member->id,
            'member_package_id' => $package->id,
            'employee_id'       => $data['employee_id'],
            'amount'            => $package->price,
            'minutes_credited'  => $package->duration_minutes,
            'payment_method'    => $data['payment_method'],
            'notes'             => $data['notes'] ?? 'Deposit pertama / registrasi member',
        ]);

        return $member;
    }

    /** Top-up / tambah deposit ke member yang sudah ada */
    public function deposit(Member $member, array $data): MemberDeposit
    {
        $package = MemberPackage::findOrFail($data['member_package_id']);

        // Validate console_type match
        if ($package->console_type !== $member->console_type) {
            throw new \InvalidArgumentException('Paket tidak sesuai dengan tipe console member ini.');
        }

        $deposit = MemberDeposit::create([
            'member_id'         => $member->id,
            'member_package_id' => $package->id,
            'employee_id'       => $data['employee_id'],
            'amount'            => $package->price,
            'minutes_credited'  => $package->duration_minutes,
            'payment_method'    => $data['payment_method'],
            'notes'             => $data['notes'] ?? 'Top-up deposit',
        ]);

        $member->creditMinutes($package->duration_minutes);

        return $deposit;
    }

    /**
     * Hitung berapa menit yang bisa dipotong dari member untuk sesi rental.
     * Returns: ['deducted' => int, 'overtime_minutes' => int]
     */
    public function calculateDeduction(Member $member, int $requestedMinutes): array
    {
        $deducted = min($member->remaining_minutes, $requestedMinutes);
        $overtimeMinutes = max(0, $requestedMinutes - $deducted);
        return ['deducted' => $deducted, 'overtime_minutes' => $overtimeMinutes];
    }
}

