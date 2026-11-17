<x-layouts.public :title="__('Become a partner')" :description="__('Taxi fleet management software for Indian operators. Plans from ₹799/month with a free trial.')">
    <section class="hero py-5">
        <div class="container py-3">
            <h1 class="display-6">{{ __('Grow your taxi business with AaoChaleTaxi') }}</h1>
            <p class="lead mb-4">{{ __('Simple monthly plans. Free trial. Cancel anytime — your data stays yours.') }}</p>
            <a href="{{ route('register.operator') }}" class="btn btn-primary btn-lg px-4">{{ __('Start free trial') }}</a>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <div class="row g-4 justify-content-center">
                @forelse ($plans as $plan)
                    <div class="col-md-6 col-lg-4">
                        <div class="card plan-card h-100 @if($plan->code === 'growth') featured shadow-soft @else shadow-sm border-0 @endif">
                            <div class="card-body p-4 d-flex flex-column">
                                @if ($plan->code === 'growth')<span class="badge text-bg-primary align-self-start mb-2">{{ __('Most popular') }}</span>@endif
                                <h2 class="h4 fw-800">{{ $plan->name }}</h2>
                                <p class="text-body-secondary small">{{ $plan->description }}</p>
                                <div class="mb-3"><span class="display-6 fw-800">{{ \App\Support\Money::format($plan->price_monthly) }}</span><span class="text-body-secondary">/{{ __('month') }}</span></div>
                                <ul class="list-unstyled d-grid gap-2 small mb-4">
                                    <li><i class="bi bi-check2 text-success me-2"></i>{{ __('Up to :n vehicles', ['n' => $plan->max_vehicles]) }}</li>
                                    <li><i class="bi bi-check2 text-success me-2"></i>{{ __('Up to :n drivers', ['n' => $plan->max_drivers]) }}</li>
                                    <li><i class="bi bi-check2 text-success me-2"></i>{{ trans_choice('{1} :n branch|[2,*] :n branches', $plan->max_branches, ['n' => $plan->max_branches]) }}</li>
                                    @foreach ($plan->features ?? [] as $feature)
                                        <li><i class="bi bi-check2 text-success me-2"></i>{{ __($feature) }}</li>
                                    @endforeach
                                </ul>
                                <a href="{{ route('register.operator') }}" class="btn {{ $plan->code === 'growth' ? 'btn-primary' : 'btn-outline-dark' }} mt-auto">{{ __('Start :days-day free trial', ['days' => $plan->trial_days]) }}</a>
                            </div>
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="tags" :title="__('Plans will be published soon.')" />
                @endforelse
            </div>
            <p class="text-center small text-body-secondary mt-4">{{ __('Prices exclusive of GST. Annual billing available with discount.') }}</p>
        </div>
    </section>
    <section class="section bg-white">
        <div class="container" style="max-width: 760px">
            <h2 class="section-title h3 text-center mb-4">{{ __('Talk to our team') }}</h2>
            @include('public.partials.contact-form', ['type' => 'partner'])
        </div>
    </section>
</x-layouts.public>
