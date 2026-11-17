@props(['status'])
@php
    $color = method_exists($status, 'badge') ? $status->badge() : 'secondary';
    $label = method_exists($status, 'label') ? $status->label() : ucfirst((string) $status);
@endphp
<span {{ $attributes->class(["badge rounded-pill text-bg-{$color}"]) }}>{{ $label }}</span>
