@props(['title' => null, 'wide' => false, 'asideTitle' => null, 'asidePoints' => null])
@php
    $asideTitle ??= __('Every ride, every rupee, under control.');
    $asidePoints ??= [
        __('Book trusted taxis with transparent fares'),
        __('Track trips, payments and invoices in one place'),
        __('Secure login with role-based access'),
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
            <h2 class="mb-4">{{ $asideTitle }}</h2>
            <ul class="check-list fs-6">
                @foreach ($asidePoints as $point)
                    <li><x-icon name="check-circle-fill" />{{ $point }}</li>
                @endforeach
            </ul>
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
