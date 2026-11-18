<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Document expiry state used for badges, filters and reminders. */
class Expiry
{
    public const DUE_DAYS = 30;

    /** @return array{state: 'missing'|'expired'|'due'|'ok', days: ?int, label: string, tone: string} */
    public static function of(?CarbonInterface $date): array
    {
        if (! $date) {
            return ['state' => 'missing', 'days' => null, 'label' => __('Not set'), 'tone' => 'muted'];
        }

        $days = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);

        return match (true) {
            $days < 0 => ['state' => 'expired', 'days' => $days, 'label' => __('Expired :n d ago', ['n' => abs($days)]), 'tone' => 'danger'],
            $days === 0 => ['state' => 'due', 'days' => 0, 'label' => __('Expires today'), 'tone' => 'danger'],
            $days <= self::DUE_DAYS => ['state' => 'due', 'days' => $days, 'label' => __('In :n days', ['n' => $days]), 'tone' => 'warn'],
            default => ['state' => 'ok', 'days' => $days, 'label' => $date->format('d M Y'), 'tone' => 'ok'],
        };
    }
}
