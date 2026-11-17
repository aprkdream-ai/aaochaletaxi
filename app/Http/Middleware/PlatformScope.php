<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Grants explicit cross-tenant access for platform (super admin / staff) routes only. */
class PlatformScope
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->type->isPlatform()) {
            abort(403);
        }

        $this->context->enableBypass();

        return $next($request);
    }
}
