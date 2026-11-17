@props(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false, 'image' => null])
@php
    $siteName = config('app.name');
    $fullTitle = $title ? $title.' | '.$siteName : $siteName.' — '.__('Book Outstation, Airport & Local Taxi in India');
    $description = $description ?? __('Book verified outstation, one-way, round-trip, airport, railway and local taxis across India with transparent fares. Taxi operators: manage your fleet with AaoChaleTaxi.');
    $canonical = $canonical ?? url()->current();
    $image = $image ?? asset('og-image.png');
@endphp
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="{{ $noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large' }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="{{ app()->getLocale() === 'hi' ? 'hi_IN' : 'en_IN' }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $fullTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $image }}">
