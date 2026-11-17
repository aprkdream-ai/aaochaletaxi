@props(['icon' => 'inbox', 'title', 'text' => null])
<div class="empty-state">
    <i class="bi bi-{{ $icon }} d-block mb-2"></i>
    <div class="fw-semibold text-body">{{ $title }}</div>
    @if ($text)<div class="small mt-1">{{ $text }}</div>@endif
    {{ $slot }}
</div>
