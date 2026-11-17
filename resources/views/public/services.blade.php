<x-layouts.public :title="__('Taxi services')" :description="__('Outstation one-way and round trips, airport and railway transfers, local rides, hourly and full-day rentals and corporate travel.')">
    <section class="hero py-5">
        <div class="container py-3">
            <h1 class="display-6">{{ __('Our taxi services') }}</h1>
            <p class="lead mb-0">{{ __('Hatchbacks, sedans, SUVs, MUVs, premium cars, tempo travellers and buses.') }}</p>
        </div>
    </section>
    <section class="section">
        <div class="container">
            <div class="row g-4">
                @foreach ([
                    ['geo-alt', 'Local taxi', 'Point-to-point rides within the city, billed by distance with a minimum fare.'],
                    ['arrow-right-circle', 'Outstation one-way', 'Pay only for one side. Ideal for intercity drops.'],
                    ['arrow-repeat', 'Outstation round trip', 'Keep the car for the full journey; per-km pricing with driver allowance.'],
                    ['airplane', 'Airport pickup & drop', 'Fixed fares for airport transfers with waiting time included.'],
                    ['train-front', 'Railway station pickup & drop', 'Timely transfers for early morning and late night trains.'],
                    ['clock', 'Hourly rental', 'Packages like 4 hrs / 40 km and 8 hrs / 80 km.'],
                    ['sun', 'Full-day rental', 'A car with driver at your disposal for the whole day.'],
                    ['building', 'Corporate booking', 'Employee travel with consolidated GST invoices.'],
                ] as [$icon, $title, $text])
                    <div class="col-md-6 col-lg-3">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body p-4">
                                <span class="feature-icon mb-3"><i class="bi bi-{{ $icon }}"></i></span>
                                <h2 class="h6 fw-bold">{{ __($title) }}</h2>
                                <p class="small text-body-secondary mb-0">{{ __($text) }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="text-center mt-5">
                <a href="{{ route('home') }}#main" class="btn btn-primary px-4">{{ __('Get a fare quote') }}</a>
            </div>
        </div>
    </section>
</x-layouts.public>
