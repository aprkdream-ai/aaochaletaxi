<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Closure;

/**
 * Holds the tenant for the current request / job.
 * Tenant-scoped queries fail closed when no tenant is set and bypass is off.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    private bool $bypass = false;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function isBypassed(): bool
    {
        return $this->bypass;
    }

    /** Explicit platform-level access (super admin panel, system jobs). */
    public function enableBypass(): void
    {
        $this->bypass = true;
    }

    public function reset(): void
    {
        $this->tenant = null;
        $this->bypass = false;
    }

    /** Run a callback as the given tenant, restoring previous state afterwards. */
    public function runAs(Tenant $tenant, Closure $callback): mixed
    {
        [$prevTenant, $prevBypass] = [$this->tenant, $this->bypass];
        $this->tenant = $tenant;
        $this->bypass = false;

        try {
            return $callback($tenant);
        } finally {
            $this->tenant = $prevTenant;
            $this->bypass = $prevBypass;
        }
    }

    /** Run a callback with platform-wide access, restoring previous state afterwards. */
    public function runWithoutScope(Closure $callback): mixed
    {
        $prevBypass = $this->bypass;
        $this->bypass = true;

        try {
            return $callback();
        } finally {
            $this->bypass = $prevBypass;
        }
    }
}
