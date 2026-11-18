<?php

namespace Tests\Feature;

use App\Models\PaymentOrder;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RazorpayCheckoutTest extends TestCase
{
    private const SECRET = 'test_key_secret';

    private const WEBHOOK_SECRET = 'whsec_123';

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        PlatformSetting::setValue('razorpay_mode', 'test');
        PlatformSetting::setValue('razorpay_key_id', 'rzp_test_ABC123');
        PlatformSetting::setValue('razorpay_key_secret', self::SECRET, true);
        PlatformSetting::setValue('razorpay_webhook_secret', self::WEBHOOK_SECRET, true);
        $this->tenant = Tenant::factory()->create();
        $this->owner = $this->owner($this->tenant);
    }

    private function startCheckout(string $plan = 'growth', string $cycle = 'monthly', bool $fake = true)
    {
        if ($fake) {
            Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_TEST1', 'amount' => 176882, 'currency' => 'INR', 'status' => 'created'])]);
        }

        return $this->actingAs($this->owner)->postJson(route('operator.checkout.store'), ['plan_id' => SubscriptionPlan::where('code', $plan)->value('id'), 'billing_cycle' => $cycle]);
    }

    private function signature(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', $orderId.'|'.$paymentId, self::SECRET);
    }

    private function webhook(array $payment, ?string $signature = null)
    {
        $body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => $payment]]]);

        return $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, self::WEBHOOK_SECRET),
        ], $body);
    }

    public function test_checkout_creates_order_with_gst(): void
    {
        $this->actingAs($this->owner)->get(route('operator.subscription'))->assertOk()->assertSee('Secure online payment')->assertSee('Choose &amp; pay', false);

        $this->startCheckout()->assertOk()->assertJson(['key' => 'rzp_test_ABC123', 'order_id' => 'order_TEST1', 'amount' => 176882, 'currency' => 'INR']);

        $order = PaymentOrder::withoutGlobalScopes()->sole();
        $this->assertSame('1499.00', $order->amount);
        $this->assertSame('1768.82', $order->total);
        $this->assertSame($this->tenant->id, $order->tenant_id);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.razorpay.com/v1/orders' && $r['amount'] === 176882 && $r->hasHeader('Authorization'));
    }

    public function test_verified_payment_activates_plan_once_even_with_webhook(): void
    {
        $this->startCheckout();
        $this->actingAs($this->owner)->post(route('operator.checkout.verify'), [
            'razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_XYZ', 'razorpay_signature' => $this->signature('order_TEST1', 'pay_XYZ'),
        ])->assertRedirect(route('operator.subscription'))->assertSessionHas('success');

        $current = $this->tenant->usableSubscription();
        $this->assertSame('growth', $current->plan->code);
        $this->assertSame('active', $current->status->value);

        $payment = SubscriptionPayment::withoutGlobalScopes()->sole();
        $this->assertSame('razorpay', $payment->method->value);
        $this->assertSame('pay_XYZ', $payment->gateway_payment_id);
        $this->assertSame('1768.82', $payment->total);

        // Duplicate callback + webhook must not create a second payment.
        $this->actingAs($this->owner)->post(route('operator.checkout.verify'), ['razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_XYZ', 'razorpay_signature' => $this->signature('order_TEST1', 'pay_XYZ')]);
        $this->webhook(['id' => 'pay_XYZ', 'order_id' => 'order_TEST1', 'amount' => 176882, 'status' => 'captured'])->assertOk();
        $this->assertSame(1, SubscriptionPayment::withoutGlobalScopes()->count());
        $this->assertSame('checkout', PaymentOrder::withoutGlobalScopes()->sole()->fulfilled_via);
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $this->startCheckout();
        $this->actingAs($this->owner)->post(route('operator.checkout.verify'), ['razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_XYZ', 'razorpay_signature' => 'forged'])
            ->assertSessionHas('error');
        $this->assertSame('failed', PaymentOrder::withoutGlobalScopes()->sole()->status);
        $this->assertSame(0, SubscriptionPayment::withoutGlobalScopes()->count());
        $this->assertSame('trialing', $this->tenant->usableSubscription()->status->value);
    }

    public function test_webhook_fulfils_when_browser_closed_and_checks_amount(): void
    {
        $this->startCheckout();
        $this->webhook(['id' => 'pay_1', 'order_id' => 'order_TEST1', 'amount' => 176882, 'status' => 'captured'], 'bad')->assertStatus(400);
        $this->assertSame(0, SubscriptionPayment::withoutGlobalScopes()->count());

        $this->webhook(['id' => 'pay_1', 'order_id' => 'order_TEST1', 'amount' => 176882, 'status' => 'captured'])->assertOk();
        $this->assertSame('webhook', PaymentOrder::withoutGlobalScopes()->sole()->fulfilled_via);
        $this->assertSame('growth', $this->tenant->usableSubscription()->plan->code);
    }

    public function test_webhook_amount_mismatch_does_not_activate(): void
    {
        $this->startCheckout();
        $this->webhook(['id' => 'pay_2', 'order_id' => 'order_TEST1', 'amount' => 100, 'status' => 'captured'])->assertOk();
        $this->assertSame('failed', PaymentOrder::withoutGlobalScopes()->sole()->status);
        $this->assertSame(0, SubscriptionPayment::withoutGlobalScopes()->count());
    }

    public function test_cannot_verify_another_tenants_order(): void
    {
        $this->startCheckout();
        $intruder = $this->owner(Tenant::factory()->create());
        $this->actingAs($intruder)->post(route('operator.checkout.verify'), ['razorpay_order_id' => 'order_TEST1', 'razorpay_payment_id' => 'pay_X', 'razorpay_signature' => $this->signature('order_TEST1', 'pay_X')])
            ->assertNotFound();
    }

    public function test_disabled_gateway_falls_back_to_billing_request(): void
    {
        PlatformSetting::setValue('razorpay_mode', 'disabled');
        $this->actingAs($this->owner)->get(route('operator.subscription'))->assertOk()->assertDontSee('Secure online payment')->assertSee('raise a billing request');
        Http::fake();
        $this->startCheckout(fake: false)->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_gateway_error_returns_friendly_message(): void
    {
        Http::fake(['api.razorpay.com/*' => Http::response(['error' => ['description' => 'Authentication failed']], 401)]);
        $this->startCheckout(fake: false)->assertStatus(502)->assertJsonPath('message', 'Could not start payment. Please try again or contact support.');
        $this->assertSame(0, PaymentOrder::withoutGlobalScopes()->count());
    }
}
