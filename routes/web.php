<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ConsoleController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\FnbController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\CashOutboundController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FnbOrderController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('dashboard'));

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/active-rentals', [DashboardController::class, 'activeRentals'])->name('dashboard.active-rentals');
    Route::get('/room-monitor', [DashboardController::class, 'roomMonitor'])->name('room-monitor');

    // Employees
    Route::resource('employees', EmployeeController::class)->except(['create', 'edit', 'show']);
    Route::post('employees/{id}/restore', [EmployeeController::class, 'restore'])->name('employees.restore');

    // Consoles
    Route::resource('consoles', ConsoleController::class)->except(['create', 'edit', 'show']);

    // Games
    Route::resource('games', GameController::class)->except(['create', 'edit', 'show']);

    // FNB
    Route::get('/fnb', [FnbController::class, 'index'])->name('fnb.index');
    Route::post('/fnb/items', [FnbController::class, 'storeItem'])->name('fnb.items.store');
    Route::put('/fnb/items/{fnbItem}', [FnbController::class, 'updateItem'])->name('fnb.items.update');
    Route::delete('/fnb/items/{fnbItem}', [FnbController::class, 'destroyItem'])->name('fnb.items.destroy');
    Route::post('/fnb/addons', [FnbController::class, 'storeAddon'])->name('fnb.addons.store');
    Route::put('/fnb/addons/{fnbAddon}', [FnbController::class, 'updateAddon'])->name('fnb.addons.update');
    Route::delete('/fnb/addons/{fnbAddon}', [FnbController::class, 'destroyAddon'])->name('fnb.addons.destroy');

    // Rentals
    Route::get('/rentals', [RentalController::class, 'index'])->name('rentals.index');
    Route::post('/rentals', [RentalController::class, 'store'])->name('rentals.store');
    Route::get('/rentals/history', [RentalController::class, 'history'])->name('rentals.history');
    Route::get('/rentals/history/export', [RentalController::class, 'exportExcel'])->name('rentals.export');
    Route::get('/rentals/{rental}', [RentalController::class, 'show'])->name('rentals.show');
    Route::post('/rentals/{rental}/add-time', [RentalController::class, 'addTime'])->name('rentals.add-time');
    Route::post('/rentals/{rental}/add-fnb', [RentalController::class, 'addFnb'])->name('rentals.add-fnb');
    Route::delete('/rentals/{rental}/fnb/{fnbItem}', [RentalController::class, 'removeFnb'])->name('rentals.remove-fnb');
    Route::post('/rentals/{rental}/finish', [RentalController::class, 'finish'])->name('rentals.finish');
    Route::get('/rentals/{rental}/payment', [RentalController::class, 'payment'])->name('rentals.payment');
    Route::post('/rentals/{rental}/pay', [RentalController::class, 'pay'])->name('rentals.pay');
    Route::get('/rentals/{rental}/receipt', [RentalController::class, 'receipt'])->name('rentals.receipt');

    // Cash Outbound
    Route::resource('cash-outbounds', CashOutboundController::class)->except(['create', 'edit', 'show']);

    // Reservations
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::put('/reservations/{reservation}', [ReservationController::class, 'update'])->name('reservations.update');
    Route::delete('/reservations/{reservation}', [ReservationController::class, 'destroy'])->name('reservations.destroy');
    Route::post('/reservations/{reservation}/convert', [ReservationController::class, 'convert'])->name('reservations.convert');

    // FNB Orders (standalone)
    Route::get('/fnb-orders', [FnbOrderController::class, 'index'])->name('fnb-orders.index');
    Route::post('/fnb-orders', [FnbOrderController::class, 'store'])->name('fnb-orders.store');
    Route::get('/fnb-orders/{fnbOrder}', [FnbOrderController::class, 'show'])->name('fnb-orders.show');
    Route::post('/fnb-orders/{fnbOrder}/pay', [FnbOrderController::class, 'pay'])->name('fnb-orders.pay');
    Route::delete('/fnb-orders/{fnbOrder}', [FnbOrderController::class, 'destroy'])->name('fnb-orders.destroy');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
