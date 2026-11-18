<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\VehicleCategory;
use App\Services\RoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Idempotent production-safe seeder: permissions, platform roles, plans, defaults, first super admin.
 * Safe to run on every deploy — never overwrites values edited by admins.
 */
class PlatformSeeder extends Seeder
{
    public function run(RoleService $roles): void
    {
        $roles->syncPermissions();
        $roles->seedPlatformRoles();

        $plans = [
            ['code' => 'starter', 'name' => 'Starter', 'description' => 'For small operators getting started.', 'price_monthly' => 799, 'price_yearly' => 7990,
                'max_vehicles' => 5, 'max_drivers' => 5, 'max_branches' => 1, 'max_users' => 3, 'sort_order' => 1,
                'features' => ['Booking management', 'Vehicle & driver management', 'Invoices & payments', 'Driver mobile app']],
            ['code' => 'growth', 'name' => 'Growth', 'description' => 'For growing fleets that want their own brand.', 'price_monthly' => 1499, 'price_yearly' => 14990,
                'max_vehicles' => 25, 'max_drivers' => 30, 'max_branches' => 1, 'max_users' => 10, 'sort_order' => 2,
                'features' => ['Everything in Starter', 'Advanced reports', 'Branded booking page', 'Expense & settlement tracking']],
            ['code' => 'business', 'name' => 'Business', 'description' => 'For large operators with multiple branches.', 'price_monthly' => 2999, 'price_yearly' => 29990,
                'max_vehicles' => 100, 'max_drivers' => 150, 'max_branches' => 10, 'max_users' => 50, 'sort_order' => 3,
                'features' => ['Everything in Growth', 'Multi-branch', 'Additional integrations', 'Priority support']],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->firstOrCreate(['code' => $plan['code']], $plan + ['trial_days' => 14, 'is_active' => true]);
        }

        foreach (['trial_plan_code' => 'starter', 'trial_days' => '14', 'grace_days' => '7', 'marketplace_commission_percent' => '10', 'default_gst_percent' => '5'] as $key => $value) {
            if (! PlatformSetting::query()->where('key', $key)->exists()) {
                PlatformSetting::setValue($key, $value);
            }
        }

        foreach (VehicleCategory::DEFAULTS as $i => [$name, $slug, $seats, $icon]) {
            if (! VehicleCategory::query()->whereNull('tenant_id')->where('slug', $slug)->exists()) {
                VehicleCategory::create(['name' => $name, 'slug' => $slug, 'seats' => $seats, 'icon' => $icon, 'sort_order' => ($i + 1) * 10]);
            }
        }

        $this->ensureSuperAdmin();
    }

    private function ensureSuperAdmin(): void
    {
        if (User::query()->where('type', UserType::SuperAdmin)->exists()) {
            return;
        }

        $cfg = config('aaochale.super_admin');

        if (empty($cfg['email'])) {
            $this->command?->warn('No super admin exists and SUPER_ADMIN_EMAIL is not set — skipping super admin creation.');

            return;
        }

        $password = $cfg['password'] ?: Str::password(16);

        $admin = new User(['name' => $cfg['name'], 'email' => strtolower($cfg['email']), 'phone' => $cfg['phone'] ?: null, 'password' => $password]);
        $admin->type = UserType::SuperAdmin;
        $admin->status = UserStatus::Active;
        $admin->email_verified_at = now();
        $admin->save();

        $this->command?->info("Super admin created: {$admin->email}".($cfg['password'] ? '' : " / generated password: {$password}"));
    }
}
