<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SubscriptionNotice;
use App\Support\Money;
use App\Support\PlatformSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Subscription lifecycle. History is never rewritten: renewals/upgrades create new rows and
 * mark the previous row as superseded.
 */
class SubscriptionService
{
    public function __construct(
        private TenantContext $context,
        private AuditLogger $audit,
        private SequenceService $sequences,
        private PlanLimitService $limits,
    ) {}

    /**
     * Activate a paid plan.
     *
     * @param  'now'|'after_current'  $start
     * @param  array{amount?:string|float|null, method:string, reference?:?string, paid_at?:mixed, notes?:?string}|null  $payment
     */
    public function activate(Tenant $tenant, SubscriptionPlan $plan, string $cycle, string $start, ?User $admin, ?array $payment = null, ?string $notes = null): Subscription
    {
        if (! in_array($cycle, ['monthly', 'yearly'], true)) {
            throw ValidationException::withMessages(['billing_cycle' => __('Invalid billing cycle.')]);
        }

        $this->assertFits($tenant, $plan);

        $subscription = DB::transaction(function () use ($tenant, $plan, $cycle, $start, $admin, $payment, $notes) {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            $current = $this->currentFor($tenant, lock: true);

            $startsAt = ($start === 'after_current' && $current && $current->ends_at->isFuture() && $current->billing_cycle !== 'trial')
                ? $current->ends_at->copy()
                : now();
            $endsAt = $cycle === 'yearly' ? $startsAt->copy()->addYear() : $startsAt->copy()->addMonth();
            $type = $locked->businessType();
            $price = $plan->priceFor($cycle, $type);
            $addon = $plan->addonPrice($cycle, $type);

            $new = $this->context->runAs($tenant, function () use ($plan, $cycle, $startsAt, $endsAt, $price, $addon, $type, $notes, $admin) {
                $s = new Subscription([
                    'plan_id' => $plan->id, 'billing_cycle' => $cycle, 'status' => SubscriptionStatus::Active,
                    'starts_at' => $startsAt, 'ends_at' => $endsAt, 'price' => $price, 'notes' => $notes,
                    'business_type' => $type, 'addon_price' => $addon,
                ]);
                $s->activated_by = $admin?->id;
                $s->save();

                return $s;
            });

            if ($current) {
                $this->supersede($current, $new, cancelNow: $startsAt->lessThanOrEqualTo(now()));
            }

            if ($payment) {
                $this->recordPayment($new, $payment['amount'] ?? $price, PaymentMethod::from($payment['method']), $payment['reference'] ?? null, $payment['paid_at'] ?? null, $admin, $payment['notes'] ?? null);
            }

            $this->audit->log('subscription.activated', $new, [], [
                'plan' => $plan->code, 'cycle' => $cycle, 'starts_at' => $startsAt->toDateTimeString(), 'ends_at' => $endsAt->toDateTimeString(), 'business_type' => $type->value, 'price' => $price,
            ], $tenant->id);

            return $new;
        });

        $this->notifyOwner($tenant, 'activated', $subscription);

        return $subscription;
    }

    /** Switch plan immediately for the remainder of the current period (no proration). */
    public function changePlan(Subscription $current, SubscriptionPlan $plan, User $admin): Subscription
    {
        $tenant = Tenant::findOrFail($current->tenant_id);

        if ($current->plan_id === $plan->id) {
            throw ValidationException::withMessages(['plan_id' => __('The operator is already on this plan.')]);
        }
        if (! $current->isUsable()) {
            throw ValidationException::withMessages(['plan_id' => __('Only an active subscription can change plan.')]);
        }
        $this->assertFits($tenant, $plan);

        return DB::transaction(function () use ($current, $plan, $admin, $tenant) {
            $locked = Subscription::withoutGlobalScopes()->lockForUpdate()->findOrFail($current->id);

            $new = $this->context->runAs($tenant, function () use ($locked, $plan, $admin) {
                $s = new Subscription([
                    'plan_id' => $plan->id, 'billing_cycle' => $locked->billing_cycle, 'status' => $locked->status,
                    'starts_at' => now(), 'ends_at' => $locked->ends_at, 'grace_ends_at' => $locked->grace_ends_at,
                    'price' => 0, 'business_type' => $locked->business_type, 'notes' => __('Plan change from :from', ['from' => $locked->plan->name]),
                ]);
                $s->activated_by = $admin->id;
                $s->save();

                return $s;
            });

            $this->supersede($locked, $new, cancelNow: true);
            $this->audit->log('subscription.plan_changed', $new, ['plan' => $locked->plan->code], ['plan' => $plan->code], $tenant->id);

            return $new;
        });
    }

    public function extend(Subscription $subscription, int $days, User $admin, ?string $reason = null): Subscription
    {
        if ($days < 1 || $days > 366) {
            throw ValidationException::withMessages(['days' => __('Extension must be between 1 and 366 days.')]);
        }

        return DB::transaction(function () use ($subscription, $days, $reason) {
            $s = Subscription::withoutGlobalScopes()->lockForUpdate()->findOrFail($subscription->id);

            if ($s->superseded_by || in_array($s->status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Suspended], true)) {
                throw ValidationException::withMessages(['days' => __('This subscription cannot be extended.')]);
            }

            $old = ['ends_at' => $s->ends_at->toDateTimeString(), 'status' => $s->status->value];
            $base = $s->ends_at->isPast() ? now() : $s->ends_at;
            $s->ends_at = $base->copy()->addDays($days);
            if (in_array($s->status, [SubscriptionStatus::Grace, SubscriptionStatus::Expired, SubscriptionStatus::PastDue], true)) {
                $s->status = $s->billing_cycle === 'trial' ? SubscriptionStatus::Trialing : SubscriptionStatus::Active;
            }
            $s->grace_ends_at = null;
            $s->reminder_stage = null;
            $s->save();

            $this->audit->log('subscription.extended', $s, $old, ['ends_at' => $s->ends_at->toDateTimeString(), 'days' => $days, 'reason' => $reason], $s->tenant_id);

            return $s;
        });
    }

    public function cancel(Subscription $subscription, User $admin, string $reason): void
    {
        DB::transaction(function () use ($subscription, $reason) {
            $s = Subscription::withoutGlobalScopes()->lockForUpdate()->findOrFail($subscription->id);
            if (! $s->isUsable()) {
                throw ValidationException::withMessages(['reason' => __('Subscription is not active.')]);
            }
            $old = ['status' => $s->status->value];
            $s->status = SubscriptionStatus::Cancelled;
            $s->cancelled_at = now();
            $s->notes = trim(($s->notes ? $s->notes.' | ' : '').__('Cancelled: :r', ['r' => $reason]));
            $s->save();
            $this->audit->log('subscription.cancelled', $s, $old, ['status' => 'cancelled', 'reason' => $reason], $s->tenant_id);
        });
    }

    /** Record a payment (amount is exclusive of GST) and issue a numbered tax invoice. */
    public function recordPayment(Subscription $subscription, string|float|int $amount, PaymentMethod $method, ?string $reference, mixed $paidAt, ?User $recorder, ?string $notes = null): SubscriptionPayment
    {
        $amount = number_format((float) $amount, 2, '.', '');
        if ((float) $amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('Amount must be greater than zero.')]);
        }

        $tenant = Tenant::findOrFail($subscription->tenant_id);
        $taxPercent = PlatformSettings::decimal('subscription_gst_percent', '18');
        $tax = Money::percent($amount, $taxPercent);
        $paidAt = $paidAt ? Carbon::parse($paidAt) : now();
        $fy = SequenceService::financialYear($paidAt);

        return DB::transaction(function () use ($subscription, $tenant, $amount, $taxPercent, $tax, $method, $reference, $paidAt, $recorder, $notes, $fy) {
            $number = sprintf('ACT/%s/%05d', $fy, $this->sequences->next('subscription_invoice:'.$fy));

            $payment = $this->context->runAs($tenant, function () use ($subscription, $tenant, $amount, $taxPercent, $tax, $method, $reference, $paidAt, $recorder, $notes, $number) {
                $p = new SubscriptionPayment([
                    'subscription_id' => $subscription->id, 'invoice_number' => $number, 'amount' => $amount,
                    'tax_percent' => $taxPercent, 'tax_amount' => $tax, 'total' => Money::add($amount, $tax),
                    'method' => $method, 'reference' => $reference, 'status' => 'paid', 'paid_at' => $paidAt, 'notes' => $notes,
                    'billing_snapshot' => $this->billingSnapshot($tenant, $subscription),
                ]);
                $p->recorded_by = $recorder?->id;
                $p->save();

                return $p;
            });

            $this->audit->log('subscription.payment_recorded', $payment, [], ['invoice' => $number, 'total' => $payment->total, 'method' => $method->value], $tenant->id);

            return $payment;
        });
    }

    /** Latest usable, non-superseded subscription for a tenant. */
    public function currentFor(Tenant $tenant, bool $lock = false): ?Subscription
    {
        $usable = array_map(fn ($s) => $s->value, array_filter(SubscriptionStatus::cases(), fn ($s) => $s->isUsable()));

        return Subscription::withoutGlobalScopes()->with('plan')
            ->where('tenant_id', $tenant->id)->whereIn('status', $usable)->whereNull('superseded_by')
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->latest('id')->first();
    }

    private function supersede(Subscription $old, Subscription $new, bool $cancelNow): void
    {
        $old->superseded_by = $new->id;
        if ($cancelNow) {
            $old->status = SubscriptionStatus::Cancelled;
            $old->cancelled_at = now();
        }
        $old->save();
    }

    private function assertFits(Tenant $tenant, SubscriptionPlan $plan): void
    {
        if (! $plan->is_active) {
            throw ValidationException::withMessages(['plan_id' => __('This plan is not active.')]);
        }
        if ($errors = $this->limits->violations($tenant, $plan)) {
            throw ValidationException::withMessages(['plan_id' => $errors]);
        }
    }

    private function billingSnapshot(Tenant $tenant, Subscription $subscription): array
    {
        $s = fn (string $k, ?string $d = null) => PlatformSetting::getValue($k, $d);

        return [
            'seller' => [
                'name' => $s('platform_legal_name', config('aaochale.legal_name')),
                'gstin' => $s('platform_gstin'), 'pan' => $s('platform_pan'),
                'address' => $s('platform_address'), 'state' => $s('platform_state'),
                'email' => config('aaochale.support_email'), 'bank' => $s('platform_bank_details'),
            ],
            'buyer' => [
                'name' => $tenant->legal_name ?: $tenant->name, 'gstin' => $tenant->gstin, 'email' => $tenant->email, 'phone' => $tenant->phone,
                'address' => collect([$tenant->address_line, $tenant->city, $tenant->state, $tenant->pincode])->filter()->join(', '),
                'state' => $tenant->state,
            ],
            'item' => [
                'plan' => $subscription->plan->name.($subscription->business_type?->hasAddon() ? ' + '.__('Fleet add-on') : ''),
                'cycle' => $subscription->billing_cycle,
                'period' => $subscription->starts_at->format('d M Y').' – '.$subscription->ends_at->format('d M Y'),
                'sac' => '998314',
            ],
        ];
    }

    private function notifyOwner(Tenant $tenant, string $kind, Subscription $subscription): void
    {
        $tenant->ownerUser()?->notify(new SubscriptionNotice($kind, $subscription->load('plan')));
    }
}
