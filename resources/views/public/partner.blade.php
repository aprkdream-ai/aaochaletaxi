@php
    use App\Support\Money;
    $faqs = [
        __('Is there a free trial?') => __('Yes. Every new operator gets a free trial of the Starter plan. No credit card is required.'),
        __('What happens when my subscription expires?') => __('You get a grace period to renew. Your data is never deleted — it is restored the moment you renew.'),
        __('Can I upgrade or downgrade later?') => __('Yes, you can change plans anytime as your fleet grows.'),
        __('Do I need to install anything?') => __('No. AaoChaleTaxi works in any browser on mobile or computer. Drivers use a lightweight mobile web app.'),
        __('Is my business data safe?') => __('Each operator’s data is kept separate and is accessible only to your authorised staff. We use HTTPS, encrypted secrets and audit logs.'),
    ];
@endphp
<x-layouts.public :title="__('Taxi fleet management software — pricing')" :description="__('All-in-one taxi booking and fleet management software for Indian operators. Simple monthly plans with a free trial.')">
    <x-page-hero :eyebrow="__('For taxi operators')" :title="__('Grow your taxi business with AaoChaleTaxi')" :lead="__('Bookings, dispatch, fleet, drivers, invoices and reports — one simple dashboard. Start free, upgrade when you grow.')" :crumbs="[__('For operators') => null]">
        <div class="d-flex flex-wrap gap-2 mt-4">
            <a href="{{ route('register.operator') }}" class="btn btn-primary btn-lg">{{ __('Start free trial') }}<x-icon name="arrow-right" /></a>
            <a href="#pricing" class="btn btn-glass btn-lg">{{ __('View plans') }}</a>
        </div>
    </x-page-hero>

    <section class="section">
        <div class="container">
            <x-section-head :eyebrow="__('Features')" :title="__('Everything you need to run your fleet')" />
            <div class="row g-3 g-lg-4">
                @foreach ([
                    ['calendar2-check', __('Bookings & dispatch'), __('Manual and online bookings, quotations and conflict-free vehicle & driver assignment.')],
                    ['truck-front', __('Fleet management'), __('Vehicles, documents, insurance/permit/PUC expiry alerts, maintenance and expenses.')],
                    ['person-badge', __('Driver management'), __('Driver profiles, licences, trip history, earnings and settlements.')],
                    ['calculator', __('Fare calculator'), __('Your own fare rules — per km, minimum km, allowances, night charges, GST.')],
                    ['receipt', __('Billing & GST'), __('GST-ready invoices, advance & balance payments, refunds and receipts.')],
                    ['graph-up-arrow', __('Reports'), __('Daily and monthly reports, vehicle-wise and booking-wise profitability.')],
                    ['phone', __('Driver mobile app'), __('Trips, navigation, OTP trip start and earnings on the driver’s phone.')],
                    ['window', __('Branded booking page'), __('Your own booking page to accept bookings under your brand.')],
                ] as [$ico, $t, $d])
                    <div class="col-6 col-lg-3 reveal">
                        <div class="card card-hover h-100"><div class="card-body p-3 p-md-4">
                            <span class="icon-tile mb-3"><x-icon :name="$ico" /></span>
                            <h3 class="h6 fw-800">{{ $t }}</h3>
                            <p class="small text-muted-2 mb-0 d-none d-sm-block">{{ $d }}</p>
                        </div></div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section bg-surface-2" id="pricing" x-data="{ yearly: false }">
        <div class="container">
            <x-section-head :eyebrow="__('Pricing')" :title="__('Simple, honest pricing')" :text="__('Prices exclusive of GST. Cancel anytime — your data stays yours.')" />
            @if ($plans->isNotEmpty())
                <div class="text-center mb-4">
                    <div class="seg bg-white shadow-sm">
                        <button type="button" :class="{ active: !yearly }" @click="yearly = false">{{ __('Monthly') }}</button>
                        <button type="button" :class="{ active: yearly }" @click="yearly = true">{{ __('Yearly') }} <span class="badge-soft ok ms-1">{{ __('Save more') }}</span></button>
                    </div>
                </div>
            @endif
            <div class="row g-4 justify-content-center">
                @forelse ($plans as $plan)
                    @php
                        $featured = $loop->count >= 3 ? $loop->index === 1 : $loop->last;
                        $save = (float) $plan->price_monthly > 0 ? max(0, (int) round(100 - ((float) $plan->price_yearly / ((float) $plan->price_monthly * 12)) * 100)) : 0;
                    @endphp
                    <div class="col-md-6 col-lg-4 reveal">
                        <div @class(['plan', 'featured' => $featured])>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="h4 mb-0">{{ $plan->name }}</h3>
                                @if ($featured)<span class="badge-soft warn">{{ __('Most popular') }}</span>@endif
                            </div>
                            <p class="small mb-4 {{ $featured ? '' : 'text-muted-2' }}">{{ $plan->description }}</p>
                            <div class="mb-1">
                                <span class="price" x-show="!yearly">{{ Money::format($plan->price_monthly, 0) }}</span>
                                <span class="price" x-show="yearly" x-cloak>{{ Money::format($plan->price_yearly, 0) }}</span>
                                <span class="small opacity-75" x-text="yearly ? '/{{ __('year') }}' : '/{{ __('month') }}'">/{{ __('month') }}</span>
                            </div>
                            <div class="small mb-4 opacity-75" style="min-height:1.3em"><span x-show="yearly" x-cloak>@if ($save > 0){{ __('Save :p% with yearly billing', ['p' => $save]) }}@endif</span></div>
                            <ul class="check-list small mb-4">
                                <li><x-icon name="check-circle-fill" />{{ __('Up to :n vehicles', ['n' => $plan->max_vehicles]) }}</li>
                                <li><x-icon name="check-circle-fill" />{{ __('Up to :n drivers', ['n' => $plan->max_drivers]) }}</li>
                                <li><x-icon name="check-circle-fill" />{{ trans_choice('{1} :n branch|[2,*] Up to :n branches', $plan->max_branches, ['n' => $plan->max_branches]) }}</li>
                                <li><x-icon name="check-circle-fill" />{{ __('Taxi or Fleet module included') }}</li>
                                @if ((float) $plan->addon_price_monthly > 0)
                                    <li><x-icon name="plus-circle" /><span>{{ __('Both modules: +:m/month', ['m' => Money::format($plan->addon_price_monthly, 0)]) }}</span></li>
                                @endif
                                @foreach ($plan->features ?? [] as $feature)
                                    <li><x-icon name="check-circle-fill" />{{ __($feature) }}</li>
                                @endforeach
                            </ul>
                            <a href="{{ route('register.operator') }}" class="btn {{ $featured ? 'btn-primary' : 'btn-ink' }} btn-lg w-100 mt-auto">{{ __('Start :days-day free trial', ['days' => $plan->trial_days]) }}</a>
                        </div>
                    </div>
                @empty
                    <div class="col-lg-6"><x-empty-state icon="tags" :title="__('Plans will be published soon.')" /></div>
                @endforelse
            </div>

            @if ($plans->isNotEmpty())
                <div class="card mt-5 reveal">
                    <div class="table-responsive">
                        <table class="table table-modern compare mb-0 text-center">
                            <caption class="visually-hidden">{{ __('Plan comparison') }}</caption>
                            <thead><tr><th class="text-start">{{ __('Compare plans') }}</th>@foreach ($plans as $plan)<th>{{ $plan->name }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach ([__('Vehicles') => 'max_vehicles', __('Drivers') => 'max_drivers', __('Branches') => 'max_branches', __('Staff users') => 'max_users'] as $label => $field)
                                    <tr><th scope="row" class="text-start fw-semibold">{{ $label }}</th>@foreach ($plans as $plan)<td>{{ $plan->$field }}</td>@endforeach</tr>
                                @endforeach
                                <tr><th scope="row" class="text-start fw-semibold">{{ __('Monthly price') }}</th>@foreach ($plans as $plan)<td class="fw-bold">{{ Money::format($plan->price_monthly, 0) }}</td>@endforeach</tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <section class="section">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-5">
                    <x-section-head align="start" :eyebrow="__('FAQ')" :title="__('Questions from operators')" />
                    <x-faq :items="$faqs" />
                </div>
                <div class="col-lg-7">
                    <div class="card shadow-soft rounded-xl"><div class="card-body p-4 p-lg-5">
                        <h2 class="h4 mb-1">{{ __('Talk to our team') }}</h2>
                        <p class="text-muted-2 mb-4">{{ __('Want a demo or have questions? We will call you back.') }}</p>
                        @include('public.partials.contact-form', ['type' => 'partner'])
                    </div></div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
