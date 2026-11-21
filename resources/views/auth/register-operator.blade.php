<x-layouts.auth :title="__('Register your taxi business')" wide
    :aside-title="__('Run your taxi business smarter — free for :days days.', ['days' => 14])"
    :aside-points="[
        ['journal-text', __('Bookings & dispatch'), __('Quotes, driver assignment and OTP trips')],
        ['car-front', __('Fleet & drivers'), __('Documents with expiry reminders, driver app')],
        ['receipt', __('GST invoices & payments'), __('Online advances, invoices and reports')],
        ['credit-card', __('No credit card required'), __('Start free, upgrade when you grow')],
    ]">
    @php
        $step1Fields = ['business_name', 'city', 'state'];
        $startStep = $errors->any() && ! $errors->hasAny($step1Fields) ? 2 : 1;
    @endphp
    <div class="auth-head">
        <span class="badge-soft ok mb-2 d-inline-block">{{ __('Free trial · No credit card') }}</span>
        <h1 class="h3 mb-1">{{ __('Register your taxi business') }}</h1>
        <p class="text-muted-2 mb-0">{{ __('Our team verifies every operator before activation — usually within one working day.') }}</p>
    </div>

    <form method="POST" action="{{ route('register.operator.store') }}" data-loading novalidate
          x-data="{ step: {{ $startStep }}, next() { const bad = [...this.$refs.s1.querySelectorAll('input[required]')].find((i) => ! i.value.trim()); if (bad) { bad.classList.add('is-invalid'); bad.focus(); return; } this.step = 2; this.$nextTick(() => this.$refs.s2.querySelector('input')?.focus()); } }">
        @csrf
        <ol class="wizard-steps" aria-label="{{ __('Steps') }}">
            <li :class="{ active: step === 1, done: step > 1 }"><button type="button" @click="step = 1"><span>1</span>{{ __('Business') }}</button></li>
            <li :class="{ active: step === 2 }"><button type="button" @click="next()"><span>2</span>{{ __('Owner login') }}</button></li>
        </ol>

        <div x-ref="s1" x-show="step === 1">
            <x-input name="business_name" icon="building" :label="__('Business name')" required maxlength="150" autocomplete="organization" x-on:input="$el.classList.remove('is-invalid')" />
            <div class="row g-2">
                <div class="col-sm-6"><x-state-select name="state" :label="__('State')" /></div>
                <div class="col-sm-6"><x-city-input name="city" state-field="state" :label="__('City')" required x-on:input="$el.classList.remove('is-invalid')" /></div>
            </div>
            <div class="wizard-note"><x-icon name="info-circle" />{{ __('You can add GSTIN, address and logo later from your business profile.') }}</div>
            <button type="button" class="btn btn-primary btn-lg w-100" @click="next()">{{ __('Continue') }}<x-icon name="arrow-right" /></button>
        </div>

        <div x-ref="s2" x-show="step === 2" x-cloak>
            <x-input name="name" icon="person" :label="__('Your full name')" required autocomplete="name" />
            <div class="row g-2">
                <div class="col-sm-6"><x-input name="email" type="email" icon="envelope" :label="__('Email')" required autocomplete="email" /></div>
                <div class="col-sm-6"><x-input name="phone" type="tel" icon="phone" :label="__('Mobile number')" required inputmode="numeric" maxlength="14" placeholder="98XXXXXXXX" autocomplete="tel-national" /></div>
                <div class="col-sm-6"><x-input name="password" type="password" icon="lock" :label="__('Password')" required autocomplete="new-password" /></div>
                <div class="col-sm-6"><x-input name="password_confirmation" type="password" icon="lock" :label="__('Confirm password')" required autocomplete="new-password" /></div>
            </div>
            <div class="form-text mt-n2 mb-2">{{ __('Password: min 8 characters with letters and numbers.') }}</div>
            @include('auth.partials.terms')
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-light border btn-lg px-3" @click="step = 1" aria-label="{{ __('Back') }}"><x-icon name="arrow-left" /></button>
                <button type="submit" class="btn btn-primary btn-lg flex-grow-1">{{ __('Create business account') }}<x-icon name="arrow-right" /></button>
            </div>
        </div>
    </form>
    <p class="small text-center mt-3 mb-0 text-muted-2">{{ __('Already registered?') }} <a href="{{ route('login') }}" class="fw-semibold">{{ __('Login') }}</a></p>
</x-layouts.auth>
