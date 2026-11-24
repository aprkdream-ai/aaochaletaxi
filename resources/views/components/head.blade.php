@props(['manifest' => null, 'turbo' => false])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0b1220">
<meta name="format-detection" content="telephone=no">
<script>window.__ui = @js(['cancel' => __('Cancel'), 'confirm' => __('Yes, continue'), 'confirmTitle' => __('Please confirm'), 'confirmDangerTitle' => __('Are you sure?'), 'suggestNone' => __('No suggestions — you can type the full address.'), 'suggestRecent' => __('Recent')]);</script>
<script>
    (function () {
        var d = document.documentElement; d.classList.add('js');
        try { var t = localStorage.getItem('theme'); if (t === 'dark' || t === 'light') d.setAttribute('data-bs-theme', t); } catch (e) {}
    })();
</script>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ $manifest ?? asset('site.webmanifest') }}">
@php try { $fontUrl = \Illuminate\Support\Facades\Vite::asset('resources/fonts/plus-jakarta-sans-latin.woff2'); } catch (\Throwable) { $fontUrl = null; } @endphp
@if ($fontUrl)<link rel="preload" href="{{ $fontUrl }}" as="font" type="font/woff2" crossorigin>@endif
@vite(['resources/scss/app.scss', 'resources/js/app.js'])
@if ($turbo)
{{-- Panels use Turbo Drive (no full reloads; Turbo prefetches on hover itself). No snapshot cache: Alpine state is never restored stale. --}}
<meta name="turbo-cache-control" content="no-cache">
@else
{{-- Instant navigation: fetch a page when the user hovers/presses a link (Chrome/Edge). Pages with side effects are excluded. --}}
<script type="speculationrules">{"prefetch":[{"where":{"and":[{"href_matches":"/*"},{"not":{"href_matches":["/logout","/locale/*","/driver/*","/b/*","/webhooks/*","/*/pdf","/*/file","/*/export*","/*/download*","/*invoice*"]}},{"not":{"selector_matches":"[data-no-prefetch], [target=_blank], [download]"}}]},"eagerness":"moderate"}]}</script>
@endif
