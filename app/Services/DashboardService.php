<?php

namespace App\Services;

use App\Models\Rental;
use App\Models\FnbOrder;
use App\Models\CashOutbound;
use App\Models\Console;
use Carbon\Carbon;

class DashboardService
{
    public function getSummary(): array
    {
        $today = Carbon::today();

        $todayRentals = Rental::whereDate('started_at', $today)->get();
        $finishedToday = $todayRentals->whereIn('status', ['finished', 'paid', 'half_paid']);

        $totalOmzetRental = $finishedToday->sum('rental_amount') + $finishedToday->sum('extra_amount');
        $totalOmzetFnbRental = $finishedToday->sum('fnb_amount');

        // Standalone FNB orders paid today
        $fnbOrdersToday  = FnbOrder::whereDate('paid_at', $today)->where('status', 'paid')->sum('total_amount');
        $totalOmzetFnb   = $totalOmzetFnbRental + $fnbOrdersToday;
        $totalOmzet      = $finishedToday->sum('total_amount') + $fnbOrdersToday;
        $totalOutbound   = CashOutbound::whereDate('date', $today)->sum('nominal');

        $activeRentals = Rental::whereIn('status', ['running', 'half_paid'])->whereNull('ended_at')->count();
        $totalRooms    = Console::count();
        $omzetBulanIni = Rental::whereYear('started_at', $today->year)
            ->whereMonth('started_at', $today->month)
            ->whereIn('status', ['finished', 'paid', 'half_paid'])
            ->sum('total_amount')
            + FnbOrder::whereYear('paid_at', $today->year)
            ->whereMonth('paid_at', $today->month)
            ->where('status', 'paid')
            ->sum('total_amount');

        $pengeluaranBulanIni = CashOutbound::whereYear('date', $today->year)
            ->whereMonth('date', $today->month)
            ->sum('nominal');

        $labaBersihBulanIni = (float) $omzetBulanIni - (float) $pengeluaranBulanIni;

        return [
            'total_service_today'       => $todayRentals->count(),
            'total_omzet_today'         => (float) $totalOmzet,
            'total_omzet_rental'        => (float) $totalOmzetRental,
            'total_omzet_fnb'           => (float) $totalOmzetFnb,
            'total_cash_outbound'       => (float) $totalOutbound,
            'total_active_rentals'      => $activeRentals,
            'total_active_rooms'        => $totalRooms,
            'total_omzet_bulan_ini'     => (float) $omzetBulanIni,
            'pengeluaran_bulan_ini'     => (float) $pengeluaranBulanIni,
            'laba_bersih_bulan_ini'     => $labaBersihBulanIni,
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
            $fnbData[]    = $dayRentals->sum('fnb_amount')
                + FnbOrder::whereDate('paid_at', $date)->where('status', 'paid')->sum('total_amount');
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
                ->sum('total_amount')
                + FnbOrder::whereYear('paid_at', $month->year)
                ->whereMonth('paid_at', $month->month)
                ->where('status', 'paid')
                ->sum('total_amount');
        }

        return compact('labels', 'data');
    }
}
