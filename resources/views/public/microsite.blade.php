@php
    use App\Support\Money;
    $color = $tenant->primary_color ?: '#ffb703';
    $about = $tenant->settings['about'] ?? null;
@endphp
<x-layouts.public :title="$tenant->name.' — '.__('Book a taxi online')" :description="__('Book :name taxis online: outstation, airport, local and rentals in :city with transparent fares.', ['name' => $tenant->name, 'city' => $tenant->city])" :mobile-cta="false">
    <x-slot:head>
        <x-json-ld :data="array_filter([
            '@context' => 'https://schema.org', '@type' => 'TaxiService', 'name' => $tenant->name, 'url' => route('microsite.show', $tenant->slug),
            'areaServed' => $tenant->city, 'telephone' => '+91'.$tenant->phone, 'logo' => $tenant->logoUrl(),
            'aggregateRating' => $rating['count'] ? ['@type' => 'AggregateRating', 'ratingValue' => $rating['avg'], 'reviewCount' => $rating['count']] : null,
        ])" />
    </x-slot:head>

    <section class="hero hero-sm" style="--brand: {{ $color }}">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        @if ($tenant->logoUrl())<img src="{{ $tenant->logoUrl() }}" alt="{{ $tenant->name }}" class="rounded-4 bg-white p-1" style="width:64px;height:64px;object-fit:contain">@else<span class="avatar" style="width:64px;height:64px;font-size:1.4rem;background:{{ $color }}">{{ mb_strtoupper(mb_substr($tenant->name, 0, 2)) }}</span>@endif
                        <div>
                            <h1 class="mb-1" style="font-size: clamp(1.8rem, 4vw, 2.6rem)">{{ $tenant->name }}</h1>
                            <div class="d-flex flex-wrap gap-3 small text-white-50">
                                <span><x-icon name="geo-alt-fill" /> {{ $tenant->city }}</span>
                                @if ($tenant->kyc_status->value === 'verified')<span class="text-white"><x-icon name="patch-check-fill" class="text-brand" /> {{ __('Verified operator') }}</span>@endif
                                @if ($rating['count'])<span class="text-white"><x-icon name="star-fill" class="text-brand" /> {{ $rating['avg'] }} · {{ trans_choice('{1} 1 review|[2,*] :n reviews', $rating['count'], ['n' => $rating['count']]) }}</span>@endif
                            </div>
                        </div>
                    </div>
                    <p class="lead mb-0">{{ $about ?: __('Comfortable, on-time taxis for outstation trips, airport transfers, local rides and rentals. Book online in a minute.') }}</p>
                </div>
                <div class="col-lg-5 d-none d-lg-block">
                    <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                        <a href="tel:+91{{ $tenant->phone }}" class="btn btn-glass"><x-icon name="telephone-fill" />{{ __('Call') }} {{ $tenant->phone }}</a>
                        <a href="https://wa.me/91{{ $tenant->phone }}" target="_blank" rel="noopener" class="btn btn-glass"><x-icon name="whatsapp" />WhatsApp</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section pt-4 pt-lg-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-8">
                    <form method="POST" action="{{ route('microsite.store', $tenant->slug) }}" class="card panel-card shadow-soft" novalidate x-ref="form"
                          x-data="micrositeForm(@js(['preview' => route('microsite.preview', $tenant->slug), 'otp' => route('microsite.otp.send', $tenant->slug), 'verify' => route('microsite.otp.verify', $tenant->slug)]), @js($verifiedPhone))"
                          @change="estimate()" @input.debounce.800ms="estimate()" @submit.prevent="submit()">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                        <div class="card-header"><span class="d-flex align-items-center gap-2"><x-icon name="calendar2-check" />{{ __('Book your ride') }}</span></div>
                        <div class="card-body">
                            @if ($errors->any())<div class="alert alert-danger small">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
                            <div class="row g-2">
                                <div class="col-md-6"><div class="mb-3"><label class="form-label" for="m_type">{{ __('Trip type') }}</label>
                                    <select name="type" id="m_type" class="form-select" x-model="type">@foreach ($types as $v => $l)<option value="{{ $v }}" @selected(old('type', 'outstation_oneway') === $v)>{{ $l }}</option>@endforeach</select></div></div>
                                <div class="col-md-6"><x-select name="vehicle_category_id" :label="__('Vehicle')" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->name.' · '.$c->seats.' '.__('seats')])->all()" :value="old('vehicle_category_id', $categories->firstWhere('slug', 'sedan')?->id ?? $categories->first()?->id)" required /></div>
                                <div class="col-12"><x-input name="pickup_address" icon="geo-alt-fill" :label="__('Pickup address')" :value="old('pickup_address')" required maxlength="255" :placeholder="__('Area, landmark, city')" /></div>
                                <div class="col-12" x-show="type !== 'hourly' && type !== 'full_day'"><x-input name="drop_address" icon="pin-map-fill" :label="__('Drop address')" :value="old('drop_address')" maxlength="255" :placeholder="__('Destination')" /></div>
                                <div class="col-md-6"><x-input name="pickup_at" type="datetime-local" :label="__('Pickup date & time')" :value="old('pickup_at', now()->addDay()->setTime(8, 0)->format('Y-m-d\TH:i'))" :min="now()->addHour()->format('Y-m-d\TH:i')" required /></div>
                                <div class="col-md-6" x-show="type === 'outstation_round'" x-cloak><x-input name="return_at" type="datetime-local" :label="__('Return date & time')" :value="old('return_at')" /></div>
                                <div class="col-md-3" x-show="type === 'hourly' || type === 'full_day'" x-cloak><x-input name="rental_hours" type="number" min="1" max="24" :label="__('Hours')" :value="old('rental_hours', 4)" /></div>
                                <div class="col-md-3"><x-input name="passengers" type="number" min="1" max="60" :label="__('Passengers')" :value="old('passengers', 1)" /></div>
                            </div>
                            <hr class="my-3">
                            <div class="row g-2">
                                <div class="col-md-6"><x-input name="customer_name" icon="person" :label="__('Your name')" :value="old('customer_name', auth()->user()?->type?->value === 'customer' ? auth()->user()->name : null)" required maxlength="150" autocomplete="name" /></div>
                                <div class="col-md-6"><x-input name="customer_phone" type="tel" icon="phone" :label="__('Mobile number')" :value="old('customer_phone', $verifiedPhone)" required inputmode="numeric" maxlength="14" autocomplete="tel-national" /></div>
                                <div class="col-md-6"><x-input name="customer_email" type="email" :label="__('Email (optional)')" :value="old('customer_email')" autocomplete="email" /></div>
                                <div class="col-md-6"><x-input name="notes" :label="__('Anything we should know?')" :value="old('notes')" maxlength="500" :placeholder="__('Luggage, child seat…')" /></div>
                            </div>

                            <div class="rounded-4 p-3 mt-2" style="background: var(--brand-soft)" x-show="otpSent" x-cloak>
                                <div class="fw-semibold mb-1"><x-icon name="shield-lock" /> {{ __('Verify your mobile number') }}</div>
                                <div class="small mb-2" x-text="otpMsg"></div>
                                <div class="d-flex gap-2"><input type="text" inputmode="numeric" maxlength="6" class="form-control" style="max-width:180px" placeholder="{{ __('6-digit code') }}" x-model="otp" x-ref="otpInput" aria-label="{{ __('Verification code') }}" @keydown.enter.prevent="verify()">
                                    <button type="button" class="btn btn-ink" @click="verify()">{{ __('Verify & book') }}</button>
                                    <button type="button" class="btn btn-link btn-sm" @click="submit()">{{ __('Resend') }}</button></div>
                            </div>
                            <div class="text-danger small mt-2" x-show="otpErr" x-text="otpErr" x-cloak></div>
                        </div>
                        <div class="card-body border-top d-flex flex-wrap align-items-center gap-3">
                            <div class="me-auto">
                                <template x-if="fare"><div><div class="small text-muted-2">{{ __('Estimated fare (incl. GST)') }}</div><div class="fs-3 fw-800" x-text="inr(fare.total)"></div><div class="small text-muted-2" x-text="fare.estimated_km ? (fare.estimated_km + ' km · ' + '{{ __('tolls & parking extra at actuals') }}') : ''"></div></div></template>
                                <div class="small text-muted-2" x-show="!fare" x-text="fareMsg || '{{ __('Fill in pickup, drop and time to see the fare.') }}'"></div>
                            </div>
                            <button class="btn btn-primary btn-lg" x-show="!otpSent" :disabled="busy"><x-icon name="check2-circle" />{{ __('Book now') }}</button>
                        </div>
                    </form>
                    <p class="small text-muted-2 mt-2"><x-icon name="info-circle" /> {{ __('Final fare is based on actual km. You only pay after the operator confirms. See our') }} <a href="{{ route('legal.refund') }}">{{ __('cancellation policy') }}</a>.</p>
                </div>
                <div class="col-lg-4">
                    <div class="card panel-card mb-3"><div class="card-header">{{ __('Our fleet') }}</div>
                        <div class="list-group list-group-flush list-modern">
                            @foreach ($categories as $c)
                                <div class="list-group-item d-flex align-items-center gap-3"><span class="icon-tile tile-sm"><x-icon :name="$c->icon" /></span><div class="me-auto"><div class="fw-semibold">{{ $c->name }}</div><div class="small text-muted-2">{{ $c->seats }} {{ __('seats') }}</div></div>@if ($fleet[$c->id] ?? 0)<span class="badge-soft ok">{{ trans_choice('{1} 1 car|[2,*] :n cars', $fleet[$c->id], ['n' => $fleet[$c->id]]) }}</span>@endif</div>
                            @endforeach
                        </div>
                    </div>
                    @if ($reviews->isNotEmpty())
                        <div class="card panel-card mb-3"><div class="card-header">{{ __('What riders say') }}</div>
                            <div class="list-group list-group-flush list-modern small">
                                @foreach ($reviews as $r)
                                    <div class="list-group-item"><div class="text-brand">@for ($i = 1; $i <= 5; $i++)<x-icon :name="$i <= $r->rating ? 'star-fill' : 'star'" />@endfor</div>@if ($r->comment)<div class="mt-1">{{ $r->comment }}</div>@endif<div class="text-muted-2 mt-1">{{ $r->created_at->format('M Y') }}</div></div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <div class="card panel-card"><div class="card-body d-grid gap-2">
                        <a href="tel:+91{{ $tenant->phone }}" class="btn btn-light border"><x-icon name="telephone" />{{ __('Call :p', ['p' => $tenant->phone]) }}</a>
                        <a href="https://wa.me/91{{ $tenant->phone }}" target="_blank" rel="noopener" class="btn btn-light border"><x-icon name="whatsapp" />{{ __('Chat on WhatsApp') }}</a>
                    </div></div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
