<?php

namespace App\Http\Controllers\Operator;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\StaffService;
use App\Support\Phone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(private StaffService $service) {}

    public function index(TenantContext $context): View
    {
        return view('operator.staff.index', [
            'staff' => User::query()->where('tenant_id', $context->id())->where('type', UserType::Operator)
                ->with(['roles:id,tenant_id,name', 'branch:id,name'])->orderBy('name')->get(),
        ]);
    }

    public function create(TenantContext $context): View
    {
        return view('operator.staff.form', ['member' => new User, 'roles' => $this->roles($context), 'branches' => Branch::query()->active()->get(), 'assigned' => []]);
    }

    public function store(Request $request, TenantContext $context): RedirectResponse
    {
        $this->service->create($context->get(), $this->validated($request));

        return redirect()->route('operator.staff.index')->with('success', __('Staff member added. Share their login email and password with them.'));
    }

    public function edit(User $member, TenantContext $context): View
    {
        $this->ensureOwn($member, $context);

        return view('operator.staff.form', ['member' => $member, 'roles' => $this->roles($context), 'branches' => Branch::query()->active()->get(), 'assigned' => $member->roles()->pluck('roles.id')->all()]);
    }

    public function update(Request $request, User $member, TenantContext $context): RedirectResponse
    {
        $this->ensureOwn($member, $context);
        $this->service->update($context->get(), $member, $this->validated($request, $member), $request->user());

        return redirect()->route('operator.staff.index')->with('success', __('Staff member updated.'));
    }

    private function validated(Request $request, ?User $member = null): array
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone')), 'email' => strtolower(trim((string) $request->input('email')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($member)],
            'phone' => ['nullable', 'regex:/^[6-9]\d{9}$/', Rule::unique('users', 'phone')->ignore($member)],
            'password' => [$member ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', app(TenantContext::class)->id())->whereNull('deleted_at')],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer'],
            'status' => [$member ? 'required' : 'nullable', Rule::enum(UserStatus::class)],
        ], ['phone.regex' => __('Enter a valid 10-digit Indian mobile number.')]) + ['status' => UserStatus::Active->value];
    }

    private function roles(TenantContext $context)
    {
        return Role::forTenant($context->id())->withCount('permissions')->orderByDesc('is_system')->orderBy('name')->get();
    }

    private function ensureOwn(User $member, TenantContext $context): void
    {
        abort_unless($member->tenant_id === $context->id() && $member->type === UserType::Operator, 404);
    }
}
