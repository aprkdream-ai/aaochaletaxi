<?php

namespace App\Http\Middleware;

use App\Support\WebScheduler;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Terminable: work happens after the response is delivered to the visitor. */
class RunWebScheduler
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('GET') && ! app()->runningUnitTests()) {
            WebScheduler::tick();
        }
    }
}
