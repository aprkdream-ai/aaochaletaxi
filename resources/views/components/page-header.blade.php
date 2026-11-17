@props(['title', 'subtitle' => null])
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div>
        <h1 class="h4 mb-0">{{ $title }}</h1>
        @if ($subtitle)<div class="text-body-secondary small mt-1">{{ $subtitle }}</div>@endif
    </div>
    @if ($slot->isNotEmpty())<div class="d-flex gap-2">{{ $slot }}</div>@endif
</div>
