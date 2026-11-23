<?php

namespace App\Services\Finance;

use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Models\Trip;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Earnings = pay (per trip × trips | commission % × fare | monthly salary pro-rata) + driver allowances
 * charged to customers on those trips. Payouts are append-only settlements.
 */
class DriverEarningsService
{
    public function __construct(private AuditLogger $audit) {}

    /** @return array{trips:int, fare:string, allowances:string, pay:string, earned:string, paid:string, due:string, lines:array} */
    public function compute(Driver $driver, CarbonInterface $from, CarbonInterface $to): array
    {
        $trips = Trip::query()->with('booking:id,booking_number,final_snapshot,pickup_at')->where('driver_id', $driver->id)
            ->where('status', 'completed')->whereBetween('completed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->orderBy('completed_at')->get();

        $fare = '0.00';
        $allowances = '0.00';
        $lines = [];
        foreach ($trips as $trip) {
            $snap = $trip->booking?->final_snapshot ?? []; // fleet trips: no fare
            $taxable = (string) ($snap['taxable'] ?? '0');
            $allow = collect($snap['lines'] ?? [])->where('code', 'driver_allowance')->sum(fn ($l) => (float) $l['amount']);
            $fare = Money::add($fare, $taxable);
            $allowances = Money::add($allowances, (string) $allow);
            $lines[] = ['booking' => $trip->number(), 'date' => $trip->completed_at->toDateString(), 'fare' => $taxable, 'allowance' => number_format($allow, 2, '.', '')];
        }

        $rate = (string) ($driver->pay_amount ?? '0');
        $days = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $pay = match ($driver->pay_type) {
            'per_trip' => Money::mul((string) $trips->count(), $rate),
            'commission' => Money::percent(Money::sub($fare, $allowances), $rate),
            default => Money::round(bcdiv(bcmul($rate, (string) $days, 4), '30', 4)),
        };
        $earned = Money::add($pay, $allowances);
        $paid = (string) DriverSettlement::query()->where('driver_id', $driver->id)
            ->whereDate('period_from', '>=', $from->toDateString())->whereDate('period_to', '<=', $to->toDateString())->sum('amount');

        return [
            'trips' => $trips->count(), 'fare' => $fare, 'allowances' => $allowances, 'pay' => $pay, 'earned' => $earned,
            'paid' => number_format((float) $paid, 2, '.', ''), 'due' => Money::sub($earned, $paid), 'lines' => $lines, 'days' => $days,
        ];
    }

    public function settle(Driver $driver, CarbonInterface $from, CarbonInterface $to, string|float $amount, string|float $deductions, string $method, ?string $reference, ?string $notes, User $user): DriverSettlement
    {
        if ($to->lessThan($from)) {
            throw ValidationException::withMessages(['period_to' => __('End date must be after start date.')]);
        }
        $calc = $this->compute($driver, $from, $to);
        $s = new DriverSettlement([
            'driver_id' => $driver->id, 'period_from' => $from->toDateString(), 'period_to' => $to->toDateString(), 'trips' => $calc['trips'],
            'earned' => $calc['earned'], 'deductions' => number_format((float) $deductions, 2, '.', ''), 'amount' => number_format((float) $amount, 2, '.', ''),
            'method' => $method, 'reference' => $reference, 'paid_on' => now()->toDateString(), 'notes' => $notes, 'breakdown' => $calc,
        ]);
        $s->recorded_by = $user->id;
        $s->save();
        $this->audit->log('driver.settled', $driver, [], ['amount' => $s->amount, 'period' => $s->period_from->toDateString().'..'.$s->period_to->toDateString()]);

        return $s;
    }

    public static function parseRange(?string $from, ?string $to): array
    {
        $f = $from ? Carbon::parse($from) : now()->startOfMonth();
        $t = $to ? Carbon::parse($to) : now();

        return [$f->startOfDay(), $t->startOfDay()];
    }
}
