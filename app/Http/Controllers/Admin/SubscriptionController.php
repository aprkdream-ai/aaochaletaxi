<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(SubscriptionStatus::class)],
            'plan' => ['nullable', 'integer'],
            'expiring' => ['nullable', 'boolean'],
        ]);

        $subscriptions = Subscription::query()->with(['plan:id,name', 'tenant:id,uuid,name,city'])
            ->whereNull('superseded_by')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['plan'] ?? null, fn ($q, $p) => $q->where('plan_id', $p))
            ->when($filters['expiring'] ?? false, fn ($q) => $q->whereIn('status', ['trialing', 'active'])->whereBetween('ends_at', [now(), now()->addDays(7)]))
            ->orderBy('ends_at')->paginate(25)->withQueryString();

        return view('admin.subscriptions.index', [
            'subscriptions' => $subscriptions,
            'filters' => $filters,
            'plans' => SubscriptionPlan::query()->orderBy('sort_order')->get(['id', 'name']),
            'revenueMonth' => SubscriptionPayment::query()->where('status', 'paid')->where('paid_at', '>=', now()->startOfMonth())->sum('total'),
            'payments' => SubscriptionPayment::query()->with('tenant:id,uuid,name')->latest('paid_at')->limit(8)->get(),
        ]);
    }

    public function activate(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'start' => ['required', 'in:now,after_current'],
            'record_payment' => ['nullable', 'boolean'],
            'amount' => ['nullable', 'required_if:record_payment,1', 'numeric', 'min:1'],
            'method' => ['nullable', 'required_if:record_payment,1', Rule::in(array_map(fn ($m) => $m->value, PaymentMethod::manual()))],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        $payment = ($data['record_payment'] ?? false) ? [
            'amount' => $data['amount'], 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'paid_at' => $data['paid_at'] ?? null,
        ] : null;

        $this->service->activate($tenant, SubscriptionPlan::findOrFail($data['plan_id']), $data['billing_cycle'], $data['start'], $request->user(), $payment, $data['notes'] ?? null);

        return back()->with('success', __('Subscription activated for :name.', ['name' => $tenant->name]));
    }

    public function changePlan(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:subscription_plans,id']]);
        $this->service->changePlan($subscription, SubscriptionPlan::findOrFail($data['plan_id']), $request->user());

        return back()->with('success', __('Plan changed.'));
    }

    public function extend(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:366'], 'reason' => ['nullable', 'string', 'max:200']]);
        $this->service->extend($subscription, (int) $data['days'], $request->user(), $data['reason'] ?? null);

        return back()->with('success', __('Subscription extended by :n days.', ['n' => $data['days']]));
    }

    public function cancel(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $this->service->cancel($subscription, $request->user(), $data['reason']);

        return back()->with('success', __('Subscription cancelled.'));
    }

    public function recordPayment(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'method' => ['required', Rule::in(array_map(fn ($m) => $m->value, PaymentMethod::manual()))],
            'reference' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);
        $payment = $this->service->recordPayment($subscription, $data['amount'], PaymentMethod::from($data['method']), $data['reference'] ?? null, $data['paid_at'] ?? null, $request->user(), $data['notes'] ?? null);

        return back()->with('success', __('Payment recorded. Invoice :no issued.', ['no' => $payment->invoice_number]));
    }

    public function invoice(SubscriptionPayment $payment): View
    {
        return view('shared.subscription-invoice', ['payment' => $payment->load('subscription.plan')]);
    }
}
