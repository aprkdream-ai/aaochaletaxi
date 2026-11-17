@props(['label', 'value', 'icon' => 'bar-chart', 'color' => 'primary', 'href' => null, 'hint' => null])
<div class="stat">
    <div class="d-flex justify-content-between align-items-start gap-2">
        <div class="min-w-0">
            <div class="stat-label">{{ $label }}</div>
            <div class="stat-value text-truncate">{{ $value }}</div>
            @if ($hint)<div class="small text-muted-2 mt-1">{{ $hint }}</div>@endif
        </div>
        <div class="stat-ico tone-{{ $color }}"><x-icon :name="$icon" /></div>
    </div>
    @if ($href)<a href="{{ $href }}" class="stretched-link" aria-label="{{ $label }}"></a>@endif
</div>
