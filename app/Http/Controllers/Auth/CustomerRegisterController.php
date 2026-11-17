<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CustomerRegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomerRegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register-customer');
    }

    public function store(CustomerRegisterRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only(['name', 'phone', 'email', 'password']));
        $user->type = UserType::Customer;
        $user->status = UserStatus::Active;
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.dashboard')->with('success', __('Welcome to AaoChaleTaxi!'));
    }
}
