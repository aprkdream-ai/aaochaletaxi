<?php

namespace App\Services;

use App\Enums\UserType;
use App\Models\Branch;
use App\Models\Driver;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;

/** Plan usage limits (counts exclude soft-deleted records). */
class PlanLimitService
{
    public const RESOURCES = ['users' => 'max_users', 'vehicles' => 'max_vehicles', 'drivers' => 'max_drivers', 'branches' => 'max_branches'];

    /** @return array<string, int> */
    public function usage(Tenant $tenant): array
    {
        $count = fn (string $model) => $model::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deleted_at')->count();

        return [
            'users' => $tenant->users()->where('type', UserType::Operator)->count(),
            'vehicles' => $count(Vehicle::class),
            'drivers' => $count(Driver::class),
            'branches' => $count(Branch::class),
        ];
    }

    /** @return list<string> */
    public function violations(Tenant $tenant, SubscriptionPlan $plan): array
    {
        $usage = $this->usage($tenant);
        $errors = [];
        $labels = ['users' => __('staff users'), 'vehicles' => __('vehicles'), 'drivers' => __('drivers'), 'branches' => __('branches')];

        foreach (self::RESOURCES as $resource => $column) {
            if ($usage[$resource] > $plan->{$column}) {
                $errors[] = __(':plan allows :max :what; :name has :n.', ['plan' => $plan->name, 'max' => $plan->{$column}, 'what' => $labels[$resource], 'name' => $tenant->name, 'n' => $usage[$resource]]);
            }
        }

        return $errors;
    }

    /** Throw a friendly validation error when adding one more would exceed the current plan. */
    public function assertCanAdd(Tenant $tenant, string $resource, string $field = 'limit'): void
    {
        $plan = $tenant->usableSubscription()?->plan;
        if (! $plan) {
            throw ValidationException::withMessages([$field => __('An active subscription is required.')]);
        }
        $max = (int) $plan->{self::RESOURCES[$resource]};
        if ($this->usage($tenant)[$resource] >= $max) {
            throw ValidationException::withMessages([$field => __('Your :plan plan allows up to :max :what. Upgrade your plan to add more.', [
                'plan' => $plan->name, 'max' => $max, 'what' => __($resource),
            ])]);
        }
    }
}
