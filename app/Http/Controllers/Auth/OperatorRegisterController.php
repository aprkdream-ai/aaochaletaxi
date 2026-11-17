<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OperatorRegisterRequest;
use App\Services\TenantOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OperatorRegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register-operator');
    }

    public function store(OperatorRegisterRequest $request, TenantOnboardingService $onboarding): RedirectResponse
    {
        $owner = $onboarding->register($request->safe()->only([
            'business_name', 'city', 'state', 'name', 'email', 'phone', 'password',
        ]));

        Auth::login($owner);
        $request->session()->regenerate();

        return redirect()->route('operator.status')
            ->with('success', __('Registration received! Our team will verify your business shortly.'));
    }
}
