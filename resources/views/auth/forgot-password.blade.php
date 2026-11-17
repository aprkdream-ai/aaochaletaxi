<x-layouts.auth :title="__('Forgot password')">
    <h1 class="h4 fw-800 mb-1">{{ __('Reset your password') }}</h1>
    <p class="text-body-secondary mb-4">{{ __('Enter your registered email and we will send you a reset link.') }}</p>
    @if (session('success'))<div class="alert alert-success small">{{ session('success') }}</div>@endif
    <form method="POST" action="{{ route('password.email') }}" data-loading novalidate>
        @csrf
        <x-input name="email" type="email" :label="__('Email')" required autofocus autocomplete="email" />
        <button type="submit" class="btn btn-primary w-100 py-2">{{ __('Send reset link') }}</button>
    </form>
    <p class="small text-center mt-4 mb-0"><a href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> {{ __('Back to login') }}</a></p>
</x-layouts.auth>
