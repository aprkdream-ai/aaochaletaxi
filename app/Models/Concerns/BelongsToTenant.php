<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $context = app(TenantContext::class);

            if ($context->has()) {
                // Always force the context tenant; never trust a supplied tenant_id.
                $model->tenant_id = $context->id();

                return;
            }

            if (! $context->isBypassed() || empty($model->tenant_id)) {
                throw new LogicException('Cannot create '.static::class.' without a tenant context.');
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('tenant_id') && ! app(TenantContext::class)->isBypassed()) {
                throw new LogicException('tenant_id cannot be changed.');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
