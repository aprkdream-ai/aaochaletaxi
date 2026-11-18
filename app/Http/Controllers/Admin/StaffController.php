<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(): View
    {
        return view('admin.staff.index', [
            'staff' => User::query()->whereIn('type', [UserType::SuperAdmin, UserType::PlatformStaff])->with('roles:id,tenant_id,name')->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.staff.form', ['member' => new User, 'roles' => $this->platformRoles(), 'assigned' => []]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $member = DB::transaction(function () use ($data) {
            $u = new User(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'password' => $data['password']]);
            $u->type = UserType::PlatformStaff;
            $u->status = UserStatus::from($data['status']);
            $u->email_verified_at = now();
            $u->save();
            $u->roles()->sync($data['roles'] ?? []);

            return $u;
        });
        $audit->log('staff.created', $member, [], ['email' => $member->email, 'roles' => $data['roles'] ?? []]);

        return redirect()->route('admin.staff.index')->with('success', __('Staff member added.'));
    }

    public function edit(User $member): View
    {
        $this->ensureEditable($member);

        return view('admin.staff.form', ['member' => $member, 'roles' => $this->platformRoles(), 'assigned' => $member->roles()->pluck('roles.id')->all()]);
    }

    public function update(Request $request, User $member, AuditLogger $audit): RedirectResponse
    {
        $this->ensureEditable($member);
        $data = $this->validated($request, $member);

        if ($member->is($request->user()) && $data['status'] !== UserStatus::Active->value) {
            return back()->withErrors(['status' => __('You cannot deactivate your own account.')]);
        }

        DB::transaction(function () use ($member, $data) {
            $member->fill(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone']]);
            if (filled($data['password'] ?? null)) {
                $member->password = $data['password'];
            }
            $member->status = UserStatus::from($data['status']);
            $member->save();
            if ($member->type === UserType::PlatformStaff) {
                $member->roles()->sync($data['roles'] ?? []);
            }
        });
        $audit->log('staff.updated', $member, [], ['status' => $data['status'], 'roles' => $data['roles'] ?? [], 'password_changed' => filled($data['password'] ?? null)]);

        return redirect()->route('admin.staff.index')->with('success', __('Staff member updated.'));
    }

    private function validated(Request $request, ?User $member = null): array
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone')), 'email' => strtolower(trim((string) $request->input('email')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($member)],
            'phone' => ['nullable', 'regex:/^[6-9]\d{9}$/', Rule::unique('users', 'phone')->ignore($member)],
            'password' => [$member ? 'nullable' : 'required', 'confirmed', Password::min(10)->letters()->numbers()],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')->whereNull('tenant_id')],
        ], ['phone.regex' => __('Enter a valid 10-digit Indian mobile number.')]);
    }

    private function ensureEditable(User $member): void
    {
        abort_unless($member->type->isPlatform(), 404);
        abort_if($member->type === UserType::SuperAdmin && ! request()->user()->isSuperAdmin(), 403);
    }

    private function platformRoles()
    {
        return Role::query()->forTenant(null)->with('permissions:id,label')->orderBy('name')->get();
    }
}
