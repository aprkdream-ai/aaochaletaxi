@props(['title' => null, 'description' => null, 'noindex' => false, 'mobileCta' => true])
@php
    $nav = [
        'home' => __('Home'),
        'services' => __('Services'),
        'about' => __('About'),
        'partner' => __('For operators'),
        'contact' => __('Contact'),
    ];
    $phone = config('aaochale.support_phone');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-head />
    <x-seo :title="$title" :description="$description" :noindex="$noindex" />
    <x-json-ld :data="[
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'AaoChaleTaxi',
        'url' => route('home'),
        'logo' => asset('apple-touch-icon.png'),
        'email' => config('aaochale.support_email'),
        'slogan' => 'Reliable Taxi Booking & Fleet Management',
    ]" />
    {{ $head ?? '' }}
</head>
<body @class(['d-flex flex-column min-vh-100', 'has-mobile-cta' => $mobileCta])>
<a class="visually-hidden-focusable position-absolute top-0 start-0 m-2 p-2 bg-white rounded z-3" href="#main">{{ __('Skip to content') }}</a>

<header class="site-header">
    <nav class="navbar navbar-dark py-2 py-lg-3" aria-label="{{ __('Main navigation') }}">
        <div class="container">
            <x-brand />
            <ul class="navbar-nav flex-row d-none d-lg-flex mx-auto gap-1">
                @foreach ($nav as $route => $label)
                    <li class="nav-item"><a class="nav-link @if (request()->routeIs($route)) active @endif" href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif>{{ $label }}</a></li>
                @endforeach
            </ul>
            <div class="d-flex align-items-center gap-2">
                <div class="dropdown d-none d-sm-block">
                    <button class="btn btn-glass btn-sm px-2" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Language') }}">
                        <x-icon name="translate" /><span class="text-uppercase small">{{ app()->getLocale() }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        @foreach (\App\Http\Middleware\SetLocale::SUPPORTED as $code => $name)
                            <li><a class="dropdown-item @if (app()->getLocale() === $code) active @endif" href="{{ route('locale', $code) }}" hreflang="{{ $code }}">{{ $name }}</a></li>
                        @endforeach
                    </ul>
                </div>
                @auth
                    <a href="{{ route(auth()->user()->homeRoute()) }}" class="btn btn-primary btn-sm d-none d-sm-inline-flex"><x-icon name="grid-1x2-fill" />{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-glass btn-sm d-none d-md-inline-flex">{{ __('Login') }}</a>
                    <a href="{{ route('home') }}#quote" class="btn btn-primary btn-sm d-none d-sm-inline-flex">{{ __('Book a taxi') }}</a>
                @endauth
                <button class="btn btn-glass btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#navDrawer" aria-controls="navDrawer" aria-label="{{ __('Open menu') }}">
                    <x-icon name="list" class="icon-lg" />
                </button>
            </div>
        </div>
    </nav>
</header>

<div class="offcanvas offcanvas-end nav-drawer" tabindex="-1" id="navDrawer" aria-labelledby="navDrawerLabel">
    <div class="offcanvas-header">
        <span id="navDrawerLabel"><x-brand /></span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="{{ __('Close') }}"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <nav class="nav flex-column">
            @foreach ($nav as $route => $label)
                <a class="nav-link @if (request()->routeIs($route)) active @endif" href="{{ route($route) }}">{{ $label }}<x-icon name="chevron-right" class="opacity-50" /></a>
            @endforeach
            <a class="nav-link" href="{{ route('register.operator') }}">{{ __('Register your taxi business') }}<x-icon name="chevron-right" class="opacity-50" /></a>
        </nav>
        <div class="d-flex gap-2 my-4">
            @foreach (\App\Http\Middleware\SetLocale::SUPPORTED as $code => $name)
                <a href="{{ route('locale', $code) }}" class="btn btn-sm {{ app()->getLocale() === $code ? 'btn-primary' : 'btn-glass' }}">{{ $name }}</a>
            @endforeach
        </div>
        <div class="mt-auto d-grid gap-2">
            @auth
                <a href="{{ route(auth()->user()->homeRoute()) }}" class="btn btn-primary btn-lg">{{ __('Dashboard') }}</a>
            @else
                <a href="{{ route('home') }}#quote" class="btn btn-primary btn-lg" data-bs-dismiss="offcanvas">{{ __('Book a taxi') }}</a>
                <a href="{{ route('login') }}" class="btn btn-glass btn-lg">{{ __('Login') }}</a>
            @endauth
        </div>
    </div>
</div>

<main id="main" class="flex-grow-1">
    {{ $slot }}
</main>

<footer class="site-footer pt-5 mt-auto">
    <div class="container pt-lg-4">
        <div class="row g-4 g-lg-5 pb-5">
            <div class="col-lg-4">
                <x-brand />
                <p class="mt-3 mb-4" style="max-width: 340px">{{ __('Reliable taxi booking for travellers and smart fleet management for taxi operators across India.') }}</p>
                <a href="mailto:{{ config('aaochale.support_email') }}" class="d-inline-flex align-items-center gap-2"><x-icon name="envelope" />{{ config('aaochale.support_email') }}</a>
                @if ($phone)<div class="mt-2"><a href="tel:{{ config('aaochale.support_phone_e164') }}" class="d-inline-flex align-items-center gap-2"><x-icon name="telephone" />{{ $phone }}</a></div>@endif
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <h2>{{ __('Rides') }}</h2>
                <ul class="list-unstyled d-grid gap-2 mb-0">
                    <li><a href="{{ route('services') }}#outstation">{{ __('Outstation taxi') }}</a></li>
                    <li><a href="{{ route('services') }}#airport">{{ __('Airport transfer') }}</a></li>
                    <li><a href="{{ route('services') }}#rental">{{ __('Hourly rental') }}</a></li>
                    <li><a href="{{ route('services') }}#corporate">{{ __('Corporate travel') }}</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <h2>{{ __('Company') }}</h2>
                <ul class="list-unstyled d-grid gap-2 mb-0">
                    <li><a href="{{ route('about') }}">{{ __('About us') }}</a></li>
                    <li><a href="{{ route('contact') }}">{{ __('Contact') }}</a></li>
                    <li><a href="{{ route('partner') }}">{{ __('Pricing') }}</a></li>
                    <li><a href="{{ route('login') }}">{{ __('Login') }}</a></li>
                </ul>
            </div>
            <div class="col-md-4 col-lg-4">
                <h2>{{ __('For taxi operators') }}</h2>
                <p class="mb-3">{{ __('Bookings, fleet, drivers and GST invoices in one simple dashboard.') }}</p>
                <a href="{{ route('register.operator') }}" class="btn btn-primary">{{ __('Start free trial') }}<x-icon name="arrow-right" /></a>
            </div>
        </div>
        <div class="foot-line py-4 d-flex flex-column flex-md-row gap-3 justify-content-between small">
            @php $ownerName = \App\Models\PlatformSetting::getValue('platform_legal_name'); $ownerAddress = \App\Models\PlatformSetting::getValue('platform_address'); @endphp
            <span>&copy; {{ now()->year }} AaoChaleTaxi. {{ __('All rights reserved.') }}@if ($ownerName)<br>{{ __('Owned & operated by :name', ['name' => $ownerName]) }}@if ($ownerAddress) · {{ preg_replace('/[,\s]*\n\s*/', ', ', trim($ownerAddress)) }}@endif @endif</span>
            <nav class="d-flex flex-wrap gap-3" aria-label="{{ __('Legal') }}">
                <a href="{{ route('legal.privacy') }}">{{ __('Privacy') }}</a>
                <a href="{{ route('legal.terms') }}">{{ __('Terms') }}</a>
                <a href="{{ route('legal.refund') }}">{{ __('Cancellation & refunds') }}</a>
                <a href="{{ route('legal.shipping') }}">{{ __('Shipping & delivery') }}</a>
                <a href="{{ route('sitemap') }}">{{ __('Sitemap') }}</a>
            </nav>
        </div>
    </div>
</footer>

@if ($mobileCta)
    <div class="mobile-cta">
        @if ($phone)<a href="tel:{{ config('aaochale.support_phone_e164') }}" class="btn btn-light border"><x-icon name="telephone-fill" />{{ __('Call') }}</a>@endif
        <a href="{{ route('home') }}#quote" class="btn btn-primary"><x-icon name="lightning-charge-fill" />{{ __('Get fare quote') }}</a>
    </div>
@endif
<x-flash />
{{ $scripts ?? '' }}
</body>
</html>
