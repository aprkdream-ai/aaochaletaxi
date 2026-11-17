<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request, TenantContext $context): View
    {
        return view('driver.home', ['user' => $request->user(), 'tenant' => $context->get()]);
    }
}
