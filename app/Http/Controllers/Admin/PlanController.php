<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans.index', [
            'plans' => SubscriptionPlan::query()->withCount(['subscriptions as active_count' => fn ($q) => $q->withoutGlobalScopes()->whereIn('status', ['trialing', 'active', 'grace'])->whereNull('superseded_by')])->orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.plans.form', ['plan' => new SubscriptionPlan(['trial_days' => 14, 'is_active' => true, 'max_branches' => 1, 'max_users' => 5])]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $plan = SubscriptionPlan::create($data);
        $audit->log('plan.created', $plan, [], $data);

        return redirect()->route('admin.plans.index')->with('success', __('Plan :name created.', ['name' => $plan->name]));
    }

    public function edit(SubscriptionPlan $plan): View
    {
        return view('admin.plans.form', ['plan' => $plan]);
    }

    public function update(Request $request, SubscriptionPlan $plan, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, $plan);
        unset($data['code']); // code is immutable (referenced by settings)
        $old = $plan->only(array_keys($data));
        $plan->update($data);
        $audit->log('plan.updated', $plan, $old, $data);

        return redirect()->route('admin.plans.index')->with('success', __('Plan :name updated. Existing subscriptions keep their price until renewal.', ['name' => $plan->name]));
    }

    private function validated(Request $request, ?SubscriptionPlan $plan = null): array
    {
        $data = $request->validate([
            'code' => [$plan ? 'nullable' : 'required', 'alpha_dash:ascii', 'max:30', Rule::unique('subscription_plans', 'code')->ignore($plan)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'price_monthly' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'price_yearly' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'max_vehicles' => ['required', 'integer', 'min:1', 'max:100000'],
            'max_drivers' => ['required', 'integer', 'min:1', 'max:100000'],
            'max_branches' => ['required', 'integer', 'min:1', 'max:1000'],
            'max_users' => ['required', 'integer', 'min:1', 'max:10000'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:90'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'features_text' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
            'advanced_reports' => ['nullable', 'boolean'],
        ]);

        $data['features'] = collect(preg_split('/\r\n|\r|\n/', (string) ($data['features_text'] ?? '')))->map(fn ($l) => trim($l))->filter()->take(20)->values()->all();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['advanced_reports'] = (bool) ($data['advanced_reports'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        if (isset($data['code'])) {
            $data['code'] = strtolower($data['code']);
        }
        unset($data['features_text']);

        return $data;
    }
}
