@props(['title', 'back' => null])
@php
    $tabs = [['driver.home', 'house', __('Trips')], ['driver.check', 'clipboard-check', __('Check')], ['driver.history', 'clock-history', __('History')], ['driver.earnings', 'wallet2', __('Earnings')], ['driver.profile', 'person-circle', __('Profile')]];
    $push = \App\Http\Controllers\Driver\PwaController::webPushConfig();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-head :manifest="route('driver.manifest')" />
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Driver">
    <x-seo :title="$title" noindex />
</head>
<body class="bg-surface-2">
<div class="driver-shell d-flex flex-column" x-data="driverApp(@js(['poll' => route('driver.poll'), 'sw' => route('driver.sw'), 'token' => route('driver.push-token'), 'push' => $push]))">
    <header class="driver-top bg-ink text-white px-3 d-flex align-items-center gap-2 sticky-top">
        @if ($back)
            <a href="{{ $back }}" class="btn btn-sm btn-glass px-2" aria-label="{{ __('Back') }}"><x-icon name="arrow-left" /></a>
            <span class="fw-bold text-truncate">{{ $title }}</span>
        @else
            <x-brand />
        @endif
        <span class="ms-auto d-flex align-items-center gap-2">
            <template x-if="offline"><span class="badge rounded-pill text-bg-danger"><x-icon name="wifi-off" /> {{ __('Offline') }}</span></template>
            <template x-if="installable"><button type="button" class="btn btn-sm btn-warning" @click="install()"><x-icon name="download" />{{ __('Install app') }}</button></template>
        </span>
    </header>
    <template x-if="fresh > 0">
    <div class="driver-alert bg-warning text-dark px-3 py-2 small fw-semibold d-flex align-items-center gap-2" role="status">
        <x-icon name="bell-fill" /><span x-text="freshText"></span>
        <a href="{{ route('driver.home') }}" class="ms-auto btn btn-sm btn-ink">{{ __('View') }}</a>
    </div>
    </template>
    <main class="p-3 flex-grow-1 driver-main" id="main">{{ $slot }}</main>
    <nav class="driver-tabs" aria-label="{{ __('Driver menu') }}">
        @foreach ($tabs as [$route, $icon, $label])
            <a href="{{ route($route) }}" @class(['active' => request()->routeIs($route) || ($route === 'driver.home' && request()->routeIs('driver.trips.*'))]) @if (request()->routeIs($route)) aria-current="page" @endif>
                <x-icon :name="$icon" /><span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
</div>
<x-flash />
<script>window.__driverI18n = @js(['new' => __('New trip assigned!'), 'many' => __(':n new trips assigned!')]);</script>
</body>
</html>
