@props(['title', 'panel' => 'operator'])
@php
    $user = auth()->user();
    $items = collect(config("navigation.$panel", []))
        ->filter(fn ($i) => isset($i['section']) || empty($i['permission']) || $user->hasPermission($i['permission']));
    // Drop section headers with no visible items after them.
    $items = $items->values()->filter(function ($item, $idx) use ($items) {
        if (! isset($item['section'])) return true;
        $next = $items->values()->get($idx + 1);
        return $next && ! isset($next['section']);
    });
    $tenant = app(\App\Support\Tenancy\TenantContext::class)->get();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-head />
    <x-seo :title="$title" noindex />
</head>
<body x-data="{ sidebar: false }">
<aside class="panel-sidebar" :class="{ 'show': sidebar }" aria-label="{{ __('Sidebar') }}">
    <div class="px-4 py-4 border-bottom border-secondary-subtle border-opacity-10">
        <a class="brand-logo" href="{{ route($user->homeRoute()) }}"><i class="bi bi-taxi-front-fill me-1"></i>AaoChale<span>Taxi</span></a>
        <div class="small text-white-50 mt-1 text-truncate">{{ $panel === 'admin' ? __('Platform administration') : ($tenant?->name ?? '') }}</div>
    </div>
    <nav class="pb-4">
        @foreach ($items as $item)
            @if (isset($item['section']))
                <div class="nav-section">{{ __($item['section']) }}</div>
            @else
                @php $active = request()->routeIs($item['active'] ?? $item['route']); @endphp
                <a href="{{ route($item['route'], $item['params'] ?? []) }}" class="nav-link @if($active) active @endif" @if($active) aria-current="page" @endif>
                    <i class="bi bi-{{ $item['icon'] }}"></i><span>{{ __($item['label']) }}</span>
                </a>
            @endif
        @endforeach
    </nav>
</aside>
<div class="sidebar-backdrop d-lg-none" x-show="sidebar" x-cloak @click="sidebar = false"></div>

<div class="panel-main d-flex flex-column">
    <header class="panel-topbar px-3 px-lg-4 py-2 d-flex align-items-center gap-2">
        <button class="btn btn-light d-lg-none" @click="sidebar = !sidebar" aria-label="{{ __('Toggle menu') }}"><i class="bi bi-list"></i></button>
        <div class="fw-semibold text-truncate">{{ $title }}</div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <button class="btn btn-light btn-sm" @click="$store.theme.toggle()" aria-label="{{ __('Toggle theme') }}">
                <i class="bi" :class="$store.theme.value === 'dark' ? 'bi-sun' : 'bi-moon-stars'"></i>
            </button>
            <div class="dropdown">
                <button class="btn btn-light btn-sm dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-circle"></i><span class="d-none d-sm-inline">{{ $user->name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-body-secondary">{{ $user->type->label() }}</span></li>
                    <li><a class="dropdown-item" href="{{ route('home') }}"><i class="bi bi-globe me-2"></i>{{ __('Website') }}</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>{{ __('Logout') }}</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>
    <main class="p-3 p-lg-4 flex-grow-1">
        {{ $slot }}
    </main>
</div>
<x-flash />
</body>
</html>
