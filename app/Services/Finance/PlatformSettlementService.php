<?php

namespace App\Services\Finance;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\PlatformSettlement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Online advances are collected on the platform's Razorpay account; this pays them out to operators. */
class PlatformSettlementService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Per operator: unsettled online collections (owed to the operator) and unsettled marketplace commission (owed to the platform).
     *
     * @return Collection<int, object>
     */
    public function outstanding()
    {
        $payments = DB::table('payments')
            ->leftJoin('platform_settlement_items as psi', 'psi.payment_id', '=', 'payments.id')
            ->whereNull('psi.id')->where('payments.method', 'razorpay')->where('payments.amount', '>', 0)
            ->groupBy('payments.tenant_id')
            ->selectRaw('payments.tenant_id, count(*) as payments_count, sum(payments.amount) as gross, min(payments.paid_at) as oldest')
            ->get()->keyBy('tenant_id');
        $commissions = DB::table('bookings')->whereNull('commission_settlement_id')->where('commission_amount', '>', 0)->where('status', 'completed')
            ->groupBy('tenant_id')->selectRaw('tenant_id, count(*) as commissions_count, sum(commission_amount) as commission')->get()->keyBy('tenant_id');

        $ids = $payments->keys()->merge($commissions->keys())->unique();
        $tenants = Tenant::query()->whereIn('id', $ids)->get(['id', 'name', 'uuid'])->keyBy('id');
        $fee = (string) PlatformSetting::getValue('online_collection_fee_percent', '2');

        return $ids->map(function ($id) use ($payments, $commissions, $tenants, $fee) {
            $gross = number_format((float) ($payments[$id]->gross ?? 0), 2, '.', '');
            $commission = number_format((float) ($commissions[$id]->commission ?? 0), 2, '.', '');
            $estFee = Money::percent($gross, $fee);

            return (object) [
                'tenant_id' => $id, 'name' => $tenants[$id]->name ?? '—', 'uuid' => $tenants[$id]->uuid ?? null,
                'payments_count' => (int) ($payments[$id]->payments_count ?? 0), 'gross' => $gross, 'oldest' => $payments[$id]->oldest ?? null,
                'commissions_count' => (int) ($commissions[$id]->commissions_count ?? 0), 'commission' => $commission,
                'net' => Money::sub(Money::sub($gross, $estFee), $commission),
            ];
        })->sortByDesc(fn ($o) => abs((float) $o->net))->values();
    }

    /** Pays out collections minus fee minus commission. A negative net means the operator owes the platform (record their payment). */
    public function settle(Tenant $tenant, ?string $fee, string $method, ?string $reference, string $paidOn, User $admin, ?string $notes = null): PlatformSettlement
    {
        return DB::transaction(function () use ($tenant, $fee, $method, $reference, $paidOn, $admin, $notes) {
            $payments = Payment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('method', 'razorpay')->where('amount', '>', 0)
                ->whereNotIn('id', DB::table('platform_settlement_items')->select('payment_id'))->lockForUpdate()->get();
            $bookings = Booking::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('commission_settlement_id')
                ->where('commission_amount', '>', 0)->where('status', 'completed')->lockForUpdate()->get(['id', 'commission_amount']);
            if ($payments->isEmpty() && $bookings->isEmpty()) {
                throw ValidationException::withMessages(['settlement' => __('Nothing to settle for this operator.')]);
            }
            $gross = Money::add('0', ...$payments->pluck('amount')->map(fn ($a) => (string) $a)->all());
            $commission = Money::add('0', ...$bookings->pluck('commission_amount')->map(fn ($a) => (string) $a)->all());
            $fee = $fee !== null && $fee !== '' ? number_format((float) $fee, 2, '.', '') : Money::percent($gross, (string) PlatformSetting::getValue('online_collection_fee_percent', '2'));
            if ((float) $fee > (float) $gross) {
                throw ValidationException::withMessages(['fee' => __('Fee cannot exceed the amount collected.')]);
            }

            $s = new PlatformSettlement(['tenant_id' => $tenant->id, 'gross' => $gross, 'fee' => $fee, 'commission' => $commission,
                'net' => Money::sub(Money::sub($gross, $fee), $commission), 'payments_count' => $payments->count(), 'commissions_count' => $bookings->count(),
                'method' => $method, 'reference' => $reference, 'paid_on' => $paidOn, 'notes' => $notes]);
            $s->created_by = $admin->id;
            $s->save();
            $s->payments()->attach($payments->pluck('id')->all());
            if ($bookings->isNotEmpty()) {
                Booking::withoutGlobalScopes()->whereIn('id', $bookings->pluck('id'))->update(['commission_settlement_id' => $s->id]);
            }
            $this->audit->log('platform.settlement', $s, [], ['tenant' => $tenant->id, 'net' => $s->net, 'commission' => $commission], $tenant->id);

            return $s;
        });
    }
}
