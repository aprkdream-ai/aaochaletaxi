<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\SubscriptionNotice;
use App\Support\PlatformSettings;
use Carbon\CarbonInterface;

/**
 * Idempotent: safe to run every hour from the scheduler.
 * trialing/active --(ends_at passed)--> grace --(grace_ends_at passed)--> expired.
 * Superseded rows (renewed/upgraded) expire quietly without grace or notices.
 */
class SubscriptionLifecycleService
{
    public const REMINDER_STAGES = [7, 3, 1];

    public function __construct(private AuditLogger $audit) {}

    /** @return array{grace:int, expired:int, reminders:int} */
    public function process(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $stats = ['grace' => 0, 'expired' => 0, 'reminders' => 0];
        $graceDays = PlatformSettings::int('grace_days', 7);

        // 1) Period ended → grace (or expired when superseded / no grace configured)
        Subscription::withoutGlobalScopes()->with('plan')
            ->whereIn('status', [SubscriptionStatus::Trialing->value, SubscriptionStatus::Active->value])
            ->where('ends_at', '<=', $now)
            ->chunkById(200, function ($subs) use ($now, $graceDays, &$stats) {
                foreach ($subs as $sub) {
                    if ($sub->superseded_by || $graceDays === 0) {
                        $this->transition($sub, SubscriptionStatus::Expired, notify: ! $sub->superseded_by);
                        $stats['expired']++;
                    } else {
                        $sub->grace_ends_at = $sub->ends_at->copy()->addDays($graceDays);
                        if ($sub->grace_ends_at->lessThanOrEqualTo($now)) {
                            $this->transition($sub, SubscriptionStatus::Expired, notify: true);
                            $stats['expired']++;
                        } else {
                            $this->transition($sub, SubscriptionStatus::Grace, notify: true);
                            $stats['grace']++;
                        }
                    }
                }
            });

        // 2) Grace over → expired
        Subscription::withoutGlobalScopes()->with('plan')
            ->where('status', SubscriptionStatus::Grace->value)
            ->where('grace_ends_at', '<=', $now)
            ->chunkById(200, function ($subs) use (&$stats) {
                foreach ($subs as $sub) {
                    $this->transition($sub, SubscriptionStatus::Expired, notify: ! $sub->superseded_by);
                    $stats['expired']++;
                }
            });

        // 3) Renewal reminders (7 / 3 / 1 days before end), once per stage
        Subscription::withoutGlobalScopes()->with('plan')
            ->whereIn('status', [SubscriptionStatus::Trialing->value, SubscriptionStatus::Active->value])
            ->whereNull('superseded_by')
            ->whereBetween('ends_at', [$now, $now->copy()->addDays(max(self::REMINDER_STAGES))->endOfDay()])
            ->chunkById(200, function ($subs) use ($now, &$stats) {
                foreach ($subs as $sub) {
                    $days = (int) $now->copy()->startOfDay()->diffInDays($sub->ends_at->copy()->startOfDay(), false);
                    $stage = collect(self::REMINDER_STAGES)->filter(fn ($s) => $days <= $s)->min();
                    if ($stage === null || ($sub->reminder_stage !== null && $sub->reminder_stage <= $stage)) {
                        continue;
                    }
                    $sub->reminder_stage = $stage;
                    $sub->save();
                    $this->notify($sub, 'expiring');
                    $stats['reminders']++;
                }
            });

        return $stats;
    }

    private function transition(Subscription $sub, SubscriptionStatus $to, bool $notify): void
    {
        $from = $sub->status->value;
        $sub->status = $to;
        $sub->save();
        $this->audit->log('subscription.'.$to->value, $sub, ['status' => $from], ['status' => $to->value], $sub->tenant_id);

        if ($notify) {
            $this->notify($sub, $to === SubscriptionStatus::Grace ? 'grace' : 'expired');
        }
    }

    private function notify(Subscription $sub, string $kind): void
    {
        Tenant::find($sub->tenant_id)?->ownerUser()?->notify(new SubscriptionNotice($kind, $sub));
    }
}
