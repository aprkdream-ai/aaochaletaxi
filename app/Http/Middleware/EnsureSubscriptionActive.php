<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Without a usable subscription operators can only reach subscription, profile, KYC and support pages. */
class EnsureSubscriptionActive
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->get();

        if (! $tenant || ! $tenant->usableSubscription()) {
            if ($request->expectsJson()) {
                abort(402, 'Subscription required.');
            }

            return redirect()->route('operator.subscription')
                ->with('warning', __('Your subscription has expired. Renew to continue — your data is safe.'));
        }

        return $next($request);
    }
}
