<x-layouts.auth :title="__('Forgot password')">
    <span class="icon-tile mb-3"><x-icon name="key" /></span>
    <h1 class="h3 mb-1">{{ __('Forgot your password?') }}</h1>
    <p class="text-muted-2 mb-4">{{ __('Enter your registered email and we’ll send you a secure reset link.') }}</p>
    @if (session('success'))<div class="alert alert-success small d-flex gap-2"><x-icon name="check-circle-fill" class="mt-1" />{{ session('success') }}</div>@endif
    <form method="POST" action="{{ route('password.email') }}" data-loading novalidate>
        @csrf
        <x-input name="email" type="email" icon="envelope" :label="__('Email')" required autofocus autocomplete="email" />
        <button type="submit" class="btn btn-primary btn-lg w-100">{{ __('Send reset link') }}<x-icon name="send" /></button>
    </form>
    <p class="small text-center mt-4 mb-0"><a href="{{ route('login') }}" class="fw-semibold d-inline-flex align-items-center gap-1"><x-icon name="arrow-left" />{{ __('Back to login') }}</a></p>
</x-layouts.auth>
