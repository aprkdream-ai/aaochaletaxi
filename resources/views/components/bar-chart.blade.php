@props(['series', 'label', 'height' => 140, 'money' => false])
{{-- Single-series bar chart (no legend: the card title names it). $series = [['label' => 'Mon 3', 'value' => 4], ...] --}}
@php
    $max = max(1, ...array_map(fn ($p) => (float) $p['value'], $series ?: [['value' => 0]]));
    $fmt = fn ($v) => $money ? \App\Support\Money::format($v, 0) : number_format((float) $v);
    $total = array_sum(array_map(fn ($p) => (float) $p['value'], $series));
@endphp
<figure {{ $attributes->class(['bar-chart m-0']) }}>
    <div class="bar-chart-plot" style="height: {{ $height }}px" role="img" aria-label="{{ $label }}: {{ collect($series)->map(fn ($p) => $p['label'].' '.$fmt($p['value']))->join(', ') }}">
        <span class="bar-chart-max">{{ $fmt($max) }}</span>
        @foreach ($series as $p)
            <div class="bar-chart-col" tabindex="0" aria-label="{{ $p['label'] }}: {{ $fmt($p['value']) }}">
                <span class="bar-chart-bar {{ (float) $p['value'] <= 0 ? 'is-zero' : '' }}" style="height: {{ (float) $p['value'] > 0 ? max(3, round((float) $p['value'] / $max * 100, 1)) : 0 }}%"></span>
                <span class="bar-chart-tip"><b>{{ $fmt($p['value']) }}</b>{{ $p['label'] }}</span>
            </div>
        @endforeach
    </div>
    <figcaption class="d-flex justify-content-between small text-muted-2 mt-2">
        <span>{{ $series[0]['label'] ?? '' }}</span><span>{{ __('Total') }}: <b class="text-body">{{ $fmt($total) }}</b></span><span>{{ $series[count($series) - 1]['label'] ?? '' }}</span>
    </figcaption>
</figure>
