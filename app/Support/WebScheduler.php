<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fallback for hosts where a real cron job is not (yet) configured.
 * Runs after the HTTP response has been sent (LiteSpeed/FPM finish_request), at most once a minute,
 * entirely in-process (no proc_open/exec). Disengages automatically while cron heartbeats are fresh.
 */
class WebScheduler
{
    public const CRON_HEARTBEAT = 'scheduler:cron_heartbeat';

    public const LAST_RUN = 'scheduler:last_run';

    /** Called by the real scheduler every minute (`php artisan schedule:run` from cron). */
    public static function cronHeartbeat(): void
    {
        Cache::put(self::CRON_HEARTBEAT, now()->timestamp, now()->addHours(2));
        Cache::put(self::LAST_RUN, ['at' => now()->toIso8601String(), 'via' => 'cron'], now()->addDays(7));
    }

    public static function cronIsActive(): bool
    {
        return (int) Cache::get(self::CRON_HEARTBEAT, 0) >= now()->subMinutes(3)->timestamp;
    }

    /** @return array{at: string, via: string}|null */
    public static function lastRun(): ?array
    {
        return Cache::get(self::LAST_RUN);
    }

    public static function tick(): bool
    {
        if (! config('aaochale.web_scheduler') || self::cronIsActive() || ! Cache::add('scheduler:web_tick', 1, 60)) {
            return false;
        }

        ignore_user_abort(true);
        @set_time_limit(120);

        try {
            Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 25, '--tries' => 3, '--quiet' => true]);

            if (Cache::add('scheduler:web_hourly', 1, 3600)) {
                Artisan::call('subscriptions:process');
                Artisan::call('auth:clear-resets');
            }
            if (Cache::add('scheduler:web_daily', 1, 86400)) {
                Artisan::call('queue:prune-failed', ['--hours' => 720]);
            }

            Cache::put(self::LAST_RUN, ['at' => now()->toIso8601String(), 'via' => 'web'], now()->addDays(7));
        } catch (Throwable $e) {
            Log::error('Web scheduler tick failed: '.$e->getMessage());
        }

        return true;
    }
}
