<?php

namespace App\Console\Commands;

use App\Enums\UserType;
use App\Models\User;
use App\Services\DemoDataService;
use Illuminate\Console\Command;

class DemoData extends Command
{
    protected $signature = 'demo:data {action : install|remove} {--owner-password=} {--driver-password=} {--force : Skip the confirmation in production}';

    protected $description = 'Install or remove the labelled demo dataset (demo operators, fleet, enquiries)';

    public function handle(DemoDataService $demo): int
    {
        $action = $this->argument('action');
        if (! in_array($action, ['install', 'remove'], true)) {
            $this->error('Action must be install or remove.');

            return self::INVALID;
        }
        if (app()->isProduction() && ! $this->option('force') && ! $this->confirm("Run demo:$action on PRODUCTION?")) {
            return self::FAILURE;
        }

        if ($action === 'remove') {
            $this->info('Removed demo tenants: '.$demo->remove());

            return self::SUCCESS;
        }

        $admin = User::query()->where('type', UserType::SuperAdmin)->orderBy('id')->first();
        if (! $admin) {
            $this->error('Create the super admin first (PlatformSeeder).');

            return self::FAILURE;
        }

        try {
            $logins = $demo->install($admin, [
                'owner' => $this->option('owner-password') ?: 'password1',
                'driver' => $this->option('driver-password') ?: 'driver123',
            ], withInvoice: ! app()->isProduction(), suffix: app()->isProduction() ? ' (Demo)' : '');
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Login', 'Credentials'], collect($logins)->map(fn ($v, $k) => [$k, $v])->values()->all());

        return self::SUCCESS;
    }
}
