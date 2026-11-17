<x-layouts.public>
    <x-slot:head>
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'TaxiService',
                'name' => 'AaoChaleTaxi',
                'url' => route('home'),
                'areaServed' => ['@type' => 'Country', 'name' => 'India'],
                'provider' => ['@type' => 'Organization', 'name' => 'AaoChaleTaxi', 'url' => route('home')],
                'serviceType' => ['Outstation taxi', 'Local taxi', 'Airport transfer', 'Railway station transfer', 'Hourly rental', 'Corporate travel'],
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
        </script>
    </x-slot:head>

    <section class="hero">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="badge rounded-pill text-bg-primary mb-3 px-3 py-2">{{ __('Outstation • Local • Airport • Rentals') }}</span>
                    <h1 class="display-5 mb-3">{{ __('Your ride across India,') }} <span class="text-brand">{{ __('on time, every time.') }}</span></h1>
                    <p class="lead mb-4">{{ __('Book verified taxi operators with transparent fares — one-way, round-trip, airport and railway transfers, hourly and full-day rentals.') }}</p>
                    <div class="d-flex flex-wrap gap-4 small text-white-50">
                        <span><i class="bi bi-shield-check text-brand me-1"></i>{{ __('Verified operators') }}</span>
                        <span><i class="bi bi-currency-rupee text-brand me-1"></i>{{ __('No hidden charges') }}</span>
                        <span><i class="bi bi-headset text-brand me-1"></i>{{ __('Support 7 days a week') }}</span>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card hero-card shadow-soft text-body">
                        <div class="card-body p-4">
                            <h2 class="h5 fw-800 mb-1">{{ __('Get a quick fare quote') }}</h2>
                            <p class="small text-body-secondary mb-3">{{ __('Share your trip and we will call you back with the best fare.') }}</p>
                            <form method="POST" action="{{ route('enquiry.store') }}" data-loading novalidate>
                                @csrf
                                <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="form-label" for="trip_type">{{ __('Trip type') }}</label>
                                        <select name="trip_type" id="trip_type" class="form-select @error('trip_type') is-invalid @enderror" required>
                                            @foreach (\App\Http\Controllers\Public\ContactController::TRIP_TYPES as $value => $label)
                                                <option value="{{ $value }}" @selected(old('trip_type') === $value)>{{ __($label) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6"><x-input name="pickup" :label="__('Pickup city / location')" required maxlength="150" /></div>
                                    <div class="col-md-6"><x-input name="drop" :label="__('Drop city / location')" maxlength="150" /></div>
                                    <div class="col-md-6"><x-input name="travel_date" type="date" :label="__('Travel date')" required :min="now()->toDateString()" /></div>
                                    <div class="col-md-6"><x-input name="phone" type="tel" :label="__('Mobile number')" required inputmode="numeric" placeholder="98XXXXXXXX" /></div>
                                    <div class="col-12"><x-input name="name" :label="__('Your name')" required maxlength="150" /></div>
                                </div>
                                <button class="btn btn-primary w-100 py-2" type="submit"><i class="bi bi-telephone-outbound me-1"></i>{{ __('Request callback') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="text-center mb-5">
                <div class="eyebrow mb-2">{{ __('Our services') }}</div>
                <h2 class="section-title">{{ __('A taxi for every journey') }}</h2>
            </div>
            <div class="row g-4">
                @foreach ([
                    ['signpost-split', 'Outstation one-way & round trip', 'Comfortable intercity travel with experienced drivers and fixed, transparent pricing.'],
                    ['airplane', 'Airport transfers', 'On-time pickups and drops with flight-aware scheduling and meet & greet.'],
                    ['train-front', 'Railway station transfers', 'Reliable pickup and drop for every train, day or night.'],
                    ['clock-history', 'Hourly & full-day rentals', 'Keep a car with driver for meetings, shopping or city sightseeing.'],
                    ['geo-alt', 'Local city rides', 'Quick local trips in hatchbacks, sedans and SUVs.'],
                    ['briefcase', 'Corporate travel', 'Monthly billing, GST invoices and dedicated account management.'],
                ] as [$icon, $title, $text])
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body p-4">
                                <span class="feature-icon mb-3"><i class="bi bi-{{ $icon }}"></i></span>
                                <h3 class="h5">{{ __($title) }}</h3>
                                <p class="text-body-secondary mb-0">{{ __($text) }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section bg-white">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="eyebrow mb-2">{{ __('For taxi operators') }}</div>
                    <h2 class="section-title mb-3">{{ __('Run your entire taxi business from one dashboard') }}</h2>
                    <p class="text-body-secondary mb-4">{{ __('Bookings, fleet, drivers, fares, invoices and settlements — built for Indian taxi operators and travel agencies of every size.') }}</p>
                    <ul class="list-unstyled d-grid gap-2 mb-4">
                        @foreach (['Booking & dispatch with conflict-free assignment', 'Vehicle documents with expiry reminders', 'Driver app, trip OTP & earnings', 'GST-ready invoices & payment tracking', 'Your own branded booking page'] as $point)
                            <li><i class="bi bi-check-circle-fill text-success me-2"></i>{{ __($point) }}</li>
                        @endforeach
                    </ul>
                    <a href="{{ route('partner') }}" class="btn btn-dark px-4">{{ __('See plans & pricing') }}</a>
                    <a href="{{ route('register.operator') }}" class="btn btn-primary px-4 ms-2">{{ __('Start free trial') }}</a>
                </div>
                <div class="col-lg-6">
                    <div class="row g-3">
                        @foreach ([['speedometer2', 'Live dashboard'], ['truck-front', 'Fleet management'], ['person-badge', 'Driver management'], ['receipt', 'Billing & GST'], ['graph-up-arrow', 'Reports'], ['phone', 'Driver PWA']] as [$icon, $label])
                            <div class="col-6 col-md-4">
                                <div class="card border-0 bg-body-tertiary text-center h-100">
                                    <div class="card-body py-4">
                                        <span class="feature-icon mb-2"><i class="bi bi-{{ $icon }}"></i></span>
                                        <div class="small fw-semibold">{{ __($label) }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="row g-4 text-center">
                @foreach ([['1', 'Share your trip', 'Tell us pickup, drop, date and vehicle preference.'], ['2', 'Get the best fare', 'Verified operators quote transparent all-inclusive fares.'], ['3', 'Ride with confidence', 'Driver details shared in advance, OTP-verified trip start.']] as [$n, $title, $text])
                    <div class="col-md-4">
                        <div class="display-6 fw-800 text-brand">{{ $n }}</div>
                        <h3 class="h5">{{ __($title) }}</h3>
                        <p class="text-body-secondary">{{ __($text) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts.public>
