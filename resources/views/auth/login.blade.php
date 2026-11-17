<x-layouts.auth :title="__('Login')">
    <h1 class="h4 fw-800 mb-1">{{ __('Welcome back') }}</h1>
    <p class="text-body-secondary mb-4">{{ __('Login with your email or mobile number.') }}</p>

    <form method="POST" action="{{ route('login.store') }}" data-loading novalidate>
        @csrf
        <x-input name="login" :label="__('Email or mobile number')" autocomplete="username" autofocus required />
        <div class="mb-3">
            <div class="d-flex justify-content-between">
                <label for="f_password" class="form-label">{{ __('Password') }} <span class="text-danger">*</span></label>
                <a href="{{ route('password.request') }}" class="small">{{ __('Forgot password?') }}</a>
            </div>
            <input type="password" name="password" id="f_password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
            <label class="form-check-label" for="remember">{{ __('Keep me logged in') }}</label>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2">{{ __('Login') }}</button>
    </form>

    <hr class="my-4">
    <div class="small text-center d-grid gap-1">
        <span>{{ __('New customer?') }} <a href="{{ route('register.customer') }}">{{ __('Create an account') }}</a></span>
        <span>{{ __('Taxi operator?') }} <a href="{{ route('register.operator') }}">{{ __('Register your business') }}</a></span>
    </div>
</x-layouts.auth>
