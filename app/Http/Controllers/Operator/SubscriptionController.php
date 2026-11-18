<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Services\Payments\RazorpayGateway;
use App\Services\PlanLimitService;
use App\Support\PlatformSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function show(TenantContext $context, PlanLimitService $limits, RazorpayGateway $gateway): View
    {
        $tenant = $context->get();

        return view('operator.subscription', [
            'tenant' => $tenant,
            'current' => $tenant->usableSubscription(),
            'latest' => Subscription::query()->with('plan')->whereNull('superseded_by')->latest('id')->first(),
            'history' => Subscription::query()->with('plan:id,name')->latest('id')->limit(20)->get(),
            'payments' => SubscriptionPayment::query()->latest('paid_at')->get(),
            'plans' => SubscriptionPlan::query()->active()->get(),
            'usage' => $limits->usage($tenant),
            'onlinePayment' => $gateway->isEnabled(),
            'gstPercent' => PlatformSettings::decimal('subscription_gst_percent', '18'),
        ]);
    }

    public function invoice(SubscriptionPayment $payment): View
    {
        return view('shared.subscription-invoice', ['payment' => $payment->load('subscription.plan')]);
    }
}
