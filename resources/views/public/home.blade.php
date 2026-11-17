@php
    use App\Http\Controllers\Public\ContactController;

    $initialTrip = old('trip_type', request('trip'));
    $initialTrip = array_key_exists((string) $initialTrip, ContactController::TRIP_TYPES) ? $initialTrip : 'outstation_oneway';
    $prefill = fn (string $key) => \Illuminate\Support\Str::limit((string) request()->string($key), 150, '');

    $services = [
        ['signpost-split', __('Outstation one-way'), __('Pay only for the distance you travel. Perfect for intercity drops.'), 'outstation_oneway'],
        ['arrow-repeat', __('Outstation round trip'), __('Keep the car for the whole journey with an experienced driver.'), 'outstation_round'],
        ['airplane', __('Airport transfers'), __('On-time pickups and drops with waiting time included.'), 'airport'],
        ['train-front', __('Railway station'), __('Reliable transfers for early-morning and late-night trains.'), 'railway'],
        ['geo-alt', __('Local city rides'), __('Point-to-point rides within the city in clean, AC cars.'), 'local'],
        ['clock-history', __('Hourly rental'), __('Packages like 4 hrs / 40 km and 8 hrs / 80 km.'), 'hourly'],
        ['sun', __('Full-day rental'), __('A car with driver at your disposal for the whole day.'), 'full_day'],
        ['briefcase', __('Corporate travel'), __('Employee travel with consolidated GST invoices.'), 'corporate'],
    ];

    $fleet = [
        ['car-front', __('Hatchback'), '4', __('Budget city rides')],
        ['car-front-fill', __('Sedan'), '4', __('Comfort for outstation')],
        ['truck-front', __('SUV'), '6–7', __('Families & hill trips')],
        ['truck-front-fill', __('MUV'), '7', __('Extra luggage space')],
        ['bus-front', __('Tempo Traveller'), '12–17', __('Group tours')],
        ['bus-front-fill', __('Bus'), '20+', __('Weddings & events')],
    ];

    $routes = [
        ['Delhi', 'Agra'], ['Delhi', 'Jaipur'], ['Mumbai', 'Pune'], ['Mumbai', 'Shirdi'],
        ['Bengaluru', 'Mysuru'], ['Chennai', 'Puducherry'], ['Ahmedabad', 'Udaipur'], ['Hyderabad', 'Vijayawada'],
    ];

    $faqs = [
        __('How do I book a taxi on AaoChaleTaxi?') => __('Choose your trip type, enter pickup, drop and travel date, and request a quote. Our team or a verified operator calls you back with the best fare and confirms your booking.'),
        __('Are there any hidden charges?') => __('No. Every quote shows the fare breakdown — base fare, distance, driver allowance and GST. Tolls, parking and state taxes are shown upfront or charged at actuals as mentioned in your quote.'),
        __('What is the difference between one-way and round trip?') => __('One-way is a drop to another city — you pay only for one side. Round trip means the car stays with you and returns you to your pickup city, billed per kilometre with driver allowance.'),
        __('Can I cancel my booking?') => __('Yes. Cancellations more than 24 hours before pickup get a full refund of the advance. See our cancellation & refund policy for details.'),
        __('I own taxis. How can I join?') => __('Register your business for a free trial. After a quick KYC check you can manage bookings, vehicles, drivers and invoices from your dashboard.'),
    ];
@endphp
<x-layouts.public>
    <x-slot:head>
        <x-json-ld :data="[
            '@context' => 'https://schema.org',
            '@type' => 'TaxiService',
            'name' => 'AaoChaleTaxi',
            'url' => route('home'),
            'areaServed' => ['@type' => 'Country', 'name' => 'India'],
            'provider' => ['@type' => 'Organization', 'name' => 'AaoChaleTaxi', 'url' => route('home')],
            'serviceType' => ['Outstation taxi', 'One-way taxi', 'Round trip taxi', 'Airport transfer', 'Railway station transfer', 'Local taxi', 'Hourly rental', 'Corporate travel'],
        ]" />
        <x-json-ld :data="['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'AaoChaleTaxi', 'url' => route('home'), 'inLanguage' => ['en-IN', 'hi-IN']]" />
    </x-slot:head>

    {{-- ===================== HERO ===================== --}}
    <section class="hero">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 col-xl-6">
                    <span class="pill mb-4"><span class="pill-dot">{{ __('NEW') }}</span>{{ __('Verified operators · Transparent fares') }}</span>
                    <h1 class="mb-4">{{ __('Your ride across India,') }}<br><span class="text-gradient">{{ __('on time, every time.') }}</span></h1>
                    <p class="lead mb-4 pe-lg-5">{{ __('Outstation, one-way, airport, railway and local taxis — booked in a minute with verified operators and an honest fare breakdown.') }}</p>
                    <div class="hero-points mb-4 mb-lg-5">
                        <span class="d-inline-flex align-items-center gap-2"><x-icon name="shield-check" />{{ __('KYC-verified operators') }}</span>
                        <span class="d-inline-flex align-items-center gap-2"><x-icon name="currency-rupee" />{{ __('No hidden charges') }}</span>
                        <span class="d-inline-flex align-items-center gap-2"><x-icon name="headset" />{{ __('Support 7 days a week') }}</span>
                    </div>
                    <div class="d-none d-lg-flex align-items-center gap-3">
                        <a href="{{ route('services') }}" class="btn btn-glass">{{ __('Explore services') }}<x-icon name="arrow-right" /></a>
                        <a href="{{ route('partner') }}" class="text-white-50 small fw-semibold">{{ __('Own taxis? Grow with us →') }}</a>
                    </div>
                </div>

                <div class="col-lg-6 col-xl-5 offset-xl-1" id="quote">
                    <div class="quote-card p-3 p-sm-4" x-data="quoteForm('{{ $initialTrip }}')">
                        <div class="d-flex justify-content-between align-items-center mb-3 px-1">
                            <h2 class="h5 mb-0">{{ __('Get a fare quote') }}</h2>
                            <span class="badge-soft ok"><x-icon name="lightning-charge-fill" /> {{ __('Quick callback') }}</span>
                        </div>

                        <div class="trip-tabs mb-3" role="tablist" aria-label="{{ __('Trip type') }}">
                            @foreach (['outstation' => ['signpost-split', __('Outstation')], 'local' => ['geo-alt', __('Local')], 'transfer' => ['airplane', __('Airport')], 'rental' => ['clock-history', __('Rental')]] as $g => [$ico, $lbl])
                                <button type="button" role="tab" :class="{ active: group === '{{ $g }}' }" :aria-selected="group === '{{ $g }}'" @click="pick('{{ $g }}')"><x-icon :name="$ico" />{{ $lbl }}</button>
                            @endforeach
                        </div>

                        <form method="POST" action="{{ route('enquiry.store') }}" data-loading novalidate>
                            @csrf
                            <input type="hidden" name="trip_type" :value="trip" value="{{ $initialTrip }}">
                            <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">

                            <div class="mb-3 d-flex flex-wrap gap-2">
                                <div class="seg" x-show="group === 'outstation'">
                                    <button type="button" :class="{ active: trip === 'outstation_oneway' }" @click="trip = 'outstation_oneway'">{{ __('One-way') }}</button>
                                    <button type="button" :class="{ active: trip === 'outstation_round' }" @click="trip = 'outstation_round'">{{ __('Round trip') }}</button>
                                </div>
                                <div class="seg" x-show="group === 'local'" x-cloak>
                                    <button type="button" :class="{ active: trip === 'local' }" @click="trip = 'local'">{{ __('City ride') }}</button>
                                    <button type="button" :class="{ active: trip === 'corporate' }" @click="trip = 'corporate'">{{ __('Corporate') }}</button>
                                </div>
                                <div class="seg" x-show="group === 'transfer'" x-cloak>
                                    <button type="button" :class="{ active: trip === 'airport' }" @click="trip = 'airport'">{{ __('Airport') }}</button>
                                    <button type="button" :class="{ active: trip === 'railway' }" @click="trip = 'railway'">{{ __('Railway station') }}</button>
                                </div>
                                <div class="seg" x-show="group === 'rental'" x-cloak>
                                    <button type="button" :class="{ active: trip === 'hourly' }" @click="trip = 'hourly'">{{ __('Hourly') }}</button>
                                    <button type="button" :class="{ active: trip === 'full_day' }" @click="trip = 'full_day'">{{ __('Full day') }}</button>
                                </div>
                            </div>
                            @error('trip_type')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                            <div class="row g-2">
                                <div class="col-sm-6"><x-input name="pickup" icon="geo-alt-fill" :label="__('Pickup')" :value="$prefill('pickup')" :placeholder="__('City or location')" required maxlength="150" autocomplete="address-level2" /></div>
                                <div class="col-sm-6"><x-input name="drop" icon="pin-map-fill" :label="__('Drop')" :value="$prefill('drop')" :placeholder="__('Optional for rentals')" maxlength="150" /></div>
                                <div class="col-sm-6"><x-input name="travel_date" type="date" icon="calendar-event" :label="__('Travel date')" required :min="now()->toDateString()" :value="now()->addDay()->toDateString()" /></div>
                                <div class="col-sm-6"><x-input name="phone" type="tel" icon="phone" :label="__('Mobile number')" required inputmode="numeric" maxlength="14" placeholder="98XXXXXXXX" autocomplete="tel-national" /></div>
                                <div class="col-12"><x-input name="name" icon="person" :label="__('Your name')" required maxlength="150" autocomplete="name" /></div>
                            </div>
                            <button class="btn btn-primary btn-lg w-100" type="submit">{{ __('Get best fare') }}<x-icon name="arrow-right" /></button>
                            <p class="small text-muted-2 text-center mt-3 mb-0"><x-icon name="lock-fill" /> {{ __('We only use your number to share your quote.') }}</p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== TRUST STRIP ===================== --}}
    <div class="container trust-strip">
        <div class="card border-0 shadow-lift rounded-xl">
            <div class="card-body p-4">
                <div class="row g-4">
                    @foreach ([
                        ['patch-check-fill', __('Verified operators'), __('KYC & documents checked')],
                        ['receipt-cutoff', __('Clear fare breakdown'), __('Tolls, taxes, allowances shown')],
                        ['shield-lock-fill', __('OTP-secured trips'), __('Trip starts only with your OTP')],
                        ['file-earmark-text-fill', __('GST invoices'), __('For business travel')],
                    ] as [$ico, $t, $d])
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="trust-item"><span class="icon-tile tile-sm"><x-icon :name="$ico" /></span><div><div class="t-title">{{ $t }}</div><div class="t-text">{{ $d }}</div></div></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== SERVICES ===================== --}}
    <section class="section">
        <div class="container">
            <x-section-head :eyebrow="__('Our services')" :title="__('A taxi for every journey')" :text="__('From a quick airport drop to a week-long family road trip — choose the ride that fits.')" />
            <div class="row g-3 g-lg-4">
                @foreach ($services as [$ico, $title, $text, $trip])
                    <div class="col-6 col-lg-3 reveal">
                        <a href="{{ route('home', ['trip' => $trip]) }}#quote" class="card card-hover h-100 text-reset text-decoration-none">
                            <div class="card-body p-3 p-md-4">
                                <span class="icon-tile mb-3"><x-icon :name="$ico" /></span>
                                <h3 class="h6 fw-800 mb-2">{{ $title }}</h3>
                                <p class="small text-muted-2 mb-3 d-none d-sm-block">{{ $text }}</p>
                                <span class="small fw-bold text-brand d-inline-flex align-items-center gap-1">{{ __('Get quote') }}<x-icon name="arrow-right" /></span>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== FLEET ===================== --}}
    <section class="section bg-surface-2">
        <div class="container">
            <x-section-head :eyebrow="__('Choose your car')" :title="__('The right vehicle for every group')" :text="__('All vehicles are commercially registered with valid permits, insurance and fitness certificates.')" />
            <div class="fleet-scroll reveal">
                @foreach ($fleet as [$ico, $name, $seats, $best])
                    <div class="fleet-card card-hover">
                        <div class="fleet-ico"><x-icon :name="$ico" /></div>
                        <div class="fw-800">{{ $name }}</div>
                        <div class="seats mb-1"><x-icon name="people-fill" />{{ $seats }} {{ __('seats') }}</div>
                        <div class="small text-muted-2">{{ $best }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== HOW IT WORKS ===================== --}}
    <section class="section">
        <div class="container">
            <x-section-head :eyebrow="__('How it works')" :title="__('Book in three simple steps')" />
            <div class="row g-3 g-lg-4 steps">
                @foreach ([
                    ['1', 'pencil-square', __('Share your trip'), __('Tell us pickup, drop, date and the car you prefer.')],
                    ['2', 'chat-square-text', __('Get the best fare'), __('Receive a clear, all-inclusive quote from a verified operator.')],
                    ['3', 'emoji-smile', __('Ride with confidence'), __('Driver details shared before pickup; trip starts with your OTP.')],
                ] as [$n, $ico, $t, $d])
                    <div class="col-md-4 reveal">
                        <div class="step">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="step-no">{{ $n }}</div>
                                <x-icon :name="$ico" class="fs-3 text-muted-2 opacity-50" />
                            </div>
                            <h3 class="h5">{{ $t }}</h3>
                            <p class="text-muted-2 mb-0">{{ $d }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== WHY US (BENTO) ===================== --}}
    <section class="section pt-0">
        <div class="container">
            <x-section-head :eyebrow="__('Why AaoChaleTaxi')" :title="__('Travel the way it should be')" />
            <div class="bento">
                <div class="bento-card dark span-2 reveal">
                    <div class="row g-4 align-items-center">
                        <div class="col-md-6">
                            <span class="icon-tile tile-ink mb-3"><x-icon name="receipt" /></span>
                            <h3 class="h4">{{ __('Honest, itemised fares') }}</h3>
                            <p class="mb-0">{{ __('Every quote breaks the fare down so you know exactly what you pay for. The fare you confirm is locked — no surprises at the end of the trip.') }}</p>
                        </div>
                        <div class="col-md-6">
                            <div class="fare-lines p-3 rounded-4" style="background: rgba(255,255,255,.05)">
                                <div class="small-caps mb-2 text-white-50">{{ __('Sample breakdown') }}</div>
                                <div class="line"><span>{{ __('Base fare + distance') }}</span><span>✓</span></div>
                                <div class="line"><span>{{ __('Driver allowance') }}</span><span>✓</span></div>
                                <div class="line"><span>{{ __('Tolls & state tax') }}</span><span>{{ __('as applicable') }}</span></div>
                                <div class="line"><span>{{ __('GST') }}</span><span>✓</span></div>
                                <div class="total"><span>{{ __('Total shown upfront') }}</span><x-icon name="check2-circle" class="text-brand" /></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bento-card reveal">
                    <span class="icon-tile mb-3"><x-icon name="person-badge" /></span>
                    <h3 class="h5">{{ __('Experienced drivers') }}</h3>
                    <p class="text-muted-2 mb-0">{{ __('Licensed drivers who know the highways, with details shared before pickup.') }}</p>
                </div>
                <div class="bento-card reveal">
                    <span class="icon-tile mb-3"><x-icon name="shield-lock" /></span>
                    <h3 class="h5">{{ __('Safe & secure') }}</h3>
                    <p class="text-muted-2 mb-0">{{ __('OTP-verified trip start and secure online payments. We never store card details.') }}</p>
                </div>
                <div class="bento-card reveal">
                    <span class="icon-tile mb-3"><x-icon name="translate" /></span>
                    <h3 class="h5">{{ __('English & हिन्दी') }}</h3>
                    <p class="text-muted-2 mb-0">{{ __('Use the website in the language you are most comfortable with.') }}</p>
                </div>
                <div class="bento-card reveal">
                    <span class="icon-tile mb-3"><x-icon name="arrow-counterclockwise" /></span>
                    <h3 class="h5">{{ __('Easy cancellation') }}</h3>
                    <p class="text-muted-2 mb-0">{{ __('Plans changed? Cancel more than 24 hours before pickup for a full refund.') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== POPULAR ROUTES ===================== --}}
    <section class="section bg-surface-2">
        <div class="container">
            <x-section-head :eyebrow="__('Popular routes')" :title="__('Where India travels by road')" :text="__('Tap a route to get a quick quote.')" />
            <div class="row g-3 reveal">
                @foreach ($routes as [$from, $to])
                    <div class="col-sm-6 col-lg-3">
                        <a class="route-chip" href="{{ route('home', ['pickup' => $from, 'drop' => $to]) }}#quote">
                            <span class="d-flex align-items-center gap-2"><x-icon name="geo-alt-fill" class="text-brand" />{{ $from }} <x-icon name="arrow-right-short" class="text-muted-2" /> {{ $to }}</span>
                            <x-icon name="chevron-right" class="arrow" />
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===================== FOR OPERATORS ===================== --}}
    <section class="section band-dark">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-5 reveal">
                    <div class="eyebrow on-dark">{{ __('For taxi operators') }}</div>
                    <h2 class="display-6 fw-800 mb-3">{{ __('Run your entire taxi business from one dashboard') }}</h2>
                    <p class="mb-4">{{ __('Built for Indian taxi operators and travel agencies — from 2 cars to 200.') }}</p>
                    <ul class="check-list mb-4">
                        @foreach ([__('Bookings & dispatch with conflict-free assignment'), __('Vehicle documents with expiry reminders'), __('Driver app with trip OTP & earnings'), __('GST-ready invoices & payment tracking'), __('Your own branded booking page')] as $p)
                            <li><x-icon name="check-circle-fill" />{{ $p }}</li>
                        @endforeach
                    </ul>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('register.operator') }}" class="btn btn-primary btn-lg">{{ __('Start free trial') }}<x-icon name="arrow-right" /></a>
                        <a href="{{ route('partner') }}" class="btn btn-glass btn-lg">{{ __('See pricing') }}</a>
                    </div>
                    @if ($startingPrice)<p class="small mt-3 mb-0 opacity-75">{{ __('Plans from :price/month · No credit card needed', ['price' => $startingPrice]) }}</p>@endif
                </div>
                <div class="col-lg-7 reveal" aria-hidden="true">
                    <div class="mock">
                        <div class="mock-bar"><span></span><span></span><span></span></div>
                        <div class="d-flex">
                            <div class="mock-side"><i></i><i></i><i></i><i></i><i></i><i style="width:60%"></i></div>
                            <div class="flex-grow-1 p-3">
                                <div class="d-flex justify-content-between align-items-center mb-3"><b style="color:#0b1220">{{ __('Today') }}</b><span class="badge-soft info">{{ __('Illustrative preview') }}</span></div>
                                <div class="row g-2 mb-3">
                                    <div class="col-4"><div class="mock-stat">{{ __('Bookings') }}<b>18</b></div></div>
                                    <div class="col-4"><div class="mock-stat">{{ __('On trip') }}<b>7</b></div></div>
                                    <div class="col-4"><div class="mock-stat">{{ __('Collected') }}<b>₹42,600</b></div></div>
                                </div>
                                @foreach ([['Ahmedabad → Udaipur', 'Sedan', 'ok', __('Assigned')], ['Airport drop · T2', 'SUV', 'warn', __('Pending')], ['Pune → Mumbai', 'Sedan', 'info', __('On trip')], ['Local · 8 hrs', 'Hatchback', 'muted', __('Completed')]] as [$r, $c, $tone, $s])
                                    <div class="mock-row"><span><b class="d-block" style="color:#0b1220;font-size:.8rem">{{ $r }}</b><span class="text-muted-2">{{ $c }}</span></span><span class="badge-soft {{ $tone }}">{{ $s }}</span></div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== FAQ ===================== --}}
    <section class="section">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-4">
                    <x-section-head align="start" :eyebrow="__('FAQ')" :title="__('Questions? We have answers.')" :text="__('Can’t find what you are looking for? Our team is happy to help.')" />
                    <a href="{{ route('contact') }}" class="btn btn-ink">{{ __('Contact support') }}<x-icon name="arrow-right" /></a>
                </div>
                <div class="col-lg-8"><x-faq :items="$faqs" /></div>
            </div>
        </div>
    </section>

    {{-- ===================== CTA ===================== --}}
    <section class="pb-5 mb-lg-4">
        <div class="container">
            <div class="cta reveal">
                <div class="row align-items-center g-4 position-relative" style="z-index:1">
                    <div class="col-lg-8">
                        <h2 class="display-6 fw-800 mb-2">{{ __('Ready for your next trip?') }}</h2>
                        <p class="mb-0 fs-5">{{ __('Get a transparent fare in minutes — no app download needed.') }}</p>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <a href="#quote" class="btn btn-ink btn-lg">{{ __('Get fare quote') }}<x-icon name="arrow-up-right" /></a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
