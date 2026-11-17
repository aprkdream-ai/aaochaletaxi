@props(['title'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-head />
    <x-seo :title="$title" noindex />
</head>
<body class="bg-surface-2">
<div class="driver-shell d-flex flex-column">
    <header class="bg-ink text-white px-3 py-3 d-flex align-items-center sticky-top">
        <x-brand />
        <form method="POST" action="{{ route('logout') }}" class="ms-auto">@csrf
            <button class="btn btn-sm btn-glass"><x-icon name="box-arrow-right" />{{ __('Logout') }}</button>
        </form>
    </header>
    <main class="p-3 flex-grow-1" id="main">{{ $slot }}</main>
</div>
<x-flash />
</body>
</html>
