<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Production-safe, idempotent: sets the Taxi + Fleet add-on price on existing plans (Phase 8A).
 * Only touches plans whose add-on is still ₹0 on both cycles, so prices edited in Admin → Plans are never overwritten.
 *
 * Run on the server once after deploying Phase 8A:
 *   php artisan db:seed --class=ModuleAddonPriceSeeder --force
 */
class ModuleAddonPriceSeeder extends Seeder
{
    /** plan code => [monthly, yearly] (excl. GST) */
    public const PRICES = [
        'starter' => [399, 3990],
        'growth' => [699, 6990],
        'business' => [1299, 12990],
    ];

    public function run(): void
    {
        foreach (self::PRICES as $code => [$monthly, $yearly]) {
            $plan = SubscriptionPlan::query()->where('code', $code)->first();

            if (! $plan) {
                $this->command?->warn("Plan '{$code}' not found — skipped.");

                continue;
            }
            if ((float) $plan->addon_price_monthly > 0 || (float) $plan->addon_price_yearly > 0) {
                $this->command?->line("Plan '{$code}' already has an add-on price — left unchanged.");

                continue;
            }

            $plan->forceFill(['addon_price_monthly' => $monthly, 'addon_price_yearly' => $yearly])->save();
            $this->command?->info("Plan '{$code}': add-on set to ₹{$monthly}/month, ₹{$yearly}/year.");
        }
    }
}
