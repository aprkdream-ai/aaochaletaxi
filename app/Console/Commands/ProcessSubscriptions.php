<?php

namespace App\Console\Commands;

use App\Services\SubscriptionLifecycleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class ProcessSubscriptions extends Command
{
    protected $signature = 'subscriptions:process';

    protected $description = 'Move ended subscriptions to grace/expired and send renewal reminders';

    public function handle(SubscriptionLifecycleService $service, TenantContext $context): int
    {
        $stats = $context->runWithoutScope(fn () => $service->process());
        $this->info(sprintf('grace: %d, expired: %d, reminders: %d', $stats['grace'], $stats['expired'], $stats['reminders']));

        return self::SUCCESS;
    }
}
