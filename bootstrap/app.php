<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureTenantActive;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserType;
use App\Http\Middleware\PlatformScope;
use App\Http\Middleware\ResetTenantContext;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ResetTenantContext::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [SetLocale::class, EnsureUserIsActive::class]);
        $middleware->validateCsrfTokens(except: ['webhooks/*']); // signature-verified instead

        $middleware->alias([
            'type' => EnsureUserType::class,
            'permission' => EnsurePermission::class,
            'tenant' => ResolveTenant::class,
            'tenant.active' => EnsureTenantActive::class,
            'platform' => PlatformScope::class,
            'subscription' => EnsureSubscriptionActive::class,
        ]);

        // Tenant context must exist before route-model binding so scoped models resolve correctly.
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenant::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, PlatformScope::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn ($request) => route($request->user()->homeRoute()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportDuplicates();
    })->create();
