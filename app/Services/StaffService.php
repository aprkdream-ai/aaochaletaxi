<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffService
{
    public function __construct(private PlanLimitService $limits, private AuditLogger $audit) {}

    public function create(Tenant $tenant, array $data): User
    {
        $this->limits->assertCanAdd($tenant, 'users');
        $roleIds = $this->tenantRoleIds($tenant, $data['roles'] ?? []);

        return DB::transaction(function () use ($tenant, $data, $roleIds) {
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null, 'password' => $data['password']]);
            $user->type = UserType::Operator;
            $user->tenant_id = $tenant->id;
            $user->branch_id = $data['branch_id'] ?? null;
            $user->status = UserStatus::Active;
            $user->save();
            $user->roles()->sync($roleIds);
            $this->audit->log('staff.created', $user, [], ['email' => $user->email, 'roles' => $roleIds], $tenant->id);

            return $user;
        });
    }

    public function update(Tenant $tenant, User $member, array $data, User $actor): User
    {
        $roleIds = $this->tenantRoleIds($tenant, $data['roles'] ?? []);
        $ownerRoleId = Role::forTenant($tenant->id)->where('slug', 'owner')->value('id');
        $losingOwner = $member->roles()->where('roles.id', $ownerRoleId)->exists()
            && (! in_array($ownerRoleId, $roleIds, true) || $data['status'] !== UserStatus::Active->value);

        if ($losingOwner && $this->activeOwnerCount($tenant, $ownerRoleId) <= 1) {
            throw ValidationException::withMessages(['roles' => __('At least one active Owner is required.')]);
        }
        if ($member->is($actor) && $data['status'] !== UserStatus::Active->value) {
            throw ValidationException::withMessages(['status' => __('You cannot deactivate your own account.')]);
        }

        return DB::transaction(function () use ($tenant, $member, $data, $roleIds) {
            $member->fill(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
            if (filled($data['password'] ?? null)) {
                $member->password = $data['password'];
            }
            $member->branch_id = $data['branch_id'] ?? null;
            $member->status = UserStatus::from($data['status']);
            $member->save();
            $member->roles()->sync($roleIds);
            $member->flushPermissionCache();
            $this->audit->log('staff.updated', $member, [], ['roles' => $roleIds, 'status' => $data['status']], $tenant->id);

            return $member;
        });
    }

    /** Only roles of this tenant can ever be attached. @return list<int> */
    private function tenantRoleIds(Tenant $tenant, array $ids): array
    {
        $valid = Role::forTenant($tenant->id)->whereIn('id', $ids)->pluck('id')->all();
        if (count($valid) !== count(array_unique($ids)) || $valid === []) {
            throw ValidationException::withMessages(['roles' => __('Select at least one valid role.')]);
        }

        return array_map('intval', $valid);
    }

    private function activeOwnerCount(Tenant $tenant, ?int $ownerRoleId): int
    {
        return User::query()->where('tenant_id', $tenant->id)->where('status', UserStatus::Active->value)
            ->whereIn('id', DB::table('role_user')->where('role_id', $ownerRoleId)->select('user_id'))->count();
    }
}
