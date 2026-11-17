<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0b1220">
<meta name="format-detection" content="telephone=no">
<script>
    (function () {
        var d = document.documentElement; d.classList.add('js');
        try { var t = localStorage.getItem('theme'); if (t === 'dark' || t === 'light') d.setAttribute('data-bs-theme', t); } catch (e) {}
    })();
</script>
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400..800&display=swap">
@vite(['resources/scss/app.scss', 'resources/js/app.js'])
