<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TenantOnboardingService
{
    public function __construct(
        private RoleService $roles,
        private AuditLogger $audit,
        private TenantContext $context,
    ) {}

    /**
     * Register a new operator business: tenant (pending) + owner user + default roles + trial.
     *
     * @param  array{business_name:string, city:string, state?:?string, name:string, email:string, phone:string, password:string}  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $tenant = new Tenant([
                'name' => $data['business_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'city' => $data['city'],
                'state' => $data['state'] ?? null,
            ]);
            $tenant->slug = Tenant::uniqueSlug($data['business_name']);
            $tenant->save();

            $this->roles->seedTenantRoles($tenant);

            $owner = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
            ]);
            $owner->tenant_id = $tenant->id;
            $owner->type = UserType::Operator;
            $owner->status = UserStatus::Active;
            $owner->save();

            $ownerRole = Role::query()->forTenant($tenant->id)->where('slug', 'owner')->firstOrFail();
            $owner->roles()->attach($ownerRole->id);

            $this->startTrial($tenant);

            $this->audit->log('tenant.registered', $tenant, [], ['name' => $tenant->name, 'slug' => $tenant->slug], $tenant->id);

            return $owner;
        });
    }

    public function startTrial(Tenant $tenant): Subscription
    {
        $planCode = PlatformSetting::getValue('trial_plan_code', 'starter');
        $plan = SubscriptionPlan::query()->where('code', $planCode)->first()
            ?? SubscriptionPlan::query()->active()->first()
            ?? throw new RuntimeException('No subscription plans configured. Run the database seeder.');

        $days = (int) PlatformSetting::getValue('trial_days', $plan->trial_days ?: 14);

        return $this->context->runAs($tenant, fn () => Subscription::create([
            'plan_id' => $plan->id,
            'billing_cycle' => 'trial',
            'status' => SubscriptionStatus::Trialing,
            'starts_at' => now(),
            'ends_at' => now()->addDays($days)->endOfDay(),
            'price' => 0,
        ]));
    }
}
