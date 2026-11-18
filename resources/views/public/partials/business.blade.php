@php
    $legalName = \App\Models\PlatformSetting::getValue('platform_legal_name');
    $legalAddress = \App\Models\PlatformSetting::getValue('platform_address');
    $legalGstin = \App\Models\PlatformSetting::getValue('platform_gstin');
@endphp
@if ($legalName)
    <p>{{ __('This website and the AaoChaleTaxi platform are owned and operated by') }} <strong>{{ $legalName }}</strong>.</p>
    @if ($legalAddress)<p class="mb-1"><strong>{{ __('Registered address') }}:</strong></p><p style="white-space: pre-line">{{ $legalAddress }}</p>@endif
    @if ($legalGstin)<p><strong>GSTIN:</strong> {{ $legalGstin }}</p>@endif
@endif
<p>{{ __('Email') }}: <a href="mailto:{{ config('aaochale.support_email') }}">{{ config('aaochale.support_email') }}</a>@if (config('aaochale.support_phone')) · {{ __('Phone') }}: <a href="tel:{{ config('aaochale.support_phone_e164') }}">{{ config('aaochale.support_phone') }}</a>@endif</p>
