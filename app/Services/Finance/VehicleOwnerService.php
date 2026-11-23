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
        $trips = Trip::query()->with('booking:id,booking_number,final_snapshot')->where('vehicle_id', $vehicle->id)->where('status', 'completed')
            ->whereBetween('completed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->orderBy('completed_at')->get();

        $revenue = '0.00';
        $km = 0;
        $lines = [];
        foreach ($trips as $t) {
            $fare = (string) ($t->booking?->final_snapshot['taxable'] ?? '0');
            $revenue = Money::add($revenue, $fare);
            $km += $t->km() ?? 0;
            $lines[] = ['trip' => $t->number(), 'date' => $t->completed_at->toDateString(), 'km' => $t->km(), 'fare' => $fare];
        }

        $rate = (string) ($vehicle->owner_rate ?? '0');
        $days = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $earned = match ($vehicle->owner_rate_type) {
            'per_km' => Money::mul((string) $km, $rate),
            'per_trip' => Money::mul((string) $trips->count(), $rate),
            'monthly' => Money::round(bcdiv(bcmul($rate, (string) $days, 4), '30', 4)),
            'revenue_share' => Money::percent($revenue, $rate),
            default => '0.00',
        };
        $expenses = number_format((float) VehicleExpense::query()->where('vehicle_id', $vehicle->id)
            ->whereDate('expense_date', '>=', $from->toDateString())->whereDate('expense_date', '<=', $to->toDateString())->sum('amount'), 2, '.', '');
        $paid = number_format((float) VehicleOwnerPayout::query()->where('vehicle_id', $vehicle->id)
            ->whereDate('period_from', '>=', $from->toDateString())->whereDate('period_to', '<=', $to->toDateString())->sum('amount'), 2, '.', '');

        return [
            'trips' => $trips->count(), 'km' => $km, 'revenue' => $revenue, 'rate_type' => $vehicle->owner_rate_type, 'rate' => $rate,
            'earned' => $earned, 'expenses' => $expenses, 'paid' => $paid, 'due' => Money::sub(Money::sub($earned, $expenses), $paid),
            'days' => $days, 'lines' => $lines,
        ];
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
