<x-layouts.auth :title="__('Create account')">
    <h1 class="h4 fw-800 mb-1">{{ __('Create your account') }}</h1>
    <p class="text-body-secondary mb-4">{{ __('Book rides faster and track all your trips in one place.') }}</p>

    <form method="POST" action="{{ route('register.customer.store') }}" data-loading novalidate>
        @csrf
        <x-input name="name" :label="__('Full name')" required autocomplete="name" />
        <x-input name="phone" type="tel" :label="__('Mobile number')" required inputmode="numeric" placeholder="98XXXXXXXX" autocomplete="tel" />
        <x-input name="email" type="email" :label="__('Email (optional)')" autocomplete="email" />
        <x-input name="password" type="password" :label="__('Password')" required autocomplete="new-password" :help="__('Min 8 characters with letters and numbers.')" />
        <x-input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" />
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
        <button type="submit" class="btn btn-primary w-100 py-2">{{ __('Create account') }}</button>
    </form>
    <p class="small text-center mt-4 mb-0">{{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Login') }}</a></p>
</x-layouts.auth>
