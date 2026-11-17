@props(['title' => null, 'description' => null, 'noindex' => false])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-head />
    <x-seo :title="$title" :description="$description" :noindex="$noindex" />
    {{ $head ?? '' }}
</head>
<body class="d-flex flex-column min-vh-100">
<a class="visually-hidden-focusable position-absolute p-2 bg-white" href="#main">{{ __('Skip to content') }}</a>
<header>
    <nav class="navbar navbar-expand-lg navbar-dark site-nav sticky-top py-3" aria-label="{{ __('Main navigation') }}">
        <div class="container">
            <a class="brand-logo" href="{{ route('home') }}"><i class="bi bi-taxi-front-fill me-1"></i>AaoChale<span>Taxi</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="siteNav">
                <ul class="navbar-nav mx-auto gap-lg-2">
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('home')) active @endif" href="{{ route('home') }}">{{ __('Home') }}</a></li>
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('services')) active @endif" href="{{ route('services') }}">{{ __('Services') }}</a></li>
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('about')) active @endif" href="{{ route('about') }}">{{ __('About us') }}</a></li>
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('partner')) active @endif" href="{{ route('partner') }}">{{ __('Become a partner') }}</a></li>
                    <li class="nav-item"><a class="nav-link @if(request()->routeIs('contact')) active @endif" href="{{ route('contact') }}">{{ __('Contact') }}</a></li>
                </ul>
                <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-translate"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @foreach (\App\Http\Middleware\SetLocale::SUPPORTED as $code => $name)
                                <li><a class="dropdown-item @if(app()->getLocale() === $code) active @endif" href="{{ route('locale', $code) }}">{{ $name }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                    @auth
                        <a href="{{ route(auth()->user()->homeRoute()) }}" class="btn btn-primary btn-sm px-3">{{ __('My dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-outline-light btn-sm px-3">{{ __('Login') }}</a>
                        <a href="{{ route('register.operator') }}" class="btn btn-primary btn-sm px-3">{{ __('Operator sign up') }}</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>
</header>

<main id="main" class="flex-grow-1">
    {{ $slot }}
</main>

<footer class="site-footer pt-5 pb-4 mt-auto">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <a class="brand-logo" href="{{ route('home') }}"><i class="bi bi-taxi-front-fill me-1"></i>AaoChale<span>Taxi</span></a>
                <p class="mt-3 small">{{ __('Reliable Taxi Booking & Fleet Management. Trusted taxi operators, transparent fares, on-time rides across India.') }}</p>
            </div>
            <div class="col-6 col-lg-2">
                <h2 class="h6 text-white">{{ __('Company') }}</h2>
                <ul class="list-unstyled small d-grid gap-2">
                    <li><a href="{{ route('about') }}">{{ __('About us') }}</a></li>
                    <li><a href="{{ route('services') }}">{{ __('Services') }}</a></li>
                    <li><a href="{{ route('contact') }}">{{ __('Contact') }}</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h2 class="h6 text-white">{{ __('Operators') }}</h2>
                <ul class="list-unstyled small d-grid gap-2">
                    <li><a href="{{ route('partner') }}">{{ __('Become a partner') }}</a></li>
                    <li><a href="{{ route('register.operator') }}">{{ __('Operator sign up') }}</a></li>
                    <li><a href="{{ route('login') }}">{{ __('Operator login') }}</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <h2 class="h6 text-white">{{ __('Legal') }}</h2>
                <ul class="list-unstyled small d-grid gap-2">
                    <li><a href="{{ route('legal.privacy') }}">{{ __('Privacy policy') }}</a></li>
                    <li><a href="{{ route('legal.terms') }}">{{ __('Terms & conditions') }}</a></li>
                    <li><a href="{{ route('legal.refund') }}">{{ __('Cancellation & refund policy') }}</a></li>
                </ul>
            </div>
        </div>
        <hr class="border-secondary my-4">
        <div class="small d-flex flex-wrap justify-content-between gap-2">
            <span>&copy; {{ now()->year }} AaoChaleTaxi. {{ __('All rights reserved.') }}</span>
            <span>{{ __('Made in India') }} 🇮🇳</span>
        </div>
    </div>
</footer>
<x-flash />
{{ $scripts ?? '' }}
</body>
</html>
