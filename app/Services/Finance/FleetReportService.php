<?php

namespace App\Services\Finance;

use App\Models\Department;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleMaintenance;
use App\Services\FuelEconomyService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Running cost and utilisation per vehicle and per department for a period (all completed trips: bookings + fleet).
 * Cost per km = (fuel + other expenses + maintenance) ÷ km driven on completed trips in the period.
 * Department cost = its trip km × the vehicle's cost per km (allocation, not a ledger entry).
 */
class FleetReportService
{
    public function __construct(private FuelEconomyService $fuel) {}

    /** @return Collection<int, object> */
    public function vehicles(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $trips = $this->trips($from, $to)->groupBy('vehicle_id');
        $expenses = VehicleExpense::query()->whereDate('expense_date', '>=', $from->toDateString())->whereDate('expense_date', '<=', $to->toDateString())
            ->orderBy('expense_date')->orderBy('id')->get(['id', 'vehicle_id', 'category', 'amount', 'litres', 'odometer_km', 'full_tank', 'expense_date'])->groupBy('vehicle_id');
        $maintenance = VehicleMaintenance::query()->whereDate('service_date', '>=', $from->toDateString())->whereDate('service_date', '<=', $to->toDateString())
            ->selectRaw('vehicle_id, sum(cost) as s')->groupBy('vehicle_id')->pluck('s', 'vehicle_id');

        return Vehicle::withTrashed()->with('category:id,name')->orderBy('registration_number')->get()->map(function (Vehicle $v) use ($trips, $expenses, $maintenance) {
            $vt = $trips->get($v->id, collect());
            $ve = $expenses->get($v->id, collect());
            $fuelRows = $ve->where('category', 'fuel');
            $km = (int) $vt->sum(fn (Trip $t) => $t->km() ?? 0);
            $fuelCost = Money::add('0', ...$fuelRows->map(fn ($e) => (string) $e->amount)->all());
            $otherCost = Money::add('0', ...$ve->where('category', '!=', 'fuel')->map(fn ($e) => (string) $e->amount)->all());
            $mnt = number_format((float) ($maintenance[$v->id] ?? 0), 2, '.', '');
            $total = Money::add($fuelCost, $otherCost, $mnt);
            $economy = $this->fuel->compute($fuelRows->filter(fn ($e) => (float) $e->litres > 0)->values());

            return (object) [
                'vehicle' => $v, 'trips' => $vt->count(), 'fleet_trips' => $vt->filter->isFleet()->count(), 'km' => $km,
                'hours' => round((float) $vt->sum(fn (Trip $t) => $t->hours() ?? 0), 1),
                'litres' => $economy['litres'], 'kmpl' => $economy['average'], 'fuel' => $fuelCost, 'other' => $otherCost, 'maintenance' => $mnt,
                'total' => $total, 'per_km' => $km > 0 ? bcdiv($total, (string) $km, 2) : null, 'book_value' => $v->bookValue(),
            ];
        })->filter(fn ($r) => $r->trips > 0 || (float) $r->total > 0 || $r->book_value !== null)->values();
    }

    /** @param Collection<int, object> $vehicleRows from vehicles() for the same period */
    public function departments(CarbonInterface $from, CarbonInterface $to, Collection $vehicleRows): Collection
    {
        $perKm = $vehicleRows->mapWithKeys(fn ($r) => [$r->vehicle->id => $r->per_km]);
        $byDept = $this->trips($from, $to)->filter->isFleet()->groupBy(fn (Trip $t) => (int) $t->department_id);
        $names = Department::query()->pluck('name', 'id');

        return $byDept->map(function (Collection $trips, int $deptId) use ($perKm, $names) {
            $cost = Money::add('0', ...$trips->map(function (Trip $t) use ($perKm) {
                $rate = $perKm->get($t->vehicle_id);

                return ($t->km() && $rate !== null) ? Money::mul((string) $t->km(), $rate) : '0';
            })->all());

            return (object) [
                'department' => $deptId ? ($names[$deptId] ?? __('Deleted')) : __('No department'),
                'trips' => $trips->count(), 'km' => (int) $trips->sum(fn (Trip $t) => $t->km() ?? 0),
                'hours' => round((float) $trips->sum(fn (Trip $t) => $t->hours() ?? 0), 1), 'cost' => $cost,
            ];
        })->sortByDesc('km')->values();
    }

    /** @return Collection<int, Trip> */
    private function trips(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Trip::query()->where('status', 'completed')->whereBetween('completed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['id', 'booking_id', 'vehicle_id', 'department_id', 'start_km', 'end_km', 'started_at', 'completed_at']);
    }
}
