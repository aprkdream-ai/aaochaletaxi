<x-layouts.auth :title="__('Create account')" wide
    :aside-title="__('Your trips, all in one place.')"
    :aside-points="[
        ['lightning-charge', __('Faster bookings'), __('Your details saved for next time')],
        ['geo-alt', __('Track every trip'), __('Driver, vehicle, OTP and invoices in one place')],
        ['shield-check', __('Verified operators'), __('KYC-checked taxi businesses only')],
    ]">
    <div class="auth-head">
        <span class="auth-head-ico"><x-icon name="person-plus" /></span>
        <h1 class="h3 mb-1">{{ __('Create your account') }}</h1>
        <p class="text-muted-2 mb-0">{{ __('It takes less than a minute.') }}</p>
    </div>
    <form method="POST" action="{{ route('register.customer.store') }}" data-loading novalidate>
        @csrf
        <x-input name="name" icon="person" :label="__('Full name')" required autocomplete="name" />
        <div class="row g-2">
            <div class="col-sm-6"><x-input name="phone" type="tel" icon="phone" :label="__('Mobile number')" required inputmode="numeric" maxlength="14" placeholder="98XXXXXXXX" autocomplete="tel-national" /></div>
            <div class="col-sm-6"><x-input name="email" type="email" icon="envelope" :label="__('Email (optional)')" autocomplete="email" /></div>
            <div class="col-sm-6"><x-input name="password" type="password" icon="lock" :label="__('Password')" required autocomplete="new-password" /></div>
            <div class="col-sm-6"><x-input name="password_confirmation" type="password" icon="lock" :label="__('Confirm password')" required autocomplete="new-password" /></div>
        </div>
        <div class="form-text mt-n2 mb-2">{{ __('Password: min 8 characters with letters and numbers.') }}</div>
        @include('auth.partials.terms')
        <button type="submit" class="btn btn-primary btn-lg w-100">{{ __('Create account') }}<x-icon name="arrow-right" /></button>
    </form>
    <p class="small text-center mt-3 mb-0 text-muted-2">{{ __('Already have an account?') }} <a href="{{ route('login') }}" class="fw-semibold">{{ __('Login') }}</a></p>
</x-layouts.auth>
