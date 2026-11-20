<x-layouts.auth :title="__('Register your taxi business')" wide
    :aside-title="__('Run your taxi business smarter — free for :days days.', ['days' => 14])"
    :aside-points="[
        ['journal-text', __('Bookings & dispatch'), __('Quotes, driver assignment and OTP trips')],
        ['car-front', __('Fleet & drivers'), __('Documents with expiry reminders, driver app')],
        ['receipt', __('GST invoices & payments'), __('Online advances, invoices and reports')],
        ['credit-card', __('No credit card required'), __('Start free, upgrade when you grow')],
    ]">
    <span class="badge-soft ok mb-3 d-inline-block">{{ __('Free trial · No credit card') }}</span>
    <h1 class="h3 mb-1">{{ __('Register your taxi business') }}</h1>
    <p class="text-muted-2 mb-4">{{ __('Our team verifies every operator before activation — usually within one working day.') }}</p>

    <form method="POST" action="{{ route('register.operator.store') }}" data-loading novalidate>
        @csrf
        <div class="small-caps text-muted-2 mb-3 d-flex align-items-center gap-2"><span class="icon-tile tile-sm" style="width:1.75rem;height:1.75rem;font-size:.85rem">1</span>{{ __('Business details') }}</div>
        <div class="row g-2">
            <div class="col-12"><x-input name="business_name" icon="building" :label="__('Business name')" required maxlength="150" autocomplete="organization" /></div>
            <div class="col-md-6"><x-input name="city" icon="geo-alt" :label="__('City')" required maxlength="100" autocomplete="address-level2" /></div>
            <div class="col-md-6"><x-input name="state" icon="map" :label="__('State')" maxlength="100" autocomplete="address-level1" /></div>
        </div>
        <div class="small-caps text-muted-2 mb-3 mt-2 d-flex align-items-center gap-2"><span class="icon-tile tile-sm" style="width:1.75rem;height:1.75rem;font-size:.85rem">2</span>{{ __('Owner login') }}</div>
        <div class="row g-2">
            <div class="col-12"><x-input name="name" icon="person" :label="__('Your full name')" required autocomplete="name" /></div>
            <div class="col-md-6"><x-input name="email" type="email" icon="envelope" :label="__('Email')" required autocomplete="email" /></div>
            <div class="col-md-6"><x-input name="phone" type="tel" icon="phone" :label="__('Mobile number')" required inputmode="numeric" maxlength="14" placeholder="98XXXXXXXX" autocomplete="tel-national" /></div>
            <div class="col-md-6"><x-input name="password" type="password" icon="lock" :label="__('Password')" required autocomplete="new-password" :help="__('Min 8 characters with letters and numbers.')" /></div>
            <div class="col-md-6"><x-input name="password_confirmation" type="password" icon="lock" :label="__('Confirm password')" required autocomplete="new-password" /></div>
        </div>
        @include('auth.partials.terms')
        <button type="submit" class="btn btn-primary btn-lg w-100">{{ __('Create business account') }}<x-icon name="arrow-right" /></button>
    </form>
    <p class="small text-center mt-4 mb-0 text-muted-2">{{ __('Already registered?') }} <a href="{{ route('login') }}" class="fw-semibold">{{ __('Login') }}</a></p>
</x-layouts.auth>
