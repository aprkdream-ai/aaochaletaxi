<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use App\Services\DemoDataService;
use Illuminate\Database\Seeder;

/** Local/demo data. In production use `php artisan demo:data install` (labelled, no invoices). */
class DemoSeeder extends Seeder
{
    public function run(DemoDataService $demo): void
    {
        if (app()->isProduction() || $demo->exists()) {
            return;
        }

        $superAdmin = User::query()->where('type', UserType::SuperAdmin)->first();
        if (! $superAdmin) {
            $superAdmin = new User(['name' => 'Super Admin', 'email' => 'admin@aaochaletaxi.test', 'password' => 'password']);
            $superAdmin->type = UserType::SuperAdmin;
            $superAdmin->status = UserStatus::Active;
            $superAdmin->save();
        }

        $demo->install($superAdmin, ['owner' => 'password1', 'driver' => 'driver123'], withInvoice: true);
    }
}
