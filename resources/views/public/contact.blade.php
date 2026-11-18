<x-layouts.public :title="__('Contact us')" :description="__('Contact AaoChaleTaxi for taxi bookings, operator partnerships and customer support.')">
    <x-page-hero :title="__('We’re here to help')" :lead="__('Questions about a booking, partnership or your account? Send us a message — we usually respond within a few working hours.')" :crumbs="[__('Contact') => null]" />

    <section class="section">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-lg-4">
                    <div class="d-grid gap-3">
                        <a href="mailto:{{ config('aaochale.support_email') }}" class="route-chip">
                            <span class="d-flex align-items-center gap-3"><span class="icon-tile tile-sm"><x-icon name="envelope" /></span><span><span class="d-block small text-muted-2 fw-semibold">{{ __('Email') }}</span>{{ config('aaochale.support_email') }}</span></span>
                            <x-icon name="chevron-right" class="arrow" />
                        </a>
                        @if (config('aaochale.support_phone'))
                            <a href="tel:{{ config('aaochale.support_phone_e164') }}" class="route-chip">
                                <span class="d-flex align-items-center gap-3"><span class="icon-tile tile-sm"><x-icon name="telephone" /></span><span><span class="d-block small text-muted-2 fw-semibold">{{ __('Phone') }}</span>{{ config('aaochale.support_phone') }}</span></span>
                                <x-icon name="chevron-right" class="arrow" />
                            </a>
                        @endif
                        <div class="route-chip">
                            <span class="d-flex align-items-center gap-3"><span class="icon-tile tile-sm"><x-icon name="clock" /></span><span><span class="d-block small text-muted-2 fw-semibold">{{ __('Support hours') }}</span>{{ __('Mon – Sun, 8 AM – 10 PM IST') }}</span></span>
                        </div>
                        <a href="{{ route('home') }}#quote" class="route-chip">
                            <span class="d-flex align-items-center gap-3"><span class="icon-tile tile-sm"><x-icon name="lightning-charge" /></span><span><span class="d-block small text-muted-2 fw-semibold">{{ __('Need a taxi?') }}</span>{{ __('Get a fare quote') }}</span></span>
                            <x-icon name="chevron-right" class="arrow" />
                        </a>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-soft rounded-xl"><div class="card-body p-4 p-lg-5">
                        <h2 class="h4 mb-1">{{ __('Send us a message') }}</h2>
                        <p class="text-muted-2 mb-4">{{ __('Fields marked * are required.') }}</p>
                        @include('public.partials.contact-form')
                    </div></div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
