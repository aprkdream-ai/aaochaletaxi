<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Gap-free, concurrency-safe counters backed by the `sequences` table (row lock). */
class SequenceService
{
    public function next(string $key): int
    {
        return DB::transaction(function () use ($key) {
            DB::table('sequences')->insertOrIgnore(['key' => $key, 'value' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $current = (int) DB::table('sequences')->where('key', $key)->lockForUpdate()->value('value');
            $next = $current + 1;
            DB::table('sequences')->where('key', $key)->update(['value' => $next, 'updated_at' => now()]);

            return $next;
        });
    }

    /** Indian financial year label, e.g. 2026-27 (April–March). */
    public static function financialYear(?CarbonInterface $date = null): string
    {
        $date ??= now();
        $start = $date->month >= 4 ? $date->year : $date->year - 1;

        return $start.'-'.substr((string) ($start + 1), -2);
    }
}
