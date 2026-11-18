<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\PaymentOrder;
use App\Models\SubscriptionPlan;
use App\Services\Payments\RazorpayGateway;
use App\Services\Payments\SubscriptionCheckoutService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(private SubscriptionCheckoutService $checkout, private RazorpayGateway $gateway) {}

    /** Creates a Razorpay order and returns Checkout options (JSON for the in-page modal). */
    public function store(Request $request, TenantContext $context): JsonResponse
    {
        $data = $request->validate(['plan_id' => ['required', 'integer'], 'billing_cycle' => ['required', 'in:monthly,yearly']]);
        $plan = SubscriptionPlan::query()->active()->findOrFail($data['plan_id']);
        $tenant = $context->get();
        $user = $request->user();

        try {
            $order = $this->checkout->start($tenant, $plan, $data['billing_cycle'], $user);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('Could not start payment. Please try again or contact support.')], 502);
        }

        return response()->json([
            'key' => $this->gateway->keyId(),
            'order_id' => $order->gateway_order_id,
            'amount' => $order->amountInPaise(),
            'currency' => 'INR',
            'name' => 'AaoChaleTaxi',
            'description' => __(':plan plan (:cycle)', ['plan' => $plan->name, 'cycle' => $data['billing_cycle']]),
            'prefill' => ['name' => $user->name, 'email' => $user->email, 'contact' => $user->phone ? '+91'.$user->phone : null],
            'notes' => ['tenant' => $tenant->name],
            'theme' => ['color' => '#ffb703'],
            'verify_url' => route('operator.checkout.verify'),
        ]);
    }

    /** Checkout success handler posts here; signature must match before we fulfil. */
    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:100'],
            'razorpay_payment_id' => ['required', 'string', 'max:100'],
            'razorpay_signature' => ['required', 'string', 'max:200'],
        ]);

        // Scoped query: an operator can only complete their own orders.
        $order = PaymentOrder::query()->where('gateway_order_id', $data['razorpay_order_id'])->firstOrFail();

        if (! $this->gateway->verifyPaymentSignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            $this->checkout->markFailed($order, 'Invalid checkout signature');

            return redirect()->route('operator.subscription')->with('error', __('Payment could not be verified. If money was deducted it will be reconciled automatically or refunded — contact support with payment ID :id.', ['id' => $data['razorpay_payment_id']]));
        }

        $this->checkout->fulfil($data['razorpay_order_id'], $data['razorpay_payment_id'], 'checkout');

        return redirect()->route('operator.subscription')->with('success', __('Payment successful! Your :plan plan is active. Invoice is available below.', ['plan' => $order->plan->name]));
    }
}
