<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Modules;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** /o/{slug}: public booking page of an active taxi operator with a usable subscription. */
class ResolveMicrositeTenant
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::query()->where('slug', (string) $request->route('slug'))->first();

        abort_unless($tenant && $tenant->isActive() && $tenant->hasModule(Modules::TAXI) && ($tenant->settings['booking_page_enabled'] ?? true) !== false && $tenant->usableSubscription(), 404);

        $this->context->set($tenant);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
