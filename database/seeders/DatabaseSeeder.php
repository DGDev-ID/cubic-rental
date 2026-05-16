<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Employee;
use App\Models\Console;
use App\Models\Game;
use App\Models\Package;
use App\Models\FnbItem;
use App\Models\FnbAddon;
use App\Models\Rental;
use App\Models\RentalPayment;
use App\Models\CashOutbound;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Superadmin
        User::updateOrCreate(
            ['email' => 'admin@rentalps.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('password')]
        );

        // Employees
        Employee::insert([
            ['name' => 'Budi Santoso', 'phone' => '08111111111', 'address' => 'Jl. Merdeka No.1',   'status' => 'active',   'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Rina Dewi',    'phone' => '08222222222', 'address' => 'Jl. Sudirman No.5',  'status' => 'active',   'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Andi Wijaya',  'phone' => '08333333333', 'address' => 'Jl. Gatot Subroto',  'status' => 'active',   'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Sari Putri',   'phone' => '08444444444', 'address' => 'Jl. Ahmad Yani',     'status' => 'inactive', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Games
        $gameData = [
            ['name' => 'FIFA 24',               'genre' => 'Sports',   'is_multiplayer' => true],
            ['name' => 'GTA V',                 'genre' => 'Action',   'is_multiplayer' => false],
            ['name' => 'Call of Duty MW2',       'genre' => 'FPS',      'is_multiplayer' => true],
            ['name' => 'PES 2024',              'genre' => 'Sports',   'is_multiplayer' => true],
            ['name' => 'Mortal Kombat 1',        'genre' => 'Fighting', 'is_multiplayer' => true],
            ['name' => 'WWE 2K24',              'genre' => 'Sports',   'is_multiplayer' => true],
            ['name' => 'UFC 5',                 'genre' => 'Sports',   'is_multiplayer' => true],
            ['name' => 'Red Dead Redemption 2', 'genre' => 'RPG',      'is_multiplayer' => false],
        ];
        foreach ($gameData as $g) { Game::create($g); }

        // Consoles
        $consolesData = [
            ['name' => 'PS5 - Room 1',        'type' => 'regular', 'price_per_hour' => 15000, 'description' => 'PlayStation 5 Standard',   'status' => 'available'],
            ['name' => 'PS5 - Room 2',        'type' => 'regular', 'price_per_hour' => 15000, 'description' => 'PlayStation 5 Standard',   'status' => 'available'],
            ['name' => 'PS5 VIP - Room 1',    'type' => 'vip',     'price_per_hour' => 25000, 'description' => 'PS5 + Sofa + AC',          'status' => 'available'],
            ['name' => 'PS5 VIP - Room 2',    'type' => 'vip',     'price_per_hour' => 25000, 'description' => 'PS5 + Sofa + AC',          'status' => 'available'],
            ['name' => 'PS5 VVIP - Suite A',  'type' => 'vvip',    'price_per_hour' => 40000, 'description' => 'PS5 + Surround Sound',     'status' => 'available'],
            ['name' => 'Suite Room - Couple', 'type' => 'suite',   'price_per_hour' => 60000, 'description' => '2x PS5 + Sofa Couple',     'status' => 'available'],
        ];
        foreach ($consolesData as $c) {
            $console = Console::create($c);
            $console->games()->attach(Game::inRandomOrder()->take(4)->pluck('id'));
        }

        // Packages
        Package::insert([
            ['name' => '3 Hours Package',  'duration_hours' => 3, 'price' => 40000,  'description' => 'Hemat 3 jam bermain', 'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Midnight Package', 'duration_hours' => 5, 'price' => 60000,  'description' => 'Main tengah malam',   'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Couple Package',   'duration_hours' => 2, 'price' => 55000,  'description' => '2 orang 2 jam',      'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Family Package',   'duration_hours' => 4, 'price' => 100000, 'description' => '4 orang 4 jam',      'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Weekend Blast',    'duration_hours' => 6, 'price' => 80000,  'description' => 'Spesial weekend',    'is_active' => true,  'created_at' => now(), 'updated_at' => now()],
        ]);

        // FNB Items
        FnbItem::insert([
            ['name' => 'Mie Goreng',     'category' => 'food',  'price' => 15000, 'stock' => 50, 'is_available' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Nasi Goreng',    'category' => 'food',  'price' => 18000, 'stock' => 50, 'is_available' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Roti Bakar',     'category' => 'food',  'price' => 12000, 'stock' => 30, 'is_available' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Kentang Goreng', 'category' => 'snack', 'price' => 10000, 'stock' => 40, 'is_available' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Es Teh Manis',   'category' => 'drink', 'price' => 5000,  'stock' => 99, 'is_available' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Es Jeruk',       'category' => 'drink', 'price' => 7000,  'stock' => 99, 'is_available' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Kopi Hitam',     'category' => 'drink', 'price' => 8000,  'stock' => 99, 'is_available' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Air Mineral',    'category' => 'drink', 'price' => 4000,  'stock' => 99, 'is_available' => true,  'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Snack Biscuit',  'category' => 'snack', 'price' => 5000,  'stock' => 60, 'is_available' => true,  'created_at' => now(), 'updated_at' => now()],
        ]);

        // FNB Add-ons
        FnbAddon::insert([
            ['name' => 'Extra Pedas',  'price' => 2000, 'is_available' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Extra Keju',   'price' => 3000, 'is_available' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Extra Telur',  'price' => 3000, 'is_available' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Tanpa MSG',    'price' => 0,    'is_available' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Es Banyak',    'price' => 0,    'is_available' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Gula Sedikit', 'price' => 0,    'is_available' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Sample completed rentals
        $emp1 = Employee::first();
        for ($i = 0; $i < 5; $i++) {
            $start = Carbon::today()->addHours(rand(8, 18));
            $end   = $start->copy()->addHours(rand(1, 3));
            $diffMinutes = $start->diffInMinutes($end);
            $pricePerHour = 15000;
            $rentalAmt = round(($pricePerHour / 60) * $diffMinutes);
            $fnbAmt    = rand(0, 30000);
            $total     = $rentalAmt + $fnbAmt;
            $rental = Rental::create([
                'transaction_code' => 'TRX-' . strtoupper(uniqid()),
                'customer_name'    => fake()->name(),
                'console_id'       => Console::inRandomOrder()->first()->id,
                'employee_id'      => Employee::where('status', 'active')->inRandomOrder()->first()->id,
                'package_id'       => null,
                'rental_type'      => 'open_time',
                'status'           => 'finished',
                'started_at'       => $start,
                'ended_at'         => $end,
                'rental_amount'    => $rentalAmt,
                'fnb_amount'       => $fnbAmt,
                'extra_amount'     => 0,
                'total_amount'     => $total,
                'paid_amount'      => $total,
                'notes'            => '',
            ]);
            RentalPayment::create([
                'rental_id' => $rental->id,
                'method'    => collect(['cash', 'qris'])->random(),
                'amount'    => $total,
                'notes'     => null,
            ]);
        }

        // Sample cash outbounds
        CashOutbound::insert([
            ['nominal' => 50000,  'notes' => 'Beli sabun cuci piring', 'employee_id' => $emp1->id, 'date' => today(), 'created_at' => now(), 'updated_at' => now()],
            ['nominal' => 100000, 'notes' => 'Beli bahan minuman',     'employee_id' => $emp1->id, 'date' => today(), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
