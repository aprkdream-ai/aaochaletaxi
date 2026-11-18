<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SubscriptionNotice;
use App\Services\SubscriptionLifecycleService;
use App\Services\TenantOnboardingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubscriptionManagementTest extends TestCase
{
    private User $admin;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->superAdmin()->create();
        $this->owner = app(TenantOnboardingService::class)->register([
            'business_name' => 'Kaveri Cabs', 'city' => 'Mysuru', 'state' => 'Karnataka', 'name' => 'K Owner', 'email' => 'k@example.com', 'phone' => '9876501234', 'password' => 'secret123',
        ]);
        $this->tenant = $this->owner->tenant;
        $this->tenant->forceFill(['status' => 'active'])->save();
    }

    private function subs()
    {
        return Subscription::withoutGlobalScopes()->with('plan')->where('tenant_id', $this->tenant->id)->orderBy('id')->get();
    }

    private function plan(string $code): SubscriptionPlan
    {
        return SubscriptionPlan::where('code', $code)->firstOrFail();
    }

    public function test_activate_now_replaces_trial_and_issues_gst_invoice(): void
    {
        $this->actingAs($this->admin)->post(route('admin.subscriptions.activate', $this->tenant), [
            'plan_id' => $this->plan('growth')->id, 'billing_cycle' => 'monthly', 'start' => 'now',
            'record_payment' => 1, 'amount' => 1499, 'method' => 'upi', 'reference' => 'UTR123',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        [$trial, $paid] = $this->subs()->all();
        $this->assertSame('cancelled', $trial->status->value);
        $this->assertSame($paid->id, $trial->superseded_by);
        $this->assertSame('active', $paid->status->value);
        $this->assertSame('growth', $paid->plan->code);
        $this->assertEqualsWithDelta(now()->addMonth()->timestamp, $paid->ends_at->timestamp, 5);

        $payment = SubscriptionPayment::withoutGlobalScopes()->sole();
        $this->assertMatchesRegularExpression('#^ACT/\d{4}-\d{2}/00001$#', $payment->invoice_number);
        $this->assertSame('1499.00', $payment->amount);
        $this->assertSame('269.82', $payment->tax_amount);   // 18%
        $this->assertSame('1768.82', $payment->total);
        $this->assertSame('Kaveri Cabs', $payment->billing_snapshot['buyer']['name']);

        $this->assertSame($paid->id, $this->tenant->usableSubscription()->id);
        Notification::assertSentTo($this->owner, SubscriptionNotice::class, fn ($n) => $n->kind === 'activated');
    }

    public function test_invoice_numbers_are_sequential(): void
    {
        $sub = $this->tenant->usableSubscription();
        $this->actingAs($this->admin)->post(route('admin.subscriptions.payments.store', $sub), ['amount' => 799, 'method' => 'cash']);
        $this->actingAs($this->admin)->post(route('admin.subscriptions.payments.store', $sub), ['amount' => 799, 'method' => 'cash']);

        $numbers = SubscriptionPayment::withoutGlobalScopes()->orderBy('id')->pluck('invoice_number')->map(fn ($n) => substr($n, -5))->all();
        $this->assertSame(['00001', '00002'], $numbers);
    }

    public function test_renewal_after_current_keeps_continuous_coverage(): void
    {
        $this->actingAs($this->admin)->post(route('admin.subscriptions.activate', $this->tenant), ['plan_id' => $this->plan('starter')->id, 'billing_cycle' => 'monthly', 'start' => 'now', 'record_payment' => 0]);
        $first = $this->subs()->last();

        $this->actingAs($this->admin)->post(route('admin.subscriptions.activate', $this->tenant), ['plan_id' => $this->plan('starter')->id, 'billing_cycle' => 'yearly', 'start' => 'after_current', 'record_payment' => 0])->assertSessionHasNoErrors();
        $renewal = $this->subs()->last();

        $first->refresh();
        $this->assertSame('active', $first->status->value, 'current period continues');
        $this->assertSame($renewal->id, $first->superseded_by);
        $this->assertSame($first->ends_at->timestamp, $renewal->starts_at->timestamp);
        $this->assertSame($renewal->id, $this->tenant->usableSubscription()->id);

        // When the old period ends it expires quietly — no grace, no "expired" email.
        Notification::fake();
        $this->travelTo($first->ends_at->copy()->addHour());
        app(TenantContext::class)->runWithoutScope(fn () => app(SubscriptionLifecycleService::class)->process());
        $this->assertSame('expired', $first->fresh()->status->value);
        $this->assertSame('active', $renewal->fresh()->status->value);
        Notification::assertNothingSent();
    }

    public function test_extend_and_cancel(): void
    {
        $sub = $this->tenant->usableSubscription();
        $end = $sub->ends_at->copy();

        $this->actingAs($this->admin)->post(route('admin.subscriptions.extend', $sub), ['days' => 10, 'reason' => 'goodwill'])->assertSessionHas('success');
        $this->assertSame($end->addDays(10)->timestamp, $sub->fresh()->ends_at->timestamp);

        $this->actingAs($this->admin)->post(route('admin.subscriptions.cancel', $sub), ['reason' => 'Requested'])->assertSessionHas('success');
        $this->assertSame('cancelled', $sub->fresh()->status->value);

        $this->actingAs($this->owner)->get(route('operator.dashboard'))->assertRedirect(route('operator.subscription'));
        $this->actingAs($this->owner)->get(route('operator.subscription'))->assertOk()->assertSee('No active subscription');
    }

    public function test_change_plan_and_downgrade_limit(): void
    {
        $sub = $this->tenant->usableSubscription();
        $this->actingAs($this->admin)->post(route('admin.subscriptions.change-plan', $sub), ['plan_id' => $this->plan('business')->id])->assertSessionHas('success');
        $current = $this->tenant->usableSubscription();
        $this->assertSame('business', $current->plan->code);
        $this->assertSame($sub->ends_at->timestamp, $current->ends_at->timestamp);

        // Starter allows 3 users; create 3 more staff → downgrade blocked.
        User::factory()->count(3)->operatorOf($this->tenant)->create();
        $this->actingAs($this->admin)->post(route('admin.subscriptions.change-plan', $current), ['plan_id' => $this->plan('starter')->id])->assertSessionHasErrors('plan_id');
        $this->assertSame('business', $this->tenant->usableSubscription()->plan->code);
    }

    public function test_operator_invoice_access_is_tenant_scoped(): void
    {
        PlatformSetting::setValue('platform_state', 'Karnataka');
        $sub = $this->tenant->usableSubscription();
        $this->actingAs($this->admin)->post(route('admin.subscriptions.payments.store', $sub), ['amount' => 799, 'method' => 'upi']);
        $payment = SubscriptionPayment::withoutGlobalScopes()->sole();

        $this->actingAs($this->owner)->get(route('operator.subscription.invoices.show', $payment))->assertOk()->assertSee($payment->invoice_number)->assertSee('CGST')->assertSee('₹942.82')->assertSee('SGST');
        $this->actingAs($this->admin)->get(route('admin.invoices.show', $payment))->assertOk();

        $other = $this->owner(Tenant::factory()->create());
        $this->actingAs($other)->get(route('operator.subscription.invoices.show', $payment))->assertNotFound();
    }

    public function test_validation_and_permissions(): void
    {
        $this->actingAs($this->admin)->post(route('admin.subscriptions.activate', $this->tenant), ['plan_id' => 999, 'billing_cycle' => 'weekly', 'start' => 'now', 'record_payment' => 1])
            ->assertSessionHasErrors(['plan_id', 'billing_cycle', 'amount', 'method']);

        $this->actingAs($this->owner)->post(route('admin.subscriptions.activate', $this->tenant), [])->assertForbidden();
    }
}
