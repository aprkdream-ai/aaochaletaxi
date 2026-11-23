<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Vehicle book value (management view, not a tax computation).
 * WDV: cost × (1 − r)^full years, then straight-line within the current year at that year's WDV rate.
 * SLM: cost − cost × r × days/365. Never below zero. bcmath, 2 dp.
 */
class Depreciation
{
    public static function bookValue(string $cost, string $method, string $ratePercent, CarbonInterface $purchased, CarbonInterface $asOf): string
    {
        if ($asOf->lessThanOrEqualTo($purchased) || bccomp($ratePercent, '0', 2) <= 0) {
            return bcadd($cost, '0', 2);
        }
        $r = bcdiv($ratePercent, '100', 6);
        $days = (int) $purchased->copy()->startOfDay()->diffInDays($asOf->copy()->startOfDay());

        if ($method === 'slm') {
            $dep = bcdiv(bcmul(bcmul($cost, $r, 6), (string) $days, 6), '365', 6);
            $value = bcsub($cost, $dep, 6);
        } else {
            $years = intdiv($days, 365);
            $value = bcmul($cost, bcpow(bcsub('1', $r, 6), (string) $years, 6), 6);
            $value = bcsub($value, bcdiv(bcmul(bcmul($value, $r, 6), (string) ($days % 365), 6), '365', 6), 6);
        }

        return bccomp($value, '0', 2) < 0 ? '0.00' : bcadd($value, '0', 2);
    }
}
