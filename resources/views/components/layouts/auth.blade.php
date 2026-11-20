@props(['title' => null, 'wide' => false, 'asideTitle' => null, 'asidePoints' => null])
@php
    $asideTitle ??= __('Every ride, every rupee, under control.');
    $asidePoints ??= [
        ['shield-check', __('Verified operators'), __('KYC-checked businesses and drivers')],
        ['receipt', __('Transparent fares'), __('Every rupee itemised, GST invoices included')],
        ['phone', __('One login for everyone'), __('Customers, operators and drivers — role-based access')],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-head />
    <x-seo :title="$title" noindex />
</head>
<body>
<div class="auth-shell">
    <aside class="auth-aside">
        <x-brand />
        <div class="my-auto py-5">
            <h2 class="mb-3">{{ $asideTitle }}</h2>
            <p class="auth-aside-lead mb-4">{{ __('India’s taxi booking & fleet platform — for travellers and taxi businesses.') }}</p>
            <div class="auth-features mb-5">
                @foreach ($asidePoints as $point)
                    @php [$pIcon, $pTitle, $pText] = is_array($point) ? $point : ['check-circle-fill', $point, null]; @endphp
                    <div class="auth-feature">
                        <span class="auth-feature-ico"><x-icon :name="$pIcon" /></span>
                        <div><div class="auth-feature-title">{{ $pTitle }}</div>@if ($pText)<div class="auth-feature-text">{{ $pText }}</div>@endif</div>
                    </div>
                @endforeach
            </div>
            <div class="auth-preview" aria-hidden="true">
                <div class="auth-preview-card">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="fw-bold text-white small">{{ __('Trip status') }}</span>
                        <span class="auth-preview-tag">{{ __('Illustrative preview') }}</span>
                    </div>
                    <div class="auth-timeline">
                        <div class="done"><span></span>{{ __('Booking confirmed') }}</div>
                        <div class="done"><span></span>{{ __('Driver & vehicle assigned') }}</div>
                        <div class="now"><span></span>{{ __('Trip started with your OTP') }}</div>
                        <div><span></span>{{ __('Drop · GST invoice ready') }}</div>
                    </div>
                </div>
                <div class="auth-float f1"><x-icon name="shield-lock-fill" />{{ __('OTP verified') }}</div>
                <div class="auth-float f2"><x-icon name="check-circle-fill" />{{ __('Payment received') }}</div>
            </div>
        </div>
        <div class="small opacity-75">&copy; {{ now()->year }} AaoChaleTaxi · <a href="{{ route('legal.privacy') }}" class="text-reset">{{ __('Privacy') }}</a></div>
    </aside>
    <main class="auth-main">
        <div @class(['auth-box', 'wide' => $wide])>
            <div class="text-center mb-4 d-lg-none"><x-brand dark /></div>
            <div class="auth-card">
                {{ $slot }}
            </div>
            <div class="text-center mt-4 small"><a href="{{ route('home') }}" class="text-muted-2 d-inline-flex align-items-center gap-1"><x-icon name="arrow-left" />{{ __('Back to website') }}</a></div>
        </div>
    </main>
</div>
<x-flash :hide-errors="true" />
</body>
</html>
