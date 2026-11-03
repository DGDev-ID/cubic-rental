<?php

namespace Database\Seeders;

use App\Models\MemberPackage;
use Illuminate\Database\Seeder;

class MemberPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['name' => 'Paket Reguler 12 Jam',  'console_type' => 'regular', 'price' => 100000, 'duration_minutes' => 720],
            ['name' => 'Paket VIP 12 Jam',       'console_type' => 'vip',     'price' => 150000, 'duration_minutes' => 720],
            ['name' => 'Paket VVIP 12 Jam',      'console_type' => 'vvip',    'price' => 250000, 'duration_minutes' => 720],
            ['name' => 'Paket Suite 12 Jam',     'console_type' => 'suite',   'price' => 350000, 'duration_minutes' => 720],
        ];

        foreach ($packages as $pkg) {
            MemberPackage::firstOrCreate(
                ['name' => $pkg['name']],
                $pkg
            );
        }
    }
}

