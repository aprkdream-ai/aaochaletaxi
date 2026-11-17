@props(['icon' => 'inbox', 'title', 'text' => null])
<div class="empty-state">
    <div class="empty-ico"><x-icon :name="$icon" /></div>
    <div class="fw-bold text-body">{{ $title }}</div>
    @if ($text)<div class="small mt-1 mx-auto" style="max-width: 360px">{{ $text }}</div>@endif
    {{ $slot }}
</div>
