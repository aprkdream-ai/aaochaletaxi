@props(['title', 'panel' => 'operator'])
@php
    $user = auth()->user();
    $visible = collect(config("navigation.$panel", []))
        ->filter(fn ($i) => isset($i['section']) || empty($i['permission']) || $user->hasPermission($i['permission']))
        ->values();
    $items = $visible->filter(fn ($item, $idx) => ! isset($item['section']) || (($next = $visible->get($idx + 1)) && ! isset($next['section'])));
    $tenant = app(\App\Support\Tenancy\TenantContext::class)->get();
    $navBadges = $panel === 'admin' && $user->hasPermission('admin.support.manage') ? [
        'tickets' => \App\Models\SupportTicket::query()->where('status', 'open')->count(),
        'enquiries' => \App\Models\ContactMessage::query()->whereNull('handled_at')->count(),
    ] : [];
    if ($panel === 'operator' && $user->hasPermission('bookings.manage')) {
        $navBadges['leads'] = \App\Models\MarketplaceLeadOffer::query()->where('status', 'pending')
            ->whereHas('lead', fn ($q) => $q->where('status', 'open')->where('expires_at', '>', now()))->count();
    }
    $initials = collect(explode(' ', $user->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-head :turbo="true" />
    <x-seo :title="$title" noindex />
</head>
<body class="panel-body" data-turbo="true" x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false">
<aside class="panel-sidebar" :class="{ 'show': sidebar }" aria-label="{{ __('Sidebar') }}">
    <div class="side-head">
        <x-brand :href="route($user->homeRoute())" />
        <div class="tenant-chip">
            <span class="avatar">{{ $panel === 'admin' ? 'SA' : mb_strtoupper(mb_substr($tenant?->name ?? 'A', 0, 2)) }}</span>
            <div class="min-w-0">
                <div class="text-white fw-semibold text-truncate">{{ $panel === 'admin' ? __('Platform admin') : ($tenant?->name ?? '') }}</div>
                <div class="text-truncate opacity-75">{{ $panel === 'operator' ? ($user->tenantRoles()->pluck('name')->first() ?? $user->type->label()) : $user->type->label() }}</div>
            </div>
        </div>
    </div>
    <nav aria-label="{{ __('Panel navigation') }}">
        @foreach ($items as $item)
            @if (isset($item['section']))
                <div class="nav-section">{{ __($item['section']) }}</div>
            @else
                @php $active = ($item['active'] ?? null) !== '__none__' && request()->routeIs($item['active'] ?? $item['route']); @endphp
                <a href="{{ route($item['route'], $item['params'] ?? []) }}" @class(['nav-link', 'active' => $active]) @if ($active) aria-current="page" @endif>
                    <x-icon :name="$item['icon']" /><span>{{ __($item['label']) }}</span>
                    @if (! empty($item['badge']) && ($navBadges[$item['badge']] ?? 0) > 0)<span class="ms-auto badge rounded-pill text-bg-warning">{{ $navBadges[$item['badge']] }}</span>@endif
                </a>
            @endif
        @endforeach
    </nav>
    <div class="side-foot">AaoChaleTaxi · v{{ config('aaochale.version') }}</div>
</aside>
<div class="sidebar-backdrop d-lg-none" x-show="sidebar" x-transition.opacity x-cloak @click="sidebar = false"></div>

<div class="panel-main">
    <header class="panel-topbar px-3 px-lg-4 py-2 d-flex align-items-center gap-2">
        <button class="btn btn-light btn-icon d-lg-none" @click="sidebar = !sidebar" aria-label="{{ __('Toggle menu') }}"><x-icon name="list" class="icon-lg" /></button>
        <div class="fw-bold text-truncate">{{ $title }}</div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <a href="{{ route('home') }}" class="btn btn-light btn-icon d-none d-sm-inline-flex" title="{{ __('Website') }}" aria-label="{{ __('Website') }}"><x-icon name="globe2" /></a>
            <button class="btn btn-light btn-icon" @click="$store.theme.toggle()" aria-label="{{ __('Toggle theme') }}">
                <span x-show="$store.theme.value !== 'dark'"><x-icon name="moon-stars" /></span>
                <span x-show="$store.theme.value === 'dark'" x-cloak><x-icon name="sun" /></span>
            </button>
            <div class="dropdown">
                <button class="btn btn-light d-flex align-items-center gap-2 py-1 ps-1 pe-2 rounded-pill" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar" style="width:2rem;height:2rem">{{ mb_strtoupper($initials) }}</span>
                    <span class="d-none d-sm-inline small fw-semibold">{{ $user->name }}</span>
                    <x-icon name="chevron-down" class="small opacity-50" />
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><div class="dropdown-item-text"><div class="fw-semibold">{{ $user->name }}</div><div class="small text-muted-2">{{ $user->email ?? $user->phone }}</div></div></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}" data-turbo="false">@csrf
                            <button class="dropdown-item text-danger d-flex align-items-center gap-2"><x-icon name="box-arrow-right" />{{ __('Logout') }}</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>
    <main class="p-3 p-lg-4 flex-grow-1" id="main">
        @error('limit')
            <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 rounded-4">
                <x-icon name="speedometer" class="icon-lg" /><div class="me-auto">{{ $message }}</div>
                @if (\Illuminate\Support\Facades\Route::has('operator.subscription') && $panel === 'operator')<a href="{{ route('operator.subscription') }}" class="btn btn-sm btn-ink">{{ __('Upgrade plan') }}</a>@endif
            </div>
        @enderror
        {{ $slot }}
    </main>
</div>
<x-flash />
</body>
</html>
