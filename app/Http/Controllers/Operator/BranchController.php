<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\AuditLogger;
use App\Services\PlanLimitService;
use App\Support\Phone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        return view('operator.branches.index', [
            'branches' => Branch::query()->withCount(['vehicles', 'drivers', 'users'])->orderByDesc('is_head_office')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('operator.branches.form', ['branch' => new Branch(['is_active' => true])]);
    }

    public function store(Request $request, TenantContext $context, PlanLimitService $limits, AuditLogger $audit): RedirectResponse
    {
        $limits->assertCanAdd($context->get(), 'branches');
        $data = $this->validated($request, $context);
        $branch = DB::transaction(function () use ($data) {
            if ($data['is_head_office']) {
                Branch::query()->update(['is_head_office' => false]);
            }

            return Branch::create($data);
        });
        $audit->log('branch.created', $branch, [], $data);

        return redirect()->route('operator.branches.index')->with('success', __('Branch :name added.', ['name' => $branch->name]));
    }

    public function edit(Branch $branch): View
    {
        return view('operator.branches.form', ['branch' => $branch]);
    }

    public function update(Request $request, Branch $branch, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, $context, $branch);
        DB::transaction(function () use ($branch, $data) {
            if ($data['is_head_office']) {
                Branch::query()->where('id', '!=', $branch->id)->update(['is_head_office' => false]);
            }
            $branch->update($data);
        });
        $audit->log('branch.updated', $branch, [], $data);

        return redirect()->route('operator.branches.index')->with('success', __('Branch updated.'));
    }

    public function destroy(Branch $branch, AuditLogger $audit): RedirectResponse
    {
        if ($branch->vehicles()->exists() || $branch->drivers()->exists() || $branch->users()->exists()) {
            return back()->withErrors(['branch' => __('Move vehicles, drivers and staff out of this branch before deleting it.')]);
        }
        $branch->delete();
        $audit->log('branch.deleted', $branch, ['name' => $branch->name]);

        return back()->with('success', __('Branch deleted.'));
    }

    private function validated(Request $request, TenantContext $context, ?Branch $branch = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code'))), 'phone' => Phone::normalize($request->input('phone'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'alpha_dash:ascii', 'max:20', Rule::unique('branches', 'code')->where('tenant_id', $context->id())->ignore($branch)->whereNull('deleted_at')],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'is_head_office' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ], ['phone.regex' => __('Enter a valid 10-digit Indian mobile number.')]);
        $data['is_head_office'] = (bool) ($data['is_head_office'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}
