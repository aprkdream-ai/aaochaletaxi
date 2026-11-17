<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sets the tenant context from the authenticated tenant user. */
class ResolveTenant
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->type->belongsToTenant() || ! $user->tenant_id) {
            abort(403);
        }

        // Always load fresh: status may change mid-session (suspension must apply immediately).
        $tenant = Tenant::query()->find($user->tenant_id);
        $user->setRelation('tenant', $tenant);

        if (! $tenant) {
            abort(403, 'Business account not found.');
        }

        $this->context->set($tenant);

        return $next($request);
    }
}
