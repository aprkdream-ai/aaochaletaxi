<?php

namespace App\Http\Controllers\Operator;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Services\FleetExpiryService;
use App\Services\PlanLimitService;
use App\Support\Tenancy\TenantContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(TenantContext $context, PlanLimitService $limits, FleetExpiryService $expiry): View
    {
        $tenant = $context->get();
        $from = now()->subDays(13)->startOfDay();
        $perDay = Booking::query()->where('created_at', '>=', $from)->pluck('created_at')
            ->countBy(fn ($d) => $d->toDateString());
        $chart = collect(range(13, 0))->map(fn ($i) => now()->subDays($i))
            ->map(fn ($d) => ['label' => $d->format('d M'), 'value' => (int) ($perDay[$d->toDateString()] ?? 0)])->values()->all();

        return view('operator.dashboard', [
            'tenant' => $tenant,
            'subscription' => $tenant->usableSubscription(),
            'usage' => $limits->usage($tenant),
            'fleet' => [
                'vehicles_available' => Vehicle::query()->where('status', 'active')->where('is_available', true)->count(),
                'vehicles_maintenance' => Vehicle::query()->where('status', 'maintenance')->count(),
                'drivers_available' => Driver::query()->where('status', 'active')->where('is_available', true)->count(),
                'expenses_month' => VehicleExpense::query()->where('expense_date', '>=', now()->startOfMonth())->sum('amount'),
            ],
            'expiring' => array_slice($expiry->itemsFor($tenant), 0, 8),
            'today' => [
                'pickups' => Booking::query()->whereDate('pickup_at', today())->where('status', '!=', BookingStatus::Cancelled)->count(),
                'on_trip' => Trip::query()->where('status', 'started')->count(),
                'to_quote' => Booking::query()->whereIn('status', [BookingStatus::Enquiry, BookingStatus::Quoted])->count(),
                'revenue_month' => Booking::query()->where('status', BookingStatus::Completed)->where('updated_at', '>=', now()->startOfMonth())->sum('final_total'),
            ],
            'upcoming' => Booking::query()->with(['trip.driver:id,name', 'trip.vehicle:id,registration_number', 'category:id,name'])
                ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Assigned, BookingStatus::Started, BookingStatus::Quoted])
                ->where('pickup_at', '>=', now()->subHours(6))->orderBy('pickup_at')->limit(6)->get(),
            'chart' => $chart,
        ]);
    }
}
