<x-layouts.auth :title="__('Login')">
    <div class="auth-head">
        <span class="auth-head-ico"><x-icon name="box-arrow-in-right" /></span>
        <h1 class="h3 mb-1">{{ __('Welcome back') }}</h1>
        <p class="text-muted-2 mb-0">{{ __('Login with your email or mobile number.') }}</p>
    </div>
    <div x-data="{ tab: @js(session('otp_sent') || $errors->has('otp') || $errors->has('phone') ? 'otp' : 'password') }">
    <div class="seg mb-4 w-100" role="tablist">
        <button type="button" class="flex-fill" :class="{ active: tab === 'password' }" @click="tab = 'password'" role="tab">{{ __('Password') }}</button>
        <button type="button" class="flex-fill" :class="{ active: tab === 'otp' }" @click="tab = 'otp'" role="tab">{{ __('Mobile OTP') }}</button>
    </div>
    <div x-show="tab === 'otp'" x-cloak>
        @if (session('otp_sent') || $errors->has('otp'))
            <form method="POST" action="{{ route('login.otp.verify') }}" data-loading novalidate>
                @csrf
                <p class="small text-muted-2">{{ __('Enter the 6-digit code sent to :p', ['p' => session('otp_login_phone')]) }}</p>
                <x-input name="otp" icon="shield-lock" :label="__('Verification code')" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required autofocus />
                <button type="submit" class="btn btn-primary btn-lg w-100">{{ __('Verify & login') }}</button>
            </form>
        @else
            <form method="POST" action="{{ route('login.otp.send') }}" data-loading novalidate>
                @csrf
                <x-input name="phone" type="tel" icon="phone" :label="__('Mobile number')" inputmode="numeric" maxlength="14" autocomplete="tel-national" required />
                <button type="submit" class="btn btn-primary btn-lg w-100">{{ __('Send code') }}<x-icon name="arrow-right" /></button>
                <div class="form-text text-center mt-2">{{ __('For customers. New here? Your account is created automatically.') }}</div>
            </form>
        @endif
    </div>
    <div x-show="tab === 'password'">


    <form method="POST" action="{{ route('login.store') }}" data-loading novalidate>
        @csrf
        <x-input name="login" icon="person" :label="__('Email or mobile number')" autocomplete="username" autofocus required />
        <x-input name="password" type="password" icon="lock" :label="__('Password')" autocomplete="current-password" required>
            <x-slot:aside><a href="{{ route('password.request') }}" class="small fw-semibold">{{ __('Forgot password?') }}</a></x-slot:aside>
        </x-input>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
            <label class="form-check-label small" for="remember">{{ __('Keep me logged in') }}</label>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-100">{{ __('Login') }}<x-icon name="arrow-right" /></button>
    </form>
    </div>
    </div>

    <div class="divider-text my-4">{{ __('New here?') }}</div>
    <div class="auth-alt">
        <a href="{{ route('register.customer') }}" class="auth-alt-item">
            <span class="icon-tile tile-sm grad-1"><x-icon name="person-plus" /></span>
            <span class="min-w-0"><b>{{ __('Customer') }}</b><small>{{ __('Book & track rides') }}</small></span>
        </a>
        <a href="{{ route('register.operator') }}" class="auth-alt-item">
            <span class="icon-tile tile-sm grad-0"><x-icon name="building-add" /></span>
            <span class="min-w-0"><b>{{ __('Taxi business') }}</b><small>{{ __('14-day free trial') }}</small></span>
        </a>
    </div>
</x-layouts.auth>
