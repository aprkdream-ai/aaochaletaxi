<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\RoleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Tenant> */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' Travels';

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'email' => fake()->unique()->companyEmail(),
            'phone' => (string) fake()->numberBetween(6000000000, 9999999999),
            'city' => fake()->randomElement(['Ahmedabad', 'Pune', 'Jaipur', 'Indore', 'Lucknow']),
            'status' => TenantStatus::Active,
        ];
    }

    public function businessType(\App\Enums\BusinessType $type): static
    {
        return $this->state(fn () => ['business_type' => $type]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Pending]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Suspended, 'suspended_at' => now(), 'suspension_reason' => 'Test']);
    }

    private bool $withSubscription = true;

    public function withoutSubscription(): static
    {
        $clone = clone $this;
        $clone->withSubscription = false;

        return $clone;
    }

    /** Seed default roles and a trial subscription, mirroring real onboarding. */
    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant) {
            app(RoleService::class)->seedTenantRoles($tenant);

            if ($this->withSubscription && $plan = SubscriptionPlan::query()->where('code', 'starter')->first()) {
                app(TenantContext::class)->runAs($tenant, fn () => Subscription::create([
                    'plan_id' => $plan->id, 'billing_cycle' => 'trial', 'status' => SubscriptionStatus::Trialing,
                    'starts_at' => now(), 'ends_at' => now()->addDays(14), 'price' => 0,
                ]));
            }
        });
    }
}
