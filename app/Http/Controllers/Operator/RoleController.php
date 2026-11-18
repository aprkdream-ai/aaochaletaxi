<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(TenantContext $context): View
    {
        return view('operator.roles.index', [
            'roles' => Role::forTenant($context->id())->withCount(['users', 'permissions'])->orderByDesc('is_system')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('operator.roles.form', ['role' => new Role, 'permissions' => $this->catalog(), 'assigned' => []]);
    }

    public function store(Request $request, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $slug = Str::slug($data['name']) ?: 'role';
        if (Role::forTenant($context->id())->where('slug', $slug)->exists()) {
            return back()->withInput()->withErrors(['name' => __('A role with this name already exists.')]);
        }

        $role = new Role(['name' => $data['name'], 'slug' => $slug, 'description' => $data['description'] ?? null, 'is_system' => false]);
        $role->tenant_id = $context->id();
        $role->save();
        $role->permissions()->sync($data['permissions']);
        $audit->log('role.created', $role, [], ['name' => $role->name, 'permissions' => count($data['permissions'])]);

        return redirect()->route('operator.roles.index')->with('success', __('Role created.'));
    }

    public function edit(Role $role, TenantContext $context): View
    {
        $this->ensureEditable($role, $context);

        return view('operator.roles.form', ['role' => $role, 'permissions' => $this->catalog(), 'assigned' => $role->permissions()->pluck('permissions.id')->all()]);
    }

    public function update(Request $request, Role $role, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->ensureEditable($role, $context);
        $data = $this->validated($request);
        $role->fill(['name' => $data['name'], 'description' => $data['description'] ?? null])->save();
        $role->permissions()->sync($data['permissions']);
        $audit->log('role.updated', $role, [], ['permissions' => count($data['permissions'])]);

        return redirect()->route('operator.roles.index')->with('success', __('Role updated.'));
    }

    public function destroy(Role $role, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->ensureEditable($role, $context);
        if ($role->is_system || $role->users()->exists()) {
            return back()->withErrors(['role' => __('Built-in roles and roles assigned to staff cannot be deleted.')]);
        }
        $role->delete();
        $audit->log('role.deleted', null, ['name' => $role->name]);

        return back()->with('success', __('Role deleted.'));
    }

    private function validated(Request $request): array
    {
        $tenantPermissionIds = Permission::query()->where('scope', 'tenant')->pluck('id')->all();

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['integer', 'in:'.implode(',', $tenantPermissionIds)],
        ]);
    }

    private function catalog()
    {
        return Permission::query()->where('scope', 'tenant')->orderBy('id')->get()->groupBy('group');
    }

    /** Roles of other tenants → 404; the Owner role is locked so nobody can lock themselves out. */
    private function ensureEditable(Role $role, TenantContext $context): void
    {
        abort_unless($role->tenant_id === $context->id(), 404);
        abort_if($role->slug === 'owner', 403, __('The Owner role always has full access and cannot be changed.'));
    }
}
