@props(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false])
@php
    $siteName = config('app.name');
    $fullTitle = $title ? $title.' | '.$siteName : $siteName.' — '.__('Reliable Taxi Booking & Fleet Management');
    $description = $description ?? __('Book outstation, local, airport and rental taxis across India, or run your taxi business with AaoChaleTaxi fleet management software.');
@endphp
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical ?? url()->current() }}">
@if ($noindex)<meta name="robots" content="noindex, nofollow">@endif
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical ?? url()->current() }}">
<meta property="og:locale" content="{{ app()->getLocale() === 'hi' ? 'hi_IN' : 'en_IN' }}">
<meta name="twitter:card" content="summary_large_image">
