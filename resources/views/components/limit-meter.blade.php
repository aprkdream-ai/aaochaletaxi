@props(['used', 'limit', 'label'])
@if ($limit)
    @php $pct = min(100, (int) round($used / max(1, $limit) * 100)); @endphp
    <div class="d-flex align-items-center gap-2 small text-muted-2" title="{{ __('Plan limit') }}">
        <span>{{ $label }}: <b class="text-body">{{ $used }}/{{ $limit }}</b></span>
        <div class="progress" style="width: 90px; height: 6px" role="progressbar" aria-valuenow="{{ $used }}" aria-valuemax="{{ $limit }}" aria-label="{{ $label }}"><div class="progress-bar {{ $pct >= 100 ? 'bg-danger' : ($pct >= 80 ? 'bg-warning' : 'bg-success') }}" style="width: {{ $pct }}%"></div></div>
    </div>
@endif
