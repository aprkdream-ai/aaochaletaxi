<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BusinessType;
use App\Enums\KycStatus;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\KycService;
use App\Services\PlanLimitService;
use App\Services\SubscriptionService;
use App\Services\TenantAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function __construct(private TenantAdminService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(TenantStatus::class)],
            'type' => ['nullable', Rule::enum(BusinessType::class)],
        ]);

        $tenants = Tenant::query()
            ->with(['latestSubscription.plan:id,name'])
            ->withCount('users')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('business_type', $t))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('city', 'like', $like));
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.tenants.index', ['tenants' => $tenants, 'filters' => $filters, 'statuses' => TenantStatus::cases(), 'types' => BusinessType::cases()]);
    }

    public function show(Tenant $tenant, SubscriptionService $subscriptions, PlanLimitService $limits): View
    {
        $tenant->load([
            'users' => fn ($q) => $q->with('roles:id,tenant_id,name,slug')->orderBy('id'),
            'subscriptions.plan:id,name',
            'documents.reviewer:id,name',
            'subscriptionPayments',
        ]);

        return view('admin.tenants.show', [
            'tenant' => $tenant,
            'current' => $subscriptions->currentFor($tenant),
            'plans' => SubscriptionPlan::query()->active()->get(),
            'usage' => $limits->usage($tenant),
            'kycRequired' => KycService::requiredStatus($tenant),
            'activity' => AuditLog::query()->with('user:id,name')->where('tenant_id', $tenant->id)->latest('id')->limit(15)->get(),
            'kycStatuses' => KycStatus::cases(),
            'types' => BusinessType::cases(),
        ]);
    }

    public function approve(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->service->approve($tenant, $request->user());

        return back()->with('success', "{$tenant->name} approved and activated.");
    }

    public function reject(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->service->reject($tenant, $data['reason']);

        return back()->with('success', "{$tenant->name} rejected.");
    }

    public function suspend(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->service->suspend($tenant, $data['reason']);

        return back()->with('success', "{$tenant->name} suspended.");
    }

    public function reactivate(Tenant $tenant): RedirectResponse
    {
        $this->service->reactivate($tenant);

        return back()->with('success', "{$tenant->name} reactivated.");
    }

    public function businessType(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'business_type' => ['required', Rule::enum(BusinessType::class)],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);
        $this->service->setBusinessType($tenant, BusinessType::from($data['business_type']), $data['reason'] ?? null);

        return back()->with('success', __('Business type changed to :type.', ['type' => $tenant->businessType()->label()]));
    }

    public function kyc(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'kyc_status' => ['required', Rule::enum(KycStatus::class)],
            'kyc_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->service->setKycStatus($tenant, KycStatus::from($data['kyc_status']), $data['kyc_notes'] ?? null);

        return back()->with('success', 'KYC status updated.');
    }
}
