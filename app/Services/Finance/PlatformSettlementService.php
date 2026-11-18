<?php

namespace App\Services\Finance;

use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\PlatformSettlement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Online advances are collected on the platform's Razorpay account; this pays them out to operators. */
class PlatformSettlementService
{
    public function __construct(private AuditLogger $audit) {}

    /** Unsettled online collections per operator. @return \Illuminate\Support\Collection<int, object> */
    public function outstanding()
    {
        return DB::table('payments')
            ->join('tenants', 'tenants.id', '=', 'payments.tenant_id')
            ->leftJoin('platform_settlement_items as psi', 'psi.payment_id', '=', 'payments.id')
            ->whereNull('psi.id')->where('payments.method', 'razorpay')->where('payments.amount', '>', 0)
            ->groupBy('payments.tenant_id', 'tenants.name', 'tenants.uuid')
            ->selectRaw('payments.tenant_id, tenants.name, tenants.uuid, count(*) as payments_count, sum(payments.amount) as gross, min(payments.paid_at) as oldest')
            ->orderByDesc('gross')->get();
    }

    public function settle(Tenant $tenant, ?string $fee, string $method, ?string $reference, string $paidOn, User $admin, ?string $notes = null): PlatformSettlement
    {
        return DB::transaction(function () use ($tenant, $fee, $method, $reference, $paidOn, $admin, $notes) {
            $payments = Payment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('method', 'razorpay')->where('amount', '>', 0)
                ->whereNotIn('id', DB::table('platform_settlement_items')->select('payment_id'))->lockForUpdate()->get();
            if ($payments->isEmpty()) {
                throw ValidationException::withMessages(['settlement' => __('Nothing to settle for this operator.')]);
            }
            $gross = Money::add(...$payments->pluck('amount')->map(fn ($a) => (string) $a)->all());
            $fee = $fee !== null && $fee !== '' ? number_format((float) $fee, 2, '.', '') : Money::percent($gross, (string) PlatformSetting::getValue('online_collection_fee_percent', '2'));
            if ((float) $fee > (float) $gross) {
                throw ValidationException::withMessages(['fee' => __('Fee cannot exceed the amount collected.')]);
            }

            $s = new PlatformSettlement(['tenant_id' => $tenant->id, 'gross' => $gross, 'fee' => $fee, 'net' => Money::sub($gross, $fee), 'payments_count' => $payments->count(),
                'method' => $method, 'reference' => $reference, 'paid_on' => $paidOn, 'notes' => $notes]);
            $s->created_by = $admin->id;
            $s->save();
            $s->payments()->attach($payments->pluck('id')->all());
            $this->audit->log('platform.settlement', $s, [], ['tenant' => $tenant->id, 'net' => $s->net], $tenant->id);

            return $s;
        });
    }
}
