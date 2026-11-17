<x-layouts.public :title="__('Contact us')" :description="__('Contact AaoChaleTaxi for bookings, operator partnerships and support.')">
    <section class="hero py-5">
        <div class="container py-3">
            <h1 class="display-6">{{ __('Contact us') }}</h1>
            <p class="lead mb-0">{{ __('We usually respond within a few working hours.') }}</p>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-4">
                    <div class="d-grid gap-4">
                        <div class="d-flex gap-3"><span class="feature-icon"><i class="bi bi-envelope"></i></span><div><div class="fw-semibold">{{ __('Email') }}</div><a href="mailto:{{ config('aaochale.support_email') }}">{{ config('aaochale.support_email') }}</a></div></div>
                        @if (config('aaochale.support_phone'))
                            <div class="d-flex gap-3"><span class="feature-icon"><i class="bi bi-telephone"></i></span><div><div class="fw-semibold">{{ __('Phone') }}</div><a href="tel:{{ config('aaochale.support_phone') }}">{{ config('aaochale.support_phone') }}</a></div></div>
                        @endif
                        <div class="d-flex gap-3"><span class="feature-icon"><i class="bi bi-clock"></i></span><div><div class="fw-semibold">{{ __('Support hours') }}</div><div class="text-body-secondary">{{ __('Mon – Sun, 8:00 AM – 10:00 PM IST') }}</div></div></div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm"><div class="card-body p-4">
                        @include('public.partials.contact-form')
                    </div></div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
