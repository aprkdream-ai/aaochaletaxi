<?php

namespace App\Support;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** OTP-verified customer phone kept in the session for 30 minutes (operator booking page + marketplace). */
class VerifiedPhone
{
    public static function get(Request $request): ?string
    {
        $user = $request->user();
        if ($user && $user->type === UserType::Customer && $user->phone) {
            return $user->phone;
        }
        $v = $request->session()->get('verified_phone');

        return ($v && $v['until'] > now()->timestamp) ? $v['phone'] : null;
    }

    /** Remember the verified number; create/log in a customer account for it when appropriate. */
    public static function mark(Request $request, string $phone, ?string $name, ?string $email): void
    {
        $request->session()->put('verified_phone', ['phone' => $phone, 'until' => now()->addMinutes(30)->timestamp]);

        $user = User::query()->where('phone', $phone)->first();
        if (! $user) {
            $user = new User(['name' => $name ?: __('Customer'), 'phone' => $phone, 'email' => $email && ! User::where('email', strtolower($email))->exists() ? strtolower($email) : null, 'password' => Str::random(32)]);
            $user->type = UserType::Customer;
            $user->status = UserStatus::Active;
            $user->phone_verified_at = now();
            $user->save();
        }
        if ($user->type === UserType::Customer && $user->isActive() && ! $request->user()) {
            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('verified_phone', ['phone' => $phone, 'until' => now()->addMinutes(30)->timestamp]);
        }
    }
}
