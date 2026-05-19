<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\CashOutbound;
use Carbon\Carbon;

class DashboardService
{
    public function getSummary(): array
    {
        $today = Carbon::today();

        $todayRentals = Rental::whereDate('started_at', $today)->get();
        $finishedToday = $todayRentals->whereIn('status', ['finished', 'paid', 'half_paid']);

        $totalOmzetRental = $finishedToday->sum('rental_amount') + $finishedToday->sum('extra_amount');
        $totalOmzetFnb    = $finishedToday->sum('fnb_amount');
        $totalOmzet       = $finishedToday->sum('total_amount');
        $totalOutbound    = CashOutbound::whereDate('date', $today)->sum('nominal');

        $activeRentals = Rental::whereIn('status', ['running', 'half_paid'])->whereNull('ended_at')->count();
        $activeRooms   = Rental::whereIn('status', ['running', 'half_paid'])->whereNull('ended_at')->distinct('console_id')->count('console_id');
        $omzetBulanIni = Rental::whereYear('started_at', $today->year)
            ->whereMonth('started_at', $today->month)
            ->whereIn('status', ['finished', 'paid', 'half_paid'])
            ->sum('total_amount');

        return [
            'total_service_today'    => $todayRentals->count(),
            'total_omzet_today'      => $totalOmzet,
            'total_omzet_rental'     => $totalOmzetRental,
            'total_omzet_fnb'        => $totalOmzetFnb,
            'total_cash_outbound'    => $totalOutbound,
            'total_active_rentals'   => $activeRentals,
            'total_active_rooms'     => $activeRooms,
            'total_omzet_bulan_ini'  => $omzetBulanIni,
        ];
    }

    public function getDailyChart(int $days = 7): array
    {
        $labels = [];
        $rentalData = [];
        $fnbData = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $labels[] = $date->format('d M');
            $dayRentals = Rental::whereDate('started_at', $date)->whereIn('status', ['finished', 'paid', 'half_paid'])->get();
            $rentalData[] = $dayRentals->sum('rental_amount') + $dayRentals->sum('extra_amount');
            $fnbData[]    = $dayRentals->sum('fnb_amount');
        }

        return compact('labels', 'rentalData', 'fnbData');
    }

    public function getMonthlyChart(int $months = 6): array
    {
        $labels = [];
        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = Carbon::today()->subMonths($i);
            $labels[] = $month->format('M Y');
            $data[] = Rental::whereYear('started_at', $month->year)
                ->whereMonth('started_at', $month->month)
                ->whereIn('status', ['finished', 'paid', 'half_paid'])
                ->sum('total_amount');
        }

        return compact('labels', 'data');
    }
}
