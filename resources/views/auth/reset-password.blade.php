<x-layouts.auth :title="__('Set new password')">
    <h1 class="h4 fw-800 mb-4">{{ __('Set a new password') }}</h1>
    <form method="POST" action="{{ route('password.update') }}" data-loading novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-input name="email" type="email" :label="__('Email')" :value="$email" required autocomplete="email" />
        <x-input name="password" type="password" :label="__('New password')" required autocomplete="new-password" />
        <x-input name="password_confirmation" type="password" :label="__('Confirm password')" required autocomplete="new-password" />
        <button type="submit" class="btn btn-primary w-100 py-2">{{ __('Update password') }}</button>
    </form>
</x-layouts.auth>
