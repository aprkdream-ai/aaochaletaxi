<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Messaging\OtpService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** Passwordless login for customers with a mobile OTP (creates the account on first login). */
class OtpLoginController extends Controller
{
    public function send(Request $request, OtpService $otp): RedirectResponse
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);
        $data = $request->validate(['phone' => ['required', 'regex:/^[6-9]\d{9}$/']], ['phone.regex' => __('Enter a valid 10-digit Indian mobile number.')]);

        $existing = User::query()->where('phone', $data['phone'])->first();
        if ($existing && $existing->type !== UserType::Customer) {
            return back()->withErrors(['phone' => __('This number belongs to a business or driver account. Please log in with your password.')])->withInput();
        }
        $otp->send($data['phone'], $existing?->email, 'login');
        $request->session()->put('otp_login_phone', $data['phone']);

        return back()->with('otp_sent', true)->withInput();
    }

    public function verify(Request $request, OtpService $otp): RedirectResponse
    {
        $phone = (string) $request->session()->get('otp_login_phone');
        $data = $request->validate(['otp' => ['required', 'digits:6']]);
        abort_unless($phone, 419);
        $otp->verify($phone, $data['otp']);

        $user = User::query()->where('phone', $phone)->first();
        if (! $user) {
            $user = new User(['name' => __('Customer'), 'phone' => $phone, 'password' => Str::random(32)]);
            $user->type = UserType::Customer;
            $user->status = UserStatus::Active;
        }
        if ($user->type !== UserType::Customer || ! ($user->status === null || $user->isActive())) {
            return redirect()->route('login')->withErrors(['phone' => __('Please log in with your password.')]);
        }
        $user->phone_verified_at = now();
        $user->save();

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->forget('otp_login_phone');

        return redirect()->intended(route('account.dashboard'));
    }
}
