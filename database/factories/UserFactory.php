<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => (string) fake()->unique()->numberBetween(6000000000, 9999999999),
            'type' => UserType::Customer,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => ['type' => UserType::SuperAdmin, 'tenant_id' => null]);
    }

    public function platformStaff(): static
    {
        return $this->state(fn () => ['type' => UserType::PlatformStaff, 'tenant_id' => null]);
    }

    public function customer(): static
    {
        return $this->state(fn () => ['type' => UserType::Customer, 'tenant_id' => null]);
    }

    public function operatorOf(Tenant $tenant, ?string $roleSlug = null): static
    {
        return $this->state(fn () => ['type' => UserType::Operator, 'tenant_id' => $tenant->id])
            ->afterCreating(function (User $user) use ($tenant, $roleSlug) {
                if ($roleSlug) {
                    $user->roles()->attach(Role::query()->forTenant($tenant->id)->where('slug', $roleSlug)->value('id'));
                }
            });
    }

    public function driverOf(Tenant $tenant): static
    {
        return $this->state(fn () => ['type' => UserType::Driver, 'tenant_id' => $tenant->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Blocked]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
