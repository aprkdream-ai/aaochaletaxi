@props(['expiry'])
<span {{ $attributes->class(['badge-soft', $expiry['tone']]) }}>{{ $expiry['label'] }}</span>
