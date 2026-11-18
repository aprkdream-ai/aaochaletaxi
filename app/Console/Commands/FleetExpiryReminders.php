<?php

namespace App\Console\Commands;

use App\Services\FleetExpiryService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class FleetExpiryReminders extends Command
{
    protected $signature = 'fleet:expiry-reminders';

    protected $description = 'Notify operators about vehicle/driver documents that are expiring';

    public function handle(FleetExpiryService $service, TenantContext $context): int
    {
        $n = $context->runWithoutScope(fn () => $service->sendDigests());
        $this->info("Digests sent: $n");

        return self::SUCCESS;
    }
}
