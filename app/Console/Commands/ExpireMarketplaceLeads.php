<?php

namespace App\Console\Commands;

use App\Services\Marketplace\MarketplaceService;
use Illuminate\Console\Command;

class ExpireMarketplaceLeads extends Command
{
    protected $signature = 'marketplace:expire-leads';

    protected $description = 'Close marketplace quote requests that no operator accepted in time';

    public function handle(MarketplaceService $marketplace): int
    {
        $this->info($marketplace->expireLeads().' request(s) expired.');

        return self::SUCCESS;
    }
}
