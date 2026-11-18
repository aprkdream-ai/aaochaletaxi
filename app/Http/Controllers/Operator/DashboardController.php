<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Driver;
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
        ]);
    }
}
