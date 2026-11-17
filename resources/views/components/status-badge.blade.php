@props(['status'])
@php
    $tone = method_exists($status, 'badge') ? $status->badge() : 'secondary';
    $map = ['success' => 'ok', 'warning' => 'warn', 'info' => 'info', 'danger' => 'danger', 'secondary' => 'muted'];
    $label = method_exists($status, 'label') ? $status->label() : ucfirst((string) $status);
@endphp
<span {{ $attributes->class(['badge-soft', $map[$tone] ?? 'muted']) }}>{{ $label }}</span>
