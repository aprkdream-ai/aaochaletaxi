<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\PaymentOrder;
use App\Services\Payments\BookingCheckoutService;
use App\Services\Payments\RazorpayGateway;
use App\Services\Payments\SubscriptionCheckoutService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/** Server-to-server confirmation (covers users who close the browser before the callback). */
class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, RazorpayGateway $gateway, SubscriptionCheckoutService $checkout, TenantContext $context): Response
    {
        $payload = $request->getContent();

        if (! $gateway->verifyWebhookSignature($payload, (string) $request->header('X-Razorpay-Signature'))) {
            Log::warning('Razorpay webhook: bad signature', ['ip' => $request->ip()]);

            return response('invalid signature', 400);
        }

        $event = $request->json('event');
        $payment = $request->json('payload.payment.entity');

        if (in_array($event, ['payment.captured', 'order.paid'], true) && ($payment['status'] ?? null) === 'captured' && ! empty($payment['order_id'])) {
            $purpose = PaymentOrder::withoutGlobalScopes()->where('gateway_order_id', $payment['order_id'])->value('purpose');
            $context->runWithoutScope(fn () => $purpose === 'booking'
                ? app(BookingCheckoutService::class)->fulfil($payment['order_id'], $payment['id'], 'webhook', (int) $payment['amount'])
                : $checkout->fulfil($payment['order_id'], $payment['id'], 'webhook', (int) $payment['amount']));
        }

        return response('ok', 200);
    }
}
