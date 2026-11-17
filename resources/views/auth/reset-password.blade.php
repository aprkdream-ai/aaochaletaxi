<x-layouts.auth :title="__('Set new password')">
    <span class="icon-tile mb-3"><x-icon name="shield-lock" /></span>
    <h1 class="h3 mb-4">{{ __('Set a new password') }}</h1>
    <form method="POST" action="{{ route('password.update') }}" data-loading novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-input name="email" type="email" icon="envelope" :label="__('Email')" :value="$email" required autocomplete="email" />
        <x-input name="password" type="password" icon="lock" :label="__('New password')" required autocomplete="new-password" :help="__('Min 8 characters with letters and numbers.')" />
        <x-input name="password_confirmation" type="password" icon="lock" :label="__('Confirm password')" required autocomplete="new-password" />
        <button type="submit" class="btn btn-primary btn-lg w-100">{{ __('Update password') }}</button>
    </form>
</x-layouts.auth>
