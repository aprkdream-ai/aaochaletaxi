<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Fleet module: departments / cost centres that request trips. Deactivate instead of delete (trips keep history). */
class DepartmentController extends Controller
{
    public function index(): View
    {
        return view('operator.departments.index', [
            'departments' => Department::query()->withCount(['trips' => fn ($q) => $q->where('status', 'completed')])->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $dept = Department::create($this->validated($request));
        $audit->log('department.created', $dept, [], ['name' => $dept->name]);

        return back()->with('success', __('Department added.'));
    }

    public function update(Request $request, Department $department, AuditLogger $audit): RedirectResponse
    {
        $old = $department->only(['name', 'code', 'is_active']);
        $department->update($this->validated($request, $department));
        $audit->log('department.updated', $department, $old, $department->only(['name', 'code', 'is_active']));

        return back()->with('success', __('Department updated.'));
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->where('tenant_id', app(TenantContext::class)->id())->ignore($department)],
            'code' => ['nullable', 'string', 'max:20'],
            'head_name' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? ! $department);

        return $data;
    }
}
