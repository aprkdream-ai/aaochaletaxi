<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantAdminService;
use App\Services\TenantOnboardingService;
use Illuminate\Database\Seeder;

/** Local/demo data only. Never runs in production. */
class DemoSeeder extends Seeder
{
    public function run(TenantOnboardingService $onboarding, TenantAdminService $admin): void
    {
        if (app()->isProduction()) {
            return;
        }

        $superAdmin = User::query()->where('type', UserType::SuperAdmin)->first();
        if (! $superAdmin) {
            $superAdmin = new User(['name' => 'Super Admin', 'email' => 'admin@aaochaletaxi.test', 'password' => 'password']);
            $superAdmin->type = UserType::SuperAdmin;
            $superAdmin->status = UserStatus::Active;
            $superAdmin->save();
        }

        if (! Tenant::query()->where('email', 'owner@shivtravels.test')->exists()) {
            $owner = $onboarding->register(['business_name' => 'Shiv Travels', 'city' => 'Ahmedabad', 'state' => 'Gujarat', 'name' => 'Ramesh Patel', 'email' => 'owner@shivtravels.test', 'phone' => '9876500001', 'password' => 'password1']);
            $admin->approve($owner->tenant, $superAdmin);
        }

        if (! Tenant::query()->where('email', 'owner@ganeshcabs.test')->exists()) {
            $onboarding->register(['business_name' => 'Ganesh Cabs', 'city' => 'Pune', 'state' => 'Maharashtra', 'name' => 'Sunil Joshi', 'email' => 'owner@ganeshcabs.test', 'phone' => '9876500002', 'password' => 'password1']);
        }
    }
}
