<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\Rental;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // Badge kuning: Menunggu Pembayaran
            'pending_payment_count' => fn () => $request->user()
                ? Rental::where(function ($q) {
                    $q->where('status', 'finished')
                      ->orWhere(function ($q2) {
                          $q2->where('status', 'half_paid')
                             ->whereNotNull('ended_at');
                      });
                })->count()
                : 0,
            // Badge merah: Waktu Tetap Habis namun Overtime
            'overtime_count' => fn () => $request->user()
                ? Rental::whereIn('status', ['running', 'half_paid'])
                    ->whereNull('ended_at')
                    ->whereNotNull('scheduled_end_at')
                    ->where('scheduled_end_at', '<', now())
                    ->count()
                : 0,
            // Backward-compatibility alias
            'finished_unpaid_count' => fn () => $request->user()
                ? Rental::where(function ($q) {
                    $q->where('status', 'finished')
                      ->orWhere(function ($q2) {
                          $q2->where('status', 'half_paid')
                             ->whereNotNull('ended_at');
                      });
                })->count()
                : 0,
        ];
    }
}
