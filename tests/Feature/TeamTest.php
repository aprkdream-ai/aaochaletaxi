<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class TeamTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->owner = $this->owner($this->tenant);
    }

    private function roleId(string $slug, ?Tenant $t = null): int
    {
        return Role::forTenant(($t ?? $this->tenant)->id)->where('slug', $slug)->value('id');
    }

    public function test_staff_create_login_and_limit(): void
    {
        $this->actingAs($this->owner)->post(route('operator.staff.store'), ['name' => 'Neha', 'email' => 'NEHA@shiv.test', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'roles' => [$this->roleId('booking-manager')]])
            ->assertRedirect(route('operator.staff.index'));
        $neha = User::where('email', 'neha@shiv.test')->sole();
        $this->assertSame($this->tenant->id, $neha->tenant_id);
        $this->assertTrue($neha->hasPermission('bookings.create'));
        $this->assertFalse($neha->hasPermission('staff.manage'));

        // Starter allows 3 users: owner + Neha + one more.
        $this->actingAs($this->owner)->post(route('operator.staff.store'), ['name' => 'A', 'email' => 'a@shiv.test', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'roles' => [$this->roleId('accountant')]])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('operator.staff.store'), ['name' => 'B', 'email' => 'b@shiv.test', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'roles' => [$this->roleId('accountant')]])->assertSessionHasErrors('limit');
    }

    public function test_cannot_assign_foreign_roles_or_edit_foreign_staff(): void
    {
        $other = Tenant::factory()->create();
        $this->actingAs($this->owner)->post(route('operator.staff.store'), ['name' => 'X', 'email' => 'x@shiv.test', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'roles' => [$this->roleId('owner', $other)]])
            ->assertSessionHasErrors('roles');
        $foreignStaff = $this->owner($other);
        $this->actingAs($this->owner)->get(route('operator.staff.edit', $foreignStaff))->assertNotFound();
        $this->actingAs($this->owner)->put(route('operator.staff.update', $foreignStaff), ['name' => 'hacked', 'email' => 'h@h.test', 'roles' => [$this->roleId('owner')], 'status' => 'active'])->assertNotFound();
    }

    public function test_last_owner_is_protected(): void
    {
        $this->actingAs($this->owner)->put(route('operator.staff.update', $this->owner), ['name' => 'Me', 'email' => $this->owner->email, 'roles' => [$this->roleId('accountant')], 'status' => 'active'])
            ->assertSessionHasErrors('roles');
        $this->assertTrue($this->owner->fresh()->hasRole('owner'));
    }

    public function test_custom_role_editor(): void
    {
        $vehiclesView = Permission::where('key', 'vehicles.view')->value('id');
        $platformPerm = Permission::where('key', 'admin.tenants.manage')->value('id');

        $this->actingAs($this->owner)->post(route('operator.roles.store'), ['name' => 'Dispatcher', 'permissions' => [$vehiclesView, $platformPerm]])->assertSessionHasErrors('permissions.1');
        $this->actingAs($this->owner)->post(route('operator.roles.store'), ['name' => 'Dispatcher', 'permissions' => [$vehiclesView]])->assertRedirect(route('operator.roles.index'));
        $role = Role::forTenant($this->tenant->id)->where('slug', 'dispatcher')->sole();
        $this->assertFalse($role->is_system);

        $this->actingAs($this->owner)->get(route('operator.roles.edit', $this->roleId('owner')))->assertForbidden();
        $this->actingAs($this->owner)->put(route('operator.roles.update', $this->roleId('owner')), ['name' => 'Owner', 'permissions' => [$vehiclesView]])->assertForbidden();
        $this->assertTrue($this->owner->fresh()->hasPermission('staff.manage'));

        $otherRole = $this->roleId('accountant', Tenant::factory()->create());
        $this->actingAs($this->owner)->get(route('operator.roles.edit', $otherRole))->assertNotFound();

        $this->actingAs($this->owner)->delete(route('operator.roles.destroy', $role))->assertSessionHas('success');
        $this->actingAs($this->owner)->delete(route('operator.roles.destroy', $this->roleId('accountant')))->assertSessionHasErrors('role');
    }

    public function test_branches_crud_head_office_and_limit(): void
    {
        $this->actingAs($this->owner)->post(route('operator.branches.store'), ['name' => 'Ahmedabad HO', 'code' => 'amd', 'city' => 'Ahmedabad', 'is_head_office' => 1, 'is_active' => 1])->assertSessionHasNoErrors();
        $b = Branch::withoutGlobalScopes()->sole();
        $this->assertSame('AMD', $b->code);
        // Starter: 1 branch.
        $this->actingAs($this->owner)->post(route('operator.branches.store'), ['name' => 'Surat', 'code' => 'STV', 'city' => 'Surat', 'is_active' => 1])->assertSessionHasErrors('limit');

        $staff = User::factory()->operatorOf($this->tenant, 'accountant')->create(['branch_id' => $b->id]);
        $this->actingAs($this->owner)->delete(route('operator.branches.destroy', $b))->assertSessionHasErrors('branch');
        $staff->forceFill(['branch_id' => null])->save();
        $this->actingAs($this->owner)->delete(route('operator.branches.destroy', $b))->assertSessionHas('success');
    }

    public function test_all_operator_pages_render(): void
    {
        foreach (['operator.dashboard', 'operator.vehicles.index', 'operator.vehicles.create', 'operator.drivers.index', 'operator.drivers.create', 'operator.staff.index', 'operator.staff.create', 'operator.roles.index', 'operator.roles.create', 'operator.branches.index', 'operator.branches.create'] as $r) {
            $this->actingAs($this->owner)->get(route($r))->assertOk();
        }
    }
}
