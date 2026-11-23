@php use App\Support\Money; @endphp
<x-layouts.public :title="__('Compare taxi operators & fares')" :description="__('Compare verified local taxi operators, see fares upfront and book outstation, airport, local and rental cabs across India.')">
    <section class="hero hero-sm"><div class="container">
        <h1 class="mb-2" style="font-size: clamp(1.7rem, 4vw, 2.5rem)">{{ __('Compare cabs from local operators') }}</h1>
        <p class="lead text-white-50 mb-4">{{ __('Fares calculated by each operator’s own rates. Book directly or let up to :n operators compete for your trip.', ['n' => \App\Support\PlatformSettings::int('marketplace_broadcast_count', 3)]) }}</p>
        @include('public.marketplace._search-form', ['q' => $q ?? []])
    </div></section>

    <section class="section"><div class="container">
        @if ($found)
            @php $results = $found['results']; $query = collect($q)->only(['type', 'vehicle_category_id', 'pickup_at', 'return_at', 'rental_hours', 'from_city', 'to_city'])->filter()->all(); @endphp
            <div class="d-flex flex-wrap align-items-end gap-2 mb-3">
                <h2 class="h4 mb-0 me-auto">{{ trans_choice('{0} No operators found|{1} 1 operator|[2,*] :n operators', $results->count(), ['n' => $results->count()]) }} · {{ $q['from_city'] }}@if (! empty($q['to_city'])) → {{ $q['to_city'] }}@endif</h2>
                @if ($found['km'])<span class="small text-muted-2">{{ __('about :km km', ['km' => number_format($found['km'])]) }}</span>@endif
            </div>
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="row g-3">
                        @forelse ($results as $r)
                            <div class="col-md-6">
                                @component('public.marketplace._operator-card', ['tenant' => $r->tenant, 'stats' => $r->stats])
                                    <div class="mt-auto d-flex align-items-end gap-2">
                                        <div class="me-auto">@if ($r->estimate)<div class="small text-muted-2">{{ __('Estimated (incl. GST)') }}</div><div class="fs-4 fw-800">{{ Money::format($r->estimate, 0) }}</div>@else<div class="small text-muted-2">{{ __('Rate') }}</div><div class="fs-5 fw-800">{{ Money::format($r->per_km) }}/km</div>@endif</div>
                                        <a href="{{ route('marketplace.go', [$r->tenant->slug] + $query) }}" class="btn btn-ink">{{ __('Book') }}<x-icon name="arrow-right" /></a>
                                    </div>
                                @endcomponent
                            </div>
                        @empty
                            <div class="col-12"><div class="card panel-card"><x-empty-state icon="search" :title="__('No operator serves this trip yet')" :text="__('Try another vehicle type, or a nearby city.')" /></div></div>
                        @endforelse
                    </div>
                    <p class="small text-muted-2 mt-3">{{ __('Estimates use road distance between the cities; the operator confirms the final fare. Tolls, parking and state tax are extra at actuals.') }}</p>
                </div>
                @if ($results->isNotEmpty())
                    <div class="col-lg-4">
                        <form method="POST" action="{{ route('marketplace.quote') }}" class="card panel-card" x-data="quoteForm(@js(['otp' => route('marketplace.otp.send'), 'verify' => route('marketplace.otp.verify')]), @js($verifiedPhone))" x-ref="form" @submit.prevent="submit()">@csrf
                            @foreach ($query as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                            <div class="card-header"><span class="d-flex align-items-center gap-2"><x-icon name="megaphone" />{{ __('Get the best offer') }}</span></div>
                            <div class="card-body">
                                <p class="small text-muted-2">{{ __('We send your trip to the top :n operators. The first to accept calls you to confirm.', ['n' => min($results->count(), \App\Support\PlatformSettings::int('marketplace_broadcast_count', 3))]) }}</p>
                                <x-input name="pickup_address" :label="__('Pickup address')" maxlength="255" :placeholder="$q['from_city']" />
                                @if (! empty($q['to_city']))<x-input name="drop_address" :label="__('Drop address')" maxlength="255" :placeholder="$q['to_city']" />@endif
                                <x-input name="customer_name" icon="person" :label="__('Your name')" required maxlength="150" :value="auth()->user()?->type?->value === 'customer' ? auth()->user()->name : null" />
                                <x-input name="customer_phone" type="tel" icon="phone" :label="__('Mobile number')" required inputmode="numeric" maxlength="14" :value="$verifiedPhone" />
                                <x-input name="passengers" type="number" min="1" max="60" :label="__('Passengers')" value="1" />
                                <div class="rounded-4 p-3 mb-2" style="background: var(--brand-soft)" x-show="otpSent" x-cloak>
                                    <div class="small mb-2" x-text="otpMsg"></div>
                                    <div class="d-flex gap-2"><input type="text" inputmode="numeric" maxlength="6" class="form-control" placeholder="{{ __('6-digit code') }}" x-model="otp" x-ref="otpInput" aria-label="{{ __('Verification code') }}" @keydown.enter.prevent="verify()"><button type="button" class="btn btn-ink" @click="verify()">{{ __('Verify') }}</button></div>
                                </div>
                                <div class="text-danger small mb-2" x-show="otpErr" x-text="otpErr" x-cloak></div>
                                @if ($errors->any())<div class="text-danger small mb-2">{{ $errors->first() }}</div>@endif
                                <button class="btn btn-primary w-100" x-show="!otpSent" :disabled="busy"><x-icon name="send" />{{ __('Send request') }}</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        @else
            @if ($cities)
                <h2 class="h5 mb-3">{{ __('Cities with listed operators') }}</h2>
                <div class="d-flex flex-wrap gap-2">@foreach ($cities as $c)<a href="{{ route('marketplace.city', \App\Models\TenantServiceCity::key($c)) }}" class="btn btn-light border btn-sm">{{ $c }}</a>@endforeach</div>
            @endif
        @endif
    </div></section>
</x-layouts.public>
