<?php

namespace App\Http\Controllers\Operator;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleInspection;
use App\Services\FleetExpiryService;
use App\Services\PlanLimitService;
use App\Support\Modules;
use App\Support\Tenancy\TenantContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(TenantContext $context, PlanLimitService $limits, FleetExpiryService $expiry): View
    {
        $tenant = $context->get();
        $taxi = $tenant->hasModule(Modules::TAXI);

        return view('operator.dashboard', [
            'tenant' => $tenant,
            'taxi' => $taxi,
            'subscription' => $tenant->usableSubscription(),
            'usage' => $limits->usage($tenant),
            'fleet' => [
                'vehicles_available' => Vehicle::query()->where('status', 'active')->where('is_available', true)->count(),
                'vehicles_maintenance' => Vehicle::query()->where('status', 'maintenance')->count(),
                'drivers_available' => Driver::query()->where('status', 'active')->where('is_available', true)->count(),
                'expenses_month' => VehicleExpense::query()->where('expense_date', '>=', now()->startOfMonth())->sum('amount'),
            ],
            'expiring' => array_slice($expiry->itemsFor($tenant), 0, 8),
            'services' => array_slice($expiry->serviceItemsFor($tenant), 0, 8),
            'checkIssues' => VehicleInspection::query()->with(['vehicle:id,registration_number', 'driver:id,name'])
                ->where('issues_count', '>', 0)->whereNull('resolved_at')->where('inspected_on', '>=', now()->subDays(14)->toDateString())
                ->latest('inspected_on')->limit(6)->get(),
            'on_trip' => Trip::query()->where('status', 'started')->count(),
            'fleetToday' => $tenant->hasModule(Modules::FLEET)
                ? Trip::query()->with(['vehicle:id,registration_number', 'driver:id,name', 'department:id,name'])->whereNull('booking_id')
                    ->where(fn ($q) => $q->where('status', 'started')->orWhere(fn ($w) => $w->where('status', 'assigned')->where('start_at', '<=', today()->endOfDay())))
                    ->orderByRaw("case when status = 'started' then 0 else 1 end")->orderBy('start_at')->limit(8)->get()
                : collect(),
        ] + ($taxi ? $this->bookingStats() : ['today' => null, 'upcoming' => collect(), 'chart' => []]));
    }

    /** Booking widgets (Taxi module only). */
    private function bookingStats(): array
    {
        $perDay = Booking::query()->where('created_at', '>=', now()->subDays(13)->startOfDay())->pluck('created_at')
            ->countBy(fn ($d) => $d->toDateString());

        return [
            'today' => [
                'pickups' => Booking::query()->whereDate('pickup_at', today())->where('status', '!=', BookingStatus::Cancelled)->count(),
                'to_quote' => Booking::query()->whereIn('status', [BookingStatus::Enquiry, BookingStatus::Quoted])->count(),
                'revenue_month' => Booking::query()->where('status', BookingStatus::Completed)->where('updated_at', '>=', now()->startOfMonth())->sum('final_total'),
            ],
            'upcoming' => Booking::query()->with(['trip.driver:id,name', 'trip.vehicle:id,registration_number', 'category:id,name'])
                ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Assigned, BookingStatus::Started, BookingStatus::Quoted])
                ->where('pickup_at', '>=', now()->subHours(6))->orderBy('pickup_at')->limit(6)->get(),
            'chart' => collect(range(13, 0))->map(fn ($i) => now()->subDays($i))
                ->map(fn ($d) => ['label' => $d->format('d M'), 'value' => (int) ($perDay[$d->toDateString()] ?? 0)])->values()->all(),
        ];
    }
}
