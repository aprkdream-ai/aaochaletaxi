<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(TenantContext $context): View
    {
        $tenant = $context->get();

        return view('operator.dashboard', [
            'tenant' => $tenant,
            'subscription' => $tenant->usableSubscription(),
            'staffCount' => $tenant->users()->count(),
        ]);
    }
}
