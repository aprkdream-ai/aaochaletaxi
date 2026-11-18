<?php

namespace App\Services;

use App\Enums\UserType;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;

/** Plan usage limits. Vehicles/drivers/branches counters are added as those modules ship (Phase 3). */
class PlanLimitService
{
    /** @return array<string, int> */
    public function usage(Tenant $tenant): array
    {
        return [
            'users' => $tenant->users()->where('type', UserType::Operator)->count(),
        ];
    }

    /** @return list<string> human-readable reasons the tenant does not fit the plan */
    public function violations(Tenant $tenant, SubscriptionPlan $plan): array
    {
        $usage = $this->usage($tenant);
        $errors = [];

        if ($usage['users'] > $plan->max_users) {
            $errors[] = __(':plan allows :max staff users; :name has :n.', ['plan' => $plan->name, 'max' => $plan->max_users, 'name' => $tenant->name, 'n' => $usage['users']]);
        }

        return $errors;
    }
}
