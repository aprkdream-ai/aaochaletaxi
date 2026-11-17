@props(['eyebrow' => null, 'title', 'text' => null, 'align' => 'center'])
<div {{ $attributes->class(['section-head reveal', 'text-start mx-0' => $align === 'start']) }}>
    @if ($eyebrow)<div class="eyebrow">{{ $eyebrow }}</div>@endif
    <h2 class="mb-3">{{ $title }}</h2>
    @if ($text)<p class="mb-0">{{ $text }}</p>@endif
</div>
