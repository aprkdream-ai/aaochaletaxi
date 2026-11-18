@props(['title', 'subtitle' => null, 'kpis' => []])
{{-- Dashboard welcome banner. $kpis = [['label' => .., 'value' => .., 'icon' => .., 'href' => null], ...] --}}
<section {{ $attributes->class(['hero-banner mb-4']) }}>
    <div class="hero-banner-top">
        <div class="min-w-0">
            <h1 class="hero-banner-title">{{ $title }}</h1>
            @if ($subtitle)<div class="hero-banner-sub">{{ $subtitle }}</div>@endif
        </div>
        @if ($slot->isNotEmpty())<div class="hero-banner-actions">{{ $slot }}</div>@endif
    </div>
    @if ($kpis)
        <div class="hero-kpis">
            @foreach ($kpis as $k)
                @if (! empty($k['href']))
                    <a href="{{ $k['href'] }}" class="hero-kpi">
                @else
                    <div class="hero-kpi">
                @endif
                    <span class="hero-kpi-ico"><x-icon :name="$k['icon']" /></span>
                    <span class="min-w-0"><span class="hero-kpi-value">{{ $k['value'] }}</span><span class="hero-kpi-label">{{ $k['label'] }}</span></span>
                @if (! empty($k['href']))</a>@else</div>@endif
            @endforeach
        </div>
    @endif
</section>
