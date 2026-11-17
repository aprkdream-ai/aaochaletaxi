<x-layouts.public :title="__('About us')" :description="__('AaoChaleTaxi connects travellers with verified taxi operators across India and gives operators modern software to run their fleet.')">
    <x-page-hero :title="__('Making road travel in India simple, safe and fair')" :lead="__('AaoChaleTaxi is a taxi booking platform and fleet-management software built for India’s taxi operators and the people who ride with them.')" :crumbs="[__('About us') => null]" />

    <section class="section">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6 reveal">
                    <div class="eyebrow">{{ __('Our mission') }}</div>
                    <h2 class="mb-3">{{ __('Empowering lakhs of independent taxi operators') }}</h2>
                    <p class="text-muted-2 fs-5">{{ __('India runs on independent taxi operators and travel agencies. We give them modern tools — bookings, fleet, drivers, billing — so they can deliver better rides, and we give travellers an easy way to book trusted taxis with honest fares.') }}</p>
                </div>
                <div class="col-lg-6">
                    <div class="row g-3">
                        @foreach ([
                            ['person-walking', __('For travellers'), __('Easy booking, transparent fares and verified operators.')],
                            ['buildings', __('For operators'), __('All-in-one software to manage and grow a taxi business.')],
                            ['globe-central-south-asia', __('Made for India'), __('INR pricing, GST invoices, English & Hindi.')],
                            ['phone', __('Mobile first'), __('Works smoothly on any phone, even on slow networks.')],
                        ] as [$ico, $t, $d])
                            <div class="col-sm-6 reveal">
                                <div class="card h-100"><div class="card-body p-4">
                                    <span class="icon-tile mb-3"><x-icon :name="$ico" /></span>
                                    <h3 class="h6 fw-800">{{ $t }}</h3>
                                    <p class="small text-muted-2 mb-0">{{ $d }}</p>
                                </div></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section bg-surface-2">
        <div class="container">
            <x-section-head :eyebrow="__('Our values')" :title="__('What we stand for')" />
            <div class="row g-3 g-lg-4">
                @foreach ([
                    ['shield-check', __('Trust'), __('Every operator goes through KYC verification before going live on the platform.')],
                    ['currency-rupee', __('Transparency'), __('Fare components — tolls, taxes, allowances — are always shown upfront.')],
                    ['people', __('Customer first'), __('Responsive support and clear, fair cancellation policies.')],
                    ['lock', __('Privacy'), __('Your data is protected and never sold. Each operator’s data is kept separate.')],
                ] as [$ico, $t, $d])
                    <div class="col-sm-6 col-lg-3 reveal">
                        <div class="step text-center">
                            <span class="icon-tile tile-ink mb-3 mx-auto"><x-icon :name="$ico" /></span>
                            <h3 class="h5">{{ $t }}</h3>
                            <p class="text-muted-2 small mb-0">{{ $d }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="cta reveal">
                <div class="row align-items-center g-4 position-relative" style="z-index:1">
                    <div class="col-lg-8"><h2 class="h1 fw-800 mb-2">{{ __('Own taxis? Let’s grow together.') }}</h2><p class="mb-0 fs-5">{{ __('Join AaoChaleTaxi and manage your fleet like a pro.') }}</p></div>
                    <div class="col-lg-4 text-lg-end"><a href="{{ route('register.operator') }}" class="btn btn-ink btn-lg">{{ __('Start free trial') }}<x-icon name="arrow-right" /></a></div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
