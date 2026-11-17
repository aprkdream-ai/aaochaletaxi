@props(['label', 'value', 'icon' => 'bar-chart', 'color' => 'primary', 'href' => null])
<div class="card stat-card h-100">
    <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon bg-{{ $color }}-subtle text-{{ $color }}-emphasis"><i class="bi bi-{{ $icon }}"></i></div>
        <div>
            <div class="text-body-secondary small">{{ $label }}</div>
            <div class="fs-4 fw-bold lh-sm">{{ $value }}</div>
        </div>
        @if ($href)<a href="{{ $href }}" class="stretched-link" aria-label="{{ $label }}"></a>@endif
    </div>
</div>
