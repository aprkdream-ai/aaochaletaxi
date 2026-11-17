<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Non-active tenants (pending / suspended / rejected) can only see the account status page. */
class EnsureTenantActive
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->get();

        if (! $tenant || ! $tenant->isActive()) {
            if ($request->expectsJson()) {
                abort(403, 'Business account is not active.');
            }

            return redirect()->route('operator.status');
        }

        return $next($request);
    }
}
