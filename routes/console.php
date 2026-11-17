<?php

use Illuminate\Support\Facades\Schedule;

// Single hosting cron runs `php artisan schedule:run` every minute (see docs/DEPLOYMENT.md).

// Drain the database queue without a long-running worker (shared hosting).
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3 --backoff=30')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('queue:prune-batches --hours=72')->daily();
