@props(['title', 'lead' => null, 'crumbs' => [], 'eyebrow' => null])
<section class="hero hero-sm">
    <div class="container">
        <x-breadcrumbs :items="$crumbs" />
        @if ($eyebrow)<div class="eyebrow on-dark">{{ $eyebrow }}</div>@endif
        <h1 class="mb-3" style="max-width: 820px">{{ $title }}</h1>
        @if ($lead)<p class="lead mb-0" style="max-width: 680px">{{ $lead }}</p>@endif
        {{ $slot }}
    </div>
</section>
