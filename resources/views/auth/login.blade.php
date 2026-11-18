<x-layouts.auth :title="__('Login')">
    <h1 class="h3 mb-1">{{ __('Welcome back 👋') }}</h1>
    <p class="text-muted-2 mb-3">{{ __('Login with your email or mobile number.') }}</p>
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
        <div class="d-flex justify-content-end mb-n2"><a href="{{ route('password.request') }}" class="small fw-semibold position-relative" style="z-index:1">{{ __('Forgot password?') }}</a></div>
        <x-input name="password" type="password" icon="lock" :label="__('Password')" autocomplete="current-password" required />
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
            <label class="form-check-label small" for="remember">{{ __('Keep me logged in') }}</label>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-100">{{ __('Login') }}<x-icon name="arrow-right" /></button>
    </form>
    </div>
    </div>

    <div class="divider-text my-4">{{ __('New here?') }}</div>
    <div class="row g-2">
        <div class="col-sm-6"><a href="{{ route('register.customer') }}" class="btn btn-light border w-100"><x-icon name="person-plus" />{{ __('Customer sign up') }}</a></div>
        <div class="col-sm-6"><a href="{{ route('register.operator') }}" class="btn btn-light border w-100"><x-icon name="building-add" />{{ __('Register business') }}</a></div>
    </div>
</x-layouts.auth>
