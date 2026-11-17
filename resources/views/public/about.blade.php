<x-layouts.public :title="__('About us')" :description="__('AaoChaleTaxi connects travellers with verified taxi operators across India and gives operators the software to run their fleet.')">
    <section class="hero py-5">
        <div class="container py-3">
            <h1 class="display-6">{{ __('About AaoChaleTaxi') }}</h1>
            <p class="lead mb-0">{{ __('Reliable Taxi Booking & Fleet Management.') }}</p>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-7">
                    <h2 class="section-title h3">{{ __('Our mission') }}</h2>
                    <p>{{ __('India runs on lakhs of independent taxi operators. AaoChaleTaxi gives them modern tools — bookings, fleet, drivers, billing — so they can deliver better rides, and gives travellers an easy way to book trusted taxis with transparent fares.') }}</p>
                    <h2 class="section-title h3 mt-4">{{ __('What we do') }}</h2>
                    <ul>
                        <li>{{ __('Taxi booking for outstation, local, airport, railway and rental trips.') }}</li>
                        <li>{{ __('Fleet management software (SaaS) for taxi operators and travel agencies.') }}</li>
                        <li>{{ __('Branded booking pages so operators can take bookings under their own name.') }}</li>
                    </ul>
                </div>
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4 d-grid gap-3">
                            @foreach ([['shield-check', 'Verified operators', 'Every operator goes through KYC before going live.'], ['currency-rupee', 'Transparent pricing', 'Fare breakdowns with tolls, taxes and allowances shown upfront.'], ['people', 'Customer first', 'Responsive support and clear cancellation policies.']] as [$icon, $t, $d])
                                <div class="d-flex gap-3">
                                    <span class="feature-icon flex-shrink-0"><i class="bi bi-{{ $icon }}"></i></span>
                                    <div><div class="fw-semibold">{{ __($t) }}</div><div class="small text-body-secondary">{{ __($d) }}</div></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
