<?php

use App\Support\WebScheduler;
use Illuminate\Support\Facades\Schedule;

// Single hosting cron runs `php artisan schedule:run` every minute (see docs/DEPLOYMENT.md).

// Heartbeat: tells the web fallback scheduler that a real cron is running.
Schedule::call(fn () => WebScheduler::cronHeartbeat())->everyMinute()->name('cron-heartbeat');

// Drain the database queue without a long-running worker (shared hosting).
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3 --backoff=30')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('queue:prune-batches --hours=72')->daily();

Schedule::command('subscriptions:process')->hourly()->withoutOverlapping(30);

Schedule::command('fleet:expiry-reminders')->dailyAt('08:00')->withoutOverlapping(30);
