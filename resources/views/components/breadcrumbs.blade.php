@props(['items' => []])
{{-- $items: [label => url|null]; Home is prepended automatically. Renders visible trail + BreadcrumbList schema. --}}
@php
    $trail = array_merge([__('Home') => route('home')], $items);
    $pos = 0;
    $schema = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => collect($trail)->map(function ($url, $label) use (&$pos) {
        return array_filter(['@type' => 'ListItem', 'position' => ++$pos, 'name' => $label, 'item' => $url ?? url()->current()]);
    })->values()->all()];
@endphp
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="d-flex flex-wrap gap-2 list-unstyled small mb-0" style="color: rgba(255,255,255,.6)">
        @foreach ($trail as $label => $url)
            <li class="d-flex align-items-center gap-2">
                @if (! $loop->last)
                    <a href="{{ $url }}" class="text-reset">{{ $label }}</a><x-icon name="chevron-right" class="opacity-50" />
                @else
                    <span class="text-white fw-semibold" aria-current="page">{{ $label }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
<x-json-ld :data="$schema" />
