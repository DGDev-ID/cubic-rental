<?php

namespace App\Http\Controllers;

use App\Models\Console;
use App\Services\DashboardService;
use App\Services\RentalService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
        private RentalService $rentalService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Dashboard/Index', [
            'summary'      => $this->dashboardService->getSummary(),
            'dailyChart'   => $this->dashboardService->getDailyChart(),
            'monthlyChart' => $this->dashboardService->getMonthlyChart(),
        ]);
    }

    public function activeRentals()
    {
        return response()->json($this->rentalService->getActiveRentals());
    }

    public function monitorData()
    {
        return response()->json([
            'activeRentals'     => $this->rentalService->getActiveRentals(),
            'finishedRentals'   => $this->rentalService->getFinishedUnpaidRentals(),
            'availableConsoles' => Console::where('status', 'available')->orderBy('name')->get(),
        ]);
    }

    public function roomMonitor(): Response
    {
        return Inertia::render('Dashboard/RoomMonitor', [
            'activeRentals'     => $this->rentalService->getActiveRentals(),
            'finishedRentals'   => $this->rentalService->getFinishedUnpaidRentals(),
            'availableConsoles' => Console::where('status', 'available')->orderBy('name')->get(),
        ]);
    }
}
