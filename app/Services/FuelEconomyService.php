<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleExpense;
use Illuminate\Support\Collection;

/**
 * Mileage (km per litre) from the fuel log using the full-tank-to-full-tank method:
 * km between two full fills ÷ litres bought after the first full fill up to and including the second.
 * Partial fills only add litres. Uses bcmath (litres/amounts are decimals).
 */
class FuelEconomyService
{
    /** A fill below this share of the vehicle's average is flagged (possible leak, theft or wrong entry). */
    public const LOW_RATIO = '0.80';

    /**
     * @return array{fills: array<int, array{kmpl: string, low: bool}>, average: ?string, last: ?string, litres: string, rate: ?string}
     *         fills keyed by expense id; rate = average ₹ per litre
     */
    public function forVehicle(Vehicle $vehicle): array
    {
        return $this->compute(VehicleExpense::query()->where('vehicle_id', $vehicle->id)->where('category', 'fuel')
            ->whereNotNull('litres')->where('litres', '>', 0)
            ->orderBy('expense_date')->orderBy('id')
            ->get(['id', 'expense_date', 'amount', 'litres', 'odometer_km', 'full_tank']));
    }

    /** @param Collection<int, VehicleExpense> $fills ordered oldest first */
    public function compute(Collection $fills): array
    {
        $result = [];
        $totalKm = 0;
        $totalLitres = '0';
        $lastFull = null;
        $pending = '0';

        foreach ($fills as $f) {
            $pending = bcadd($pending, (string) $f->litres, 2);
            if (! $f->full_tank || $f->odometer_km === null) {
                continue;
            }
            if ($lastFull !== null && $f->odometer_km > $lastFull && bccomp($pending, '0', 2) > 0) {
                $km = $f->odometer_km - $lastFull;
                $result[$f->id] = bcdiv((string) $km, $pending, 2);
                $totalKm += $km;
                $totalLitres = bcadd($totalLitres, $pending, 2);
            }
            $lastFull = $f->odometer_km;
            $pending = '0';
        }

        $average = bccomp($totalLitres, '0', 2) > 0 ? bcdiv((string) $totalKm, $totalLitres, 2) : null;
        $threshold = $average !== null && count($result) >= 3 ? bcmul($average, self::LOW_RATIO, 2) : null;
        $allLitres = $fills->reduce(fn ($c, $f) => bcadd($c, (string) $f->litres, 2), '0');
        $allAmount = $fills->reduce(fn ($c, $f) => bcadd($c, (string) $f->amount, 2), '0');

        return [
            'fills' => array_map(fn ($kmpl) => ['kmpl' => $kmpl, 'low' => $threshold !== null && bccomp($kmpl, $threshold, 2) < 0], $result),
            'average' => $average,
            'last' => $result === [] ? null : end($result),
            'litres' => $allLitres,
            'rate' => bccomp($allLitres, '0', 2) > 0 ? bcdiv($allAmount, $allLitres, 2) : null,
        ];
    }
}
