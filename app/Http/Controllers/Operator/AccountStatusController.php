<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountStatusController extends Controller
{
    public function __invoke(TenantContext $context): View|RedirectResponse
    {
        $tenant = $context->get();

        if ($tenant->isActive()) {
            return redirect()->route('operator.dashboard');
        }

        return view('operator.status', ['tenant' => $tenant]);
    }
}
