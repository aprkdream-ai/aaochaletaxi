<?php

namespace App\Services\Booking;

use App\Enums\BookingType;
use App\Models\FareRule;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Pure fare computation (no DB writes). Returns a self-contained breakdown that is stored as a snapshot,
 * so later changes to fare rules never alter existing quotes.
 *
 * Rules:
 *  - local/airport/railway/corporate: base + max(km, min_km) × per_km
 *  - outstation one-way: base + max(km, min_km) × per_km + driver allowance × days
 *  - outstation round:   base + max(km, min_km × days) × per_km + driver allowance × days
 *  - hourly/full-day:    package (base) + extra km × extra_km_rate + extra hours × extra_hour_rate (+ allowance for full day)
 *  - night charge once when pickup (or return) falls in the night window
 *  - waiting beyond free minutes × waiting_per_min
 *  - GST on fare components; tolls, parking and state tax are pass-through (not taxed), discount reduces taxable value.
 */
class FareCalculator
{
    /**
     * @param  array{distance_km?: float|string|null, pickup_at: CarbonInterface, return_at?: ?CarbonInterface, rental_hours?: ?int,
     *               waiting_min?: ?int, tolls?: float|string|null, parking?: float|string|null, state_tax?: float|string|null, discount?: float|string|null}  $in
     */
    public function calculate(FareRule $rule, array $in): array
    {
        $type = $rule->booking_type;
        $km = $this->dec($in['distance_km'] ?? 0, 1);
        $pickup = $in['pickup_at'];
        $return = $in['return_at'] ?? null;
        $lines = [];
        $days = 1;
        $billableKm = $km;

        if ($type === BookingType::OutstationRound) {
            if (! $return || $return->lessThan($pickup)) {
                throw ValidationException::withMessages(['return_at' => __('Return date/time is required for round trips and must be after pickup.')]);
            }
            $days = (int) $pickup->copy()->startOfDay()->diffInDays($return->copy()->startOfDay()) + 1;
        }

        if ($type->isRental()) {
            $hours = max((int) ($in['rental_hours'] ?? $rule->included_hours), 1);
            $lines[] = $this->line('package', __('Package :h hrs / :k km', ['h' => $rule->included_hours, 'k' => $rule->included_km]), null, (string) $rule->base_fare);
            $extraKm = max(0, (float) $km - $rule->included_km);
            if ($extraKm > 0) {
                $lines[] = $this->line('extra_km', __('Extra km'), $this->num($extraKm).' km × ₹'.$rule->extra_km_rate, Money::mul((string) $extraKm, (string) $rule->extra_km_rate));
            }
            $extraHours = max(0, $hours - $rule->included_hours);
            if ($extraHours > 0) {
                $lines[] = $this->line('extra_hours', __('Extra hours'), $extraHours.' h × ₹'.$rule->extra_hour_rate, Money::mul((string) $extraHours, (string) $rule->extra_hour_rate));
            }
            if ($type === BookingType::FullDay && (float) $rule->driver_allowance > 0) {
                $lines[] = $this->line('driver_allowance', __('Driver allowance'), '1 '.__('day'), (string) $rule->driver_allowance);
            }
        } else {
            $minKm = $type === BookingType::OutstationRound ? $rule->min_km * $days : $rule->min_km;
            $billableKm = max((float) $km, (float) $minKm);
            if ((float) $rule->base_fare > 0) {
                $lines[] = $this->line('base', __('Base fare'), null, (string) $rule->base_fare);
            }
            $detail = $this->num($billableKm).' km × ₹'.$rule->per_km.($billableKm > (float) $km ? ' ('.__('minimum :n km', ['n' => $minKm]).')' : '');
            $lines[] = $this->line('distance', __('Distance charge'), $detail, Money::mul((string) $billableKm, (string) $rule->per_km));
            if ($type->isOutstation() && (float) $rule->driver_allowance > 0) {
                $lines[] = $this->line('driver_allowance', __('Driver allowance'), $days.' × ₹'.$rule->driver_allowance, Money::mul((string) $days, (string) $rule->driver_allowance));
            }
        }

        if ((float) $rule->night_charge > 0 && ($this->isNight($pickup, $rule) || ($return && $this->isNight($return, $rule)))) {
            $lines[] = $this->line('night', __('Night charge'), substr($rule->night_start, 0, 5).'–'.substr($rule->night_end, 0, 5), (string) $rule->night_charge);
        }

        $waiting = max(0, (int) ($in['waiting_min'] ?? 0) - $rule->waiting_free_min);
        if ($waiting > 0 && (float) $rule->waiting_per_min > 0) {
            $lines[] = $this->line('waiting', __('Waiting'), $waiting.' min × ₹'.$rule->waiting_per_min, Money::mul((string) $waiting, (string) $rule->waiting_per_min));
        }

        $fare = Money::add(...array_column($lines, 'amount'));
        $discount = $this->dec($in['discount'] ?? 0);
        if ((float) $discount > (float) $fare) {
            throw ValidationException::withMessages(['discount' => __('Discount cannot exceed the fare.')]);
        }
        $taxable = Money::sub($fare, $discount);
        $gst = Money::percent($taxable, (string) $rule->gst_percent);

        $passthrough = [];
        foreach (['tolls' => __('Tolls'), 'parking' => __('Parking'), 'state_tax' => __('State entry tax')] as $key => $label) {
            $amount = $this->dec($in[$key] ?? 0);
            if ((float) $amount > 0) {
                $passthrough[] = $this->line($key, $label, __('at actuals'), $amount);
            }
        }
        $passTotal = $passthrough ? Money::add(...array_column($passthrough, 'amount')) : '0.00';

        $exact = Money::add($taxable, $gst, $passTotal);
        $total = number_format(round((float) $exact), 2, '.', ''); // round to whole rupee

        return [
            'rule_id' => $rule->id,
            'type' => $type->value,
            'category_id' => $rule->vehicle_category_id,
            'distance_km' => (float) $km,
            'billable_km' => (float) $billableKm,
            'days' => $days,
            'lines' => $lines,
            'fare' => $fare,
            'discount' => $discount,
            'taxable' => $taxable,
            'gst_percent' => (string) $rule->gst_percent,
            'gst' => $gst,
            'passthrough' => $passthrough,
            'passthrough_total' => $passTotal,
            'round_off' => Money::sub($total, $exact),
            'total' => $total,
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    public function isNight(CarbonInterface $at, FareRule $rule): bool
    {
        $t = $at->format('H:i:s');
        $start = $rule->night_start;
        $end = $rule->night_end;

        return $start > $end ? ($t >= $start || $t < $end) : ($t >= $start && $t < $end);
    }

    private function line(string $code, string $label, ?string $detail, string $amount): array
    {
        return ['code' => $code, 'label' => $label, 'detail' => $detail, 'amount' => number_format((float) $amount, 2, '.', '')];
    }

    private function dec(mixed $v, int $scale = 2): string
    {
        return number_format(max(0, (float) $v), $scale, '.', '');
    }

    private function num(float|string $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');
    }
}
