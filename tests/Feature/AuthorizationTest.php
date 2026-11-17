<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RoleService;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    public function test_panels_are_restricted_by_user_type(): void
    {
        $customer = User::factory()->customer()->create();
        $this->actingAs($customer)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($customer)->get(route('operator.dashboard'))->assertForbidden();
        $this->actingAs($customer)->get(route('driver.home'))->assertForbidden();
        $this->actingAs($customer)->get(route('account.dashboard'))->assertOk();

        $owner = $this->owner();
        $this->actingAs($owner)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($owner)->get(route('account.dashboard'))->assertForbidden();
    }

    public function test_super_admin_has_full_access(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->assertTrue($admin->hasPermission('anything.at.all'));
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('admin.tenants.index'))->assertOk();
    }

    public function test_platform_staff_limited_by_role(): void
    {
        app(RoleService::class)->seedPlatformRoles();
        $staff = User::factory()->platformStaff()->create();
        $staff->roles()->attach(Role::forTenant(null)->where('slug', 'platform-support')->value('id'));
        $tenant = Tenant::factory()->pending()->create();

        $this->actingAs($staff)->get(route('admin.tenants.index'))->assertOk();
        $this->actingAs($staff)->get(route('admin.tenants.show', $tenant))->assertOk()->assertDontSee('Approve &amp; activate', false);
        $this->actingAs($staff)->post(route('admin.tenants.approve', $tenant))->assertForbidden();
        $this->assertSame('pending', $tenant->fresh()->status->value);
    }

    public function test_staff_without_permission_is_forbidden(): void
    {
        $tenant = Tenant::factory()->create();
        $noRole = User::factory()->operatorOf($tenant)->create();
        $this->actingAs($noRole)->get(route('operator.dashboard'))->assertForbidden();

        $accountant = User::factory()->operatorOf($tenant, 'accountant')->create();
        $this->assertTrue($accountant->hasPermission('invoices.manage'));
        $this->assertFalse($accountant->hasPermission('vehicles.manage'));
        $this->actingAs($accountant)->get(route('operator.dashboard'))->assertOk();
    }

    public function test_roles_from_another_tenant_are_ignored(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();
        $user = User::factory()->operatorOf($a)->create();
        // Attach B's owner role directly (simulating a bad pivot row).
        $user->roles()->attach(Role::forTenant($b->id)->where('slug', 'owner')->value('id'));

        $this->assertFalse($user->fresh()->hasPermission('vehicles.manage'));
    }

    public function test_gate_maps_dotted_abilities_to_permissions(): void
    {
        $owner = $this->owner();
        $this->assertTrue($owner->can('vehicles.manage'));
        $this->assertFalse($owner->can('admin.tenants.manage'));
    }

    public function test_suspended_tenant_users_are_blocked(): void
    {
        $tenant = Tenant::factory()->suspended()->create();
        $owner = $this->owner($tenant);

        $this->actingAs($owner)->get(route('operator.dashboard'))->assertRedirect(route('operator.status'));
        $this->actingAs($owner)->get(route('operator.status'))->assertOk()->assertSee('suspended');

        $driver = User::factory()->driverOf($tenant)->create();
        $this->actingAs($driver)->get(route('driver.home'))->assertRedirect(route('operator.status'));
    }

    public function test_active_driver_sees_home(): void
    {
        $tenant = Tenant::factory()->create();
        $driver = User::factory()->driverOf($tenant)->create();
        $this->actingAs($driver)->get(route('driver.home'))->assertOk()->assertSee($tenant->name);
    }
}
