@props(['title' => null, 'width' => 'col-md-7 col-lg-5'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-head />
    <x-seo :title="$title" noindex />
</head>
<body>
<main class="auth-wrap">
    <div class="container">
        <div class="row justify-content-center">
            <div class="{{ $width }}">
                <div class="text-center mb-4">
                    <a class="brand-logo fs-3" href="{{ route('home') }}"><i class="bi bi-taxi-front-fill me-1"></i>AaoChale<span>Taxi</span></a>
                </div>
                <div class="card auth-card shadow-soft">
                    <div class="card-body p-4 p-md-5">
                        {{ $slot }}
                    </div>
                </div>
                <p class="text-center small mt-4 text-white-50">&copy; {{ now()->year }} AaoChaleTaxi</p>
            </div>
        </div>
    </div>
</main>
<x-flash :hide-errors="true" />
</body>
</html>
