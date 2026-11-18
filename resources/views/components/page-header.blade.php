@props(['title', 'subtitle' => null])
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="min-w-0">
        <h1 class="h3 mb-1 text-truncate">{{ $title }}</h1>
        @if ($subtitle)<div class="text-muted-2 small">{{ $subtitle }}</div>@endif
    </div>
    @if ($slot->isNotEmpty())<div class="d-flex flex-wrap align-items-center gap-2">{{ $slot }}</div>@endif
</div>
