<?php

namespace App\Services\Finance;

use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleOwnerPayout;
use App\Services\AuditLogger;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Statement for an attached / vendor vehicle's owner: earnings by the agreed rate (per km, per trip, fixed monthly or % of
 * trip revenue) minus expenses the operator paid for the vehicle in the period (suggested; editable at payout).
 */
class VehicleOwnerService
{
    public function __construct(private AuditLogger $audit) {}

    /** @return array{trips:int, km:int, revenue:string, rate_type:?string, rate:string, earned:string, expenses:string, paid:string, due:string, days:int, lines:array} */
    public function compute(Vehicle $vehicle, CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->computeMany(collect([$vehicle]), $from, $to)[$vehicle->id];
    }

    /**
     * Statements for many vehicles with a fixed number of queries.
     *
     * @param  Collection<int, Vehicle>  $vehicles
     * @return array<int, array>
     */
    public function computeMany(Collection $vehicles, CarbonInterface $from, CarbonInterface $to): array
    {
        $ids = $vehicles->pluck('id')->all();
        $trips = Trip::query()->with('booking:id,booking_number,final_snapshot')->whereIn('vehicle_id', $ids)->where('status', 'completed')
            ->whereBetween('completed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->orderBy('completed_at')->get()->groupBy('vehicle_id');
        $expenses = VehicleExpense::query()->whereIn('vehicle_id', $ids)
            ->whereDate('expense_date', '>=', $from->toDateString())->whereDate('expense_date', '<=', $to->toDateString())
            ->selectRaw('vehicle_id, sum(amount) as s')->groupBy('vehicle_id')->pluck('s', 'vehicle_id');
        $paid = VehicleOwnerPayout::query()->whereIn('vehicle_id', $ids)
            ->whereDate('period_from', '>=', $from->toDateString())->whereDate('period_to', '<=', $to->toDateString())
            ->selectRaw('vehicle_id, sum(amount) as s')->groupBy('vehicle_id')->pluck('s', 'vehicle_id');
        $days = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;

        $out = [];
        foreach ($vehicles as $vehicle) {
            $vt = $trips->get($vehicle->id, collect());
            $revenue = '0.00';
            $km = 0;
            $lines = [];
            foreach ($vt as $t) {
                $fare = (string) ($t->booking?->final_snapshot['taxable'] ?? '0');
                $revenue = Money::add($revenue, $fare);
                $km += $t->km() ?? 0;
                $lines[] = ['trip' => $t->number(), 'date' => $t->completed_at->toDateString(), 'km' => $t->km(), 'fare' => $fare];
            }
            $rate = (string) ($vehicle->owner_rate ?? '0');
            $earned = match ($vehicle->owner_rate_type) {
                'per_km' => Money::mul((string) $km, $rate),
                'per_trip' => Money::mul((string) $vt->count(), $rate),
                'monthly' => Money::round(bcdiv(bcmul($rate, (string) $days, 4), '30', 4)),
                'revenue_share' => Money::percent($revenue, $rate),
                default => '0.00',
            };
            $exp = number_format((float) ($expenses[$vehicle->id] ?? 0), 2, '.', '');
            $pd = number_format((float) ($paid[$vehicle->id] ?? 0), 2, '.', '');
            $out[$vehicle->id] = [
                'trips' => $vt->count(), 'km' => $km, 'revenue' => $revenue, 'rate_type' => $vehicle->owner_rate_type, 'rate' => $rate,
                'earned' => $earned, 'expenses' => $exp, 'paid' => $pd, 'due' => Money::sub(Money::sub($earned, $exp), $pd), 'days' => $days, 'lines' => $lines,
            ];
        }

        return $out;
    }

    public function settle(Vehicle $vehicle, CarbonInterface $from, CarbonInterface $to, string $amount, string $deductions, string $method, ?string $reference, ?string $notes, User $user): VehicleOwnerPayout
    {
        if (! $vehicle->isAttached()) {
            throw ValidationException::withMessages(['amount' => __('Owner payouts are only for attached or vendor vehicles.')]);
        }
        $calc = $this->compute($vehicle, $from, $to);
        $payout = new VehicleOwnerPayout([
            'vehicle_id' => $vehicle->id, 'period_from' => $from->toDateString(), 'period_to' => $to->toDateString(),
            'earned' => $calc['earned'], 'deductions' => Money::round($deductions), 'amount' => Money::round($amount),
            'method' => $method, 'reference' => $reference, 'paid_on' => now()->toDateString(), 'notes' => $notes,
            'breakdown' => $calc + ['owner' => $vehicle->owner_name],
        ]);
        $payout->recorded_by = $user->id;
        $payout->save();
        $this->audit->log('vehicle.owner_paid', $vehicle, [], ['amount' => $payout->amount, 'period' => $from->toDateString().'..'.$to->toDateString()]);

        return $payout;
    }
}
