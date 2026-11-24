@php
    $legal = \App\Models\PlatformSetting::getValue('platform_legal_name') ?: config('app.name');
    $support = config('aaochale.support_email');
    $phone = config('aaochale.support_phone');
    $site = rtrim(config('app.url'), '/');
@endphp
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ __('Need help?') }} [{{ $support }}](mailto:{{ $support }})@if ($phone) · {{ $phone }}@endif

[{{ __('Website') }}]({{ $site }}) · [{{ __('Privacy policy') }}]({{ route('legal.privacy') }}) · [{{ __('Terms') }}]({{ route('legal.terms') }})

© {{ date('Y') }} {{ $legal }}. {{ __('This is an automated email — please do not reply directly.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
