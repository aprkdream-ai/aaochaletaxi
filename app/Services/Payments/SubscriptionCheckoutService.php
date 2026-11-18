<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Models\PaymentOrder;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SubscriptionService;
use App\Support\Money;
use App\Support\PlatformSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Online subscription purchase via Razorpay.
 * Fulfilment is idempotent: the checkout callback and the webhook may both arrive — only one activates.
 */
class SubscriptionCheckoutService
{
    public function __construct(
        private RazorpayGateway $gateway,
        private SubscriptionService $subscriptions,
        private TenantContext $context,
        private AuditLogger $audit,
    ) {}

    public function start(Tenant $tenant, SubscriptionPlan $plan, string $cycle, User $user): PaymentOrder
    {
        if (! $this->gateway->isEnabled()) {
            throw ValidationException::withMessages(['plan_id' => __('Online payment is not available right now. Please contact support.')]);
        }
        if (! $plan->is_active || ! in_array($cycle, ['monthly', 'yearly'], true)) {
            throw ValidationException::withMessages(['plan_id' => __('Invalid plan selection.')]);
        }

        $amount = number_format((float) ($cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly), 2, '.', '');
        if ((float) $amount <= 0) {
            throw ValidationException::withMessages(['plan_id' => __('This plan cannot be purchased online.')]);
        }
        $tax = Money::percent($amount, PlatformSettings::decimal('subscription_gst_percent', '18'));
        $total = Money::add($amount, $tax);

        $order = $this->gateway->createOrder((int) bcmul($total, '100', 0), 'sub_'.$tenant->id.'_'.now()->format('ymdHis'), [
            'tenant_id' => $tenant->id, 'tenant' => $tenant->name, 'plan' => $plan->code, 'cycle' => $cycle,
        ]);

        return $this->context->runAs($tenant, function () use ($plan, $cycle, $amount, $tax, $total, $order, $user) {
            $po = new PaymentOrder([
                'purpose' => 'subscription', 'plan_id' => $plan->id, 'billing_cycle' => $cycle,
                'amount' => $amount, 'tax_amount' => $tax, 'total' => $total, 'currency' => 'INR',
                'gateway' => 'razorpay', 'gateway_mode' => $this->gateway->mode(), 'gateway_order_id' => $order['id'],
            ]);
            $po->created_by = $user->id;
            $po->save();

            return $po;
        });
    }

    /** Returns true when this call (not a previous one) fulfilled the order. */
    public function fulfil(string $gatewayOrderId, string $gatewayPaymentId, string $via, ?int $paidAmountPaise = null): bool
    {
        $fulfilled = DB::transaction(function () use ($gatewayOrderId, $gatewayPaymentId, $via, $paidAmountPaise) {
            $order = PaymentOrder::withoutGlobalScopes()->where('gateway_order_id', $gatewayOrderId)->lockForUpdate()->first();

            if (! $order) {
                Log::warning('Razorpay: unknown order', ['order' => $gatewayOrderId]);

                return false;
            }
            if ($order->status === 'paid') {
                return false; // already fulfilled (idempotent)
            }
            if ($paidAmountPaise !== null && $paidAmountPaise !== $order->amountInPaise()) {
                $order->status = 'failed';
                $order->failure_reason = "Amount mismatch: paid {$paidAmountPaise}, expected {$order->amountInPaise()}";
                $order->save();
                Log::error('Razorpay amount mismatch', ['order' => $gatewayOrderId]);

                return false;
            }

            $tenant = Tenant::findOrFail($order->tenant_id);
            $current = $this->subscriptions->currentFor($tenant);
            $start = ($current && $current->billing_cycle !== 'trial' && $current->ends_at->isFuture()) ? 'after_current' : 'now';

            $subscription = $this->subscriptions->activate($tenant, $order->plan, $order->billing_cycle, $start, null, null, __('Online payment :id', ['id' => $gatewayPaymentId]));
            $payment = $this->subscriptions->recordPayment($subscription, $order->amount, PaymentMethod::Razorpay, $gatewayPaymentId, now(), null, 'Razorpay '.$order->gateway_mode);
            $payment->forceFill(['gateway' => 'razorpay', 'gateway_order_id' => $gatewayOrderId, 'gateway_payment_id' => $gatewayPaymentId])->save();

            $order->forceFill([
                'status' => 'paid', 'gateway_payment_id' => $gatewayPaymentId, 'subscription_payment_id' => $payment->id,
                'fulfilled_via' => $via, 'paid_at' => now(),
            ])->save();

            $this->audit->log('payment.online_received', $order, [], ['total' => $order->total, 'via' => $via, 'payment_id' => $gatewayPaymentId], $tenant->id);

            return true;
        });

        return $fulfilled;
    }

    public function markFailed(PaymentOrder $order, string $reason): void
    {
        if ($order->status === 'created') {
            $order->forceFill(['status' => 'failed', 'failure_reason' => mb_substr($reason, 0, 255)])->save();
        }
    }
}
