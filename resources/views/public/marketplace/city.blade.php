<x-layouts.public :title="__('Taxi operators in :city — compare & book', ['city' => $city])" :description="__('Compare :n taxi operators in :city: outstation, airport, local and rental cabs with upfront fares.', ['n' => $operators->count(), 'city' => $city])">
    <section class="hero hero-sm"><div class="container">
        <h1 class="mb-2" style="font-size: clamp(1.7rem, 4vw, 2.5rem)">{{ __('Taxi operators in :city', ['city' => $city]) }}</h1>
        <p class="lead text-white-50 mb-4">{{ trans_choice('{1} 1 operator lists fares here.|[2,*] :n operators list fares here.', $operators->count(), ['n' => $operators->count()]) }}</p>
        @include('public.marketplace._search-form', ['q' => ['from_city' => $city]])
    </div></section>
    <section class="section"><div class="container"><div class="row g-3">
        @foreach ($operators as $t)
            <div class="col-md-6 col-lg-4">
                @component('public.marketplace._operator-card', ['tenant' => $t, 'stats' => $t->mp_stats])
                    @if ($t->settings['about'] ?? null)<p class="small text-muted-2 mb-3">{{ \Illuminate\Support\Str::limit($t->settings['about'], 140) }}</p>@endif
                    <a href="{{ route('marketplace.go', $t->slug) }}" class="btn btn-ink mt-auto">{{ __('View fares & book') }}<x-icon name="arrow-right" /></a>
                @endcomponent
            </div>
        @endforeach
    </div></div></section>
</x-layouts.public>
