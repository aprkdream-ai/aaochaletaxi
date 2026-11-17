<x-layouts.auth :title="__('Register your taxi business')" width="col-lg-8 col-xl-7">
    <h1 class="h4 fw-800 mb-1">{{ __('Register your taxi business') }}</h1>
    <p class="text-body-secondary mb-4">{{ __('Start a free trial. No credit card required. Our team verifies every operator before activation.') }}</p>

    <form method="POST" action="{{ route('register.operator.store') }}" data-loading novalidate>
        @csrf
        <h2 class="h6 text-uppercase text-body-secondary small mb-3">{{ __('Business') }}</h2>
        <div class="row">
            <div class="col-md-12"><x-input name="business_name" :label="__('Business name')" required maxlength="150" /></div>
            <div class="col-md-6"><x-input name="city" :label="__('City')" required maxlength="100" /></div>
            <div class="col-md-6"><x-input name="state" :label="__('State')" maxlength="100" /></div>
        </div>
        <h2 class="h6 text-uppercase text-body-secondary small mb-3 mt-2">{{ __('Owner / admin login') }}</h2>
        <div class="row">
            <div class="col-md-12"><x-input name="name" :label="__('Your full name')" required autocomplete="name" /></div>
            <div class="col-md-6"><x-input name="email" type="email" :label="__('Email')" required autocomplete="email" /></div>
            <div class="col-md-6"><x-input name="phone" type="tel" :label="__('Mobile number')" required inputmode="numeric" placeholder="98XXXXXXXX" autocomplete="tel" /></div>
            <div class="col-md-6"><x-input name="password" type="password" :label="__('Password')" required autocomplete="new-password" :help="__('Min 8 characters with letters and numbers.')" /></div>
            <div class="col-md-6"><x-input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" /></div>
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" value="1" id="terms" @checked(old('terms'))>
            <label class="form-check-label small" for="terms">
                {!! __('I agree to the :terms and :privacy.', [
                    'terms' => '<a href="'.e(route('legal.terms')).'" target="_blank">'.e(__('Terms & conditions')).'</a>',
                    'privacy' => '<a href="'.e(route('legal.privacy')).'" target="_blank">'.e(__('Privacy policy')).'</a>',
                ]) !!}
            </label>
            @error('terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2">{{ __('Create business account') }}</button>
    </form>
    <p class="small text-center mt-4 mb-0">{{ __('Already registered?') }} <a href="{{ route('login') }}">{{ __('Login') }}</a></p>
</x-layouts.auth>
