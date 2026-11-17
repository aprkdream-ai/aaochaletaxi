<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\RoleService;
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

    public function pending(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Pending]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Suspended, 'suspended_at' => now(), 'suspension_reason' => 'Test']);
    }

    /** Seed default roles after creation (requires permissions to be synced). */
    public function configure(): static
    {
        return $this->afterCreating(fn (Tenant $tenant) => app(RoleService::class)->seedTenantRoles($tenant));
    }
}
