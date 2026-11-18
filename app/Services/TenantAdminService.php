<?php

namespace App\Services;

use App\Enums\KycStatus;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantApproved;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Super-admin lifecycle actions on tenants. */
class TenantAdminService
{
    public function __construct(private AuditLogger $audit) {}

    public function approve(Tenant $tenant, User $admin): void
    {
        $this->transition($tenant, [TenantStatus::Pending, TenantStatus::Rejected], TenantStatus::Active, function (Tenant $t) use ($admin) {
            $t->approved_at = now();
            $t->approved_by = $admin->id;
            $t->suspended_at = null;
            $t->suspension_reason = null;
        }, 'tenant.approved');

        $tenant->ownerUser()?->notify(new TenantApproved($tenant));
    }

    public function reject(Tenant $tenant, string $reason): void
    {
        $this->transition($tenant, [TenantStatus::Pending], TenantStatus::Rejected, function (Tenant $t) use ($reason) {
            $t->suspension_reason = $reason;
        }, 'tenant.rejected');
    }

    public function suspend(Tenant $tenant, string $reason): void
    {
        $this->transition($tenant, [TenantStatus::Active], TenantStatus::Suspended, function (Tenant $t) use ($reason) {
            $t->suspended_at = now();
            $t->suspension_reason = $reason;
        }, 'tenant.suspended');
    }

    public function reactivate(Tenant $tenant): void
    {
        $this->transition($tenant, [TenantStatus::Suspended], TenantStatus::Active, function (Tenant $t) {
            $t->suspended_at = null;
            $t->suspension_reason = null;
        }, 'tenant.reactivated');
    }

    public function setKycStatus(Tenant $tenant, KycStatus $status, ?string $notes): void
    {
        DB::transaction(function () use ($tenant, $status, $notes) {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            $old = ['kyc_status' => $locked->kyc_status->value];
            $locked->kyc_status = $status;
            $locked->kyc_notes = $notes;
            $locked->save();
            $this->audit->log('tenant.kyc_updated', $locked, $old, ['kyc_status' => $status->value, 'notes' => $notes]);
            $tenant->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /** @param list<TenantStatus> $from */
    private function transition(Tenant $tenant, array $from, TenantStatus $to, callable $mutate, string $event): void
    {
        DB::transaction(function () use ($tenant, $from, $to, $mutate, $event) {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);

            if (! in_array($locked->status, $from, true)) {
                throw ValidationException::withMessages([
                    'status' => "Operator is {$locked->status->value}; cannot change to {$to->value}.",
                ]);
            }

            $old = ['status' => $locked->status->value];
            $locked->status = $to;
            $mutate($locked);
            $locked->save();

            $this->audit->log($event, $locked, $old, ['status' => $to->value, 'reason' => $locked->suspension_reason]);
            $tenant->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
