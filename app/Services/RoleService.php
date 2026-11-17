<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Support\PermissionCatalog;

class RoleService
{
    /** Create/update the permission catalog. Idempotent. */
    public function syncPermissions(): void
    {
        foreach (PermissionCatalog::all() as $key => $meta) {
            Permission::query()->updateOrCreate(['key' => $key], $meta);
        }
    }

    /** Create default roles for a tenant (idempotent; never removes custom permissions). */
    public function seedTenantRoles(Tenant $tenant): void
    {
        $this->seedRoles(PermissionCatalog::defaultTenantRoles(), $tenant->id);
    }

    public function seedPlatformRoles(): void
    {
        $this->seedRoles(PermissionCatalog::defaultPlatformRoles(), null);
    }

    private function seedRoles(array $definitions, ?int $tenantId): void
    {
        $permissionIds = Permission::query()->pluck('id', 'key');

        foreach ($definitions as $slug => $def) {
            $role = Role::query()->forTenant($tenantId)->where('slug', $slug)->first();

            if (! $role) {
                $role = new Role(['name' => $def['name'], 'slug' => $slug, 'description' => $def['description'], 'is_system' => true]);
                $role->tenant_id = $tenantId;
                $role->save();
                $role->permissions()->sync($permissionIds->only($def['permissions'])->values());
            } elseif ($slug === 'owner') {
                // Owner must always hold every tenant permission (new ones added over time).
                $role->permissions()->syncWithoutDetaching($permissionIds->only($def['permissions'])->values());
            }
        }
    }
}
