@props(['title'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-head />
    <x-seo :title="$title" noindex />
</head>
<body class="bg-body-tertiary">
<div class="driver-shell bg-body d-flex flex-column">
    <header class="bg-dark text-white px-3 py-3 d-flex align-items-center sticky-top">
        <span class="brand-logo fs-5"><i class="bi bi-taxi-front-fill me-1"></i>AaoChale<span>Taxi</span></span>
        <form method="POST" action="{{ route('logout') }}" class="ms-auto">@csrf
            <button class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i> {{ __('Logout') }}</button>
        </form>
    </header>
    <main class="p-3 flex-grow-1">{{ $slot }}</main>
</div>
<x-flash />
</body>
</html>
