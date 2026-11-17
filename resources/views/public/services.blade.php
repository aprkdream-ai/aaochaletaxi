@php
    $groups = [
        'outstation' => [__('Outstation travel'), [
            ['signpost-split', __('Outstation one-way'), __('Pay only for one side. Ideal for intercity drops, relocations and airport runs to another city.'), [__('Fixed fare for the route'), __('No return charges'), __('Sedan, SUV & more')], 'outstation_oneway'],
            ['arrow-repeat', __('Outstation round trip'), __('Keep the car for your entire journey — sightseeing, family visits or business trips.'), [__('Per-km pricing'), __('Driver allowance included in quote'), __('Multi-day trips')], 'outstation_round'],
        ]],
        'airport' => [__('Airport & railway transfers'), [
            ['airplane', __('Airport pickup & drop'), __('Fixed-fare transfers to and from the airport with waiting time included.'), [__('Flight-aware pickup'), __('Meet & greet on request'), __('Day & night')], 'airport'],
            ['train-front', __('Railway station pickup & drop'), __('Timely transfers for early-morning and late-night trains.'), [__('Platform-side coordination'), __('Luggage friendly cars'), __('Fixed fares')], 'railway'],
        ]],
        'rental' => [__('Local & rentals'), [
            ['geo-alt', __('Local taxi'), __('Point-to-point rides within the city, billed by distance with a minimum fare.'), [__('Clean AC cars'), __('Multiple stops'), __('Pay by UPI / cash')], 'local'],
            ['clock-history', __('Hourly rental'), __('Packages like 4 hrs / 40 km and 8 hrs / 80 km for meetings and shopping.'), [__('Extra km & hours as per quote'), __('Car waits for you'), __('Flexible plans')], 'hourly'],
            ['sun', __('Full-day rental'), __('A car with driver at your disposal for the whole day.'), [__('City tours'), __('Weddings & events'), __('Tempo travellers & buses')], 'full_day'],
        ]],
        'corporate' => [__('Business travel'), [
            ['briefcase', __('Corporate booking'), __('Employee and guest travel with consolidated GST invoices and monthly billing.'), [__('GST-ready invoices'), __('Dedicated coordinator'), __('Trip reports')], 'corporate'],
        ]],
    ];
@endphp
<x-layouts.public :title="__('Taxi services — outstation, airport, local & rentals')" :description="__('Outstation one-way and round trips, airport and railway transfers, local rides, hourly and full-day rentals and corporate travel across India.')">
    <x-page-hero :title="__('Taxi services for every kind of trip')" :lead="__('Hatchbacks, sedans, SUVs, MUVs, tempo travellers and buses — with verified operators and transparent fares.')" :crumbs="[__('Services') => null]" />

    <section class="section">
        <div class="container">
            @foreach ($groups as $anchor => [$heading, $items])
                <div id="{{ $anchor }}" class="mb-5 pb-lg-3">
                    <h2 class="h3 mb-4 reveal">{{ $heading }}</h2>
                    <div class="row g-3 g-lg-4">
                        @foreach ($items as [$ico, $title, $text, $points, $trip])
                            <div class="col-md-6 col-lg-4 reveal">
                                <div class="card card-hover h-100">
                                    <div class="card-body p-4 d-flex flex-column">
                                        <span class="icon-tile mb-3"><x-icon :name="$ico" /></span>
                                        <h3 class="h5">{{ $title }}</h3>
                                        <p class="text-muted-2">{{ $text }}</p>
                                        <ul class="check-list small mb-4">
                                            @foreach ($points as $p)<li><x-icon name="check2" class="text-success" />{{ $p }}</li>@endforeach
                                        </ul>
                                        <a href="{{ route('home', ['trip' => $trip]) }}#quote" class="btn btn-ink mt-auto align-self-start">{{ __('Get quote') }}<x-icon name="arrow-right" /></a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="cta reveal">
                <div class="row align-items-center g-4 position-relative" style="z-index:1">
                    <div class="col-lg-8"><h2 class="h1 fw-800 mb-2">{{ __('Not sure which service you need?') }}</h2><p class="mb-0 fs-5">{{ __('Tell us about your trip and we will suggest the best option.') }}</p></div>
                    <div class="col-lg-4 text-lg-end"><a href="{{ route('contact') }}" class="btn btn-ink btn-lg">{{ __('Talk to us') }}<x-icon name="chat-dots" /></a></div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
