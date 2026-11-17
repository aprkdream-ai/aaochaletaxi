<x-layouts.auth :title="__('Login')">
    <h1 class="h3 mb-1">{{ __('Welcome back 👋') }}</h1>
    <p class="text-muted-2 mb-4">{{ __('Login with your email or mobile number.') }}</p>


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

    <div class="divider-text my-4">{{ __('New here?') }}</div>
    <div class="row g-2">
        <div class="col-sm-6"><a href="{{ route('register.customer') }}" class="btn btn-light border w-100"><x-icon name="person-plus" />{{ __('Customer sign up') }}</a></div>
        <div class="col-sm-6"><a href="{{ route('register.operator') }}" class="btn btn-light border w-100"><x-icon name="building-add" />{{ __('Register business') }}</a></div>
    </div>
</x-layouts.auth>
