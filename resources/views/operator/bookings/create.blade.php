<x-layouts.panel :title="__('New booking')" panel="operator">
    <x-page-header :title="__('New booking')" :subtitle="__('Fill in the trip — the fare updates as you type')">
        <a href="{{ route('operator.bookings.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
    </x-page-header>
    <form method="POST" action="{{ route('operator.bookings.store') }}" data-loading novalidate
          x-data="bookingForm(@js(route('operator.bookings.preview')), @js(route('operator.bookings.distance')), @js(old('type', 'outstation_oneway')), @js(old('stops', [])))"
          x-init="init()" @input.debounce.600ms="refresh()" @change="refresh()" @if ($mapsEnabled) @suggest:select="autoDistance($event)" @endif x-ref="form">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Trip') }}</div><div class="card-body"><div class="row g-2">
                    <div class="col-md-6"><div class="mb-3"><label class="form-label" for="f_type">{{ __('Trip type') }}</label>
                        <select name="type" id="f_type" class="form-select @error('type') is-invalid @enderror" x-model="type">@foreach ($types as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div></div>
                    <div class="col-md-6"><x-select name="vehicle_category_id" :label="__('Vehicle type')" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->name.' ('.$c->seats.' '.__('seats').')'])->all()" :value="old('vehicle_category_id', $categories->firstWhere('slug', 'sedan')?->id)" required /></div>
                    <div class="col-12"><x-place-input name="pickup_address" :label="__('Pickup')" :placeholder="__('Area, landmark, city')" :value="$prefill['pickup_address'] ?? null" required maxlength="255" x-ref="from" /></div>
                    <template x-for="(stop, i) in stops" :key="i">
                        <div class="col-12 d-flex gap-2 mb-2"><div class="position-relative flex-grow-1"><input type="text" name="stops[]" class="form-control" data-suggest="places" data-suggest-url="{{ route('suggest.places') }}" autocomplete="off" :aria-label="'{{ __('Stop') }} ' + (i + 1)" :placeholder="'{{ __('Stop') }} ' + (i + 1)" x-model="stops[i]" maxlength="255"></div><button type="button" class="btn btn-light border" @click="stops.splice(i, 1)" aria-label="{{ __('Remove stop') }}"><x-icon name="x-lg" /></button></div>
                    </template>
                    <div class="col-12 mb-2"><button type="button" class="btn btn-sm btn-link px-0" @click="stops.push('')" x-show="stops.length < 8"><x-icon name="plus-circle" /> {{ __('Add a stop') }}</button></div>
                    <div class="col-12" x-show="type !== 'hourly' && type !== 'full_day'"><x-place-input name="drop_address" icon="flag-fill" :label="__('Drop')" :placeholder="__('Destination')" :value="$prefill['drop_address'] ?? null" maxlength="255" x-ref="to" /></div>
                    <div class="col-md-6"><x-input name="pickup_at" type="datetime-local" :label="__('Pickup date & time')" :value="now()->addHours(3)->startOfHour()->format('Y-m-d\TH:i')" required /></div>
                    <div class="col-md-6" x-show="type === 'outstation_round'" x-cloak><x-input name="return_at" type="datetime-local" :label="__('Return date & time')" /></div>
                    <div class="col-md-6" x-show="type === 'hourly' || type === 'full_day'" x-cloak><x-input name="rental_hours" type="number" min="1" max="24" :label="__('Hours')" value="4" /></div>
                    <div class="col-md-6" x-show="type !== 'hourly' && type !== 'full_day'">
                        <label class="form-label" for="f_distance_km">{{ __('Distance (km)') }} <span class="text-muted-2 small" x-show="type === 'outstation_round'">{{ __('total, both ways') }}</span></label>
                        <div class="input-group mb-1">
                            <input type="number" name="distance_km" id="f_distance_km" step="0.1" min="0" value="{{ old('distance_km') }}" class="form-control @error('distance_km') is-invalid @enderror" x-ref="km">
                            @if ($mapsEnabled)<button type="button" class="btn btn-light border" @click="fetchDistance()" :disabled="busy"><x-icon name="geo" /> {{ __('Calculate') }}</button>@endif
                        </div>
                        <input type="hidden" name="duration_min" x-ref="duration">
                        <div class="form-text" x-text="distanceNote">{{ $mapsEnabled ? '' : __('Enter road distance. Add Google Maps in admin settings to calculate automatically.') }}</div>
                    </div>
                    <div class="col-md-3"><x-input name="passengers" type="number" min="1" max="60" :label="__('Passengers')" value="1" /></div>
                </div></div></div>
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Customer') }}</div><div class="card-body"><div class="row g-2">
                    <div class="col-md-6"><x-input name="customer_name" icon="person" :label="__('Name')" :value="$prefill['customer_name'] ?? null" required maxlength="150" /></div>
                    <div class="col-md-6"><x-input name="customer_phone" type="tel" icon="phone" :label="__('Mobile')" :value="$prefill['customer_phone'] ?? null" required inputmode="numeric" maxlength="14" /></div>
                    <div class="col-md-6"><x-input name="customer_email" type="email" :label="__('Email (optional)')" /></div>
                    <div class="col-md-6"><x-select name="source" :label="__('Source')" :options="['phone' => __('Phone call'), 'whatsapp' => 'WhatsApp', 'walk_in' => __('Walk-in'), 'manual' => __('Other')]" value="phone" /></div>
                    <div class="col-12"><x-textarea name="notes" :label="__('Notes for the team')" rows="2" maxlength="1000" /></div>
                </div></div></div>
            </div>
            <div class="col-lg-4">
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Extras') }} <span class="small fw-normal text-muted-2">{{ __('estimates') }}</span></div><div class="card-body"><div class="row g-2">
                    <div class="col-6"><x-input name="tolls" type="number" step="1" min="0" :label="__('Tolls ₹')" /></div>
                    <div class="col-6"><x-input name="parking" type="number" step="1" min="0" :label="__('Parking ₹')" /></div>
                    <div class="col-6"><x-input name="state_tax" type="number" step="1" min="0" :label="__('State tax ₹')" /></div>
                    <div class="col-6"><x-input name="discount" type="number" step="1" min="0" :label="__('Discount ₹')" /></div>
                </div></div></div>
                <div class="card panel-card mb-3 position-sticky" style="top: 80px">
                    <div class="card-header">{{ __('Fare estimate') }} <span class="spinner-border spinner-border-sm text-muted" x-show="busy" x-cloak></span></div>
                    <div class="card-body">
                        <template x-if="fare">
                            <div class="small">
                                <template x-for="l in fare.lines"><div class="d-flex justify-content-between py-1"><div><span x-text="l.label"></span><div class="text-muted-2" x-text="l.detail" x-show="l.detail"></div></div><span class="fw-semibold" x-text="inr(l.amount)"></span></div></template>
                                <template x-if="+fare.discount > 0"><div class="d-flex justify-content-between py-1"><span>{{ __('Discount') }}</span><span x-text="'− ' + inr(fare.discount)"></span></div></template>
                                <div class="d-flex justify-content-between py-1 border-top mt-1"><span>{{ __('GST') }} <span x-text="'(' + (+fare.gst_percent) + '%)'"></span></span><span x-text="inr(fare.gst)"></span></div>
                                <template x-for="l in fare.passthrough"><div class="d-flex justify-content-between py-1"><span x-text="l.label + ' (' + l.detail + ')'"></span><span x-text="inr(l.amount)"></span></div></template>
                                <div class="d-flex justify-content-between pt-2 mt-1 border-top fs-5 fw-800"><span>{{ __('Total') }}</span><span x-text="inr(fare.total)"></span></div>
                                <div class="text-muted-2 mt-1" x-show="fare.billable_km > fare.distance_km" x-text="'{{ __('Billed for') }} ' + fare.billable_km + ' km ({{ __('minimum') }})'"></div>
                            </div>
                        </template>
                        <div class="small text-muted-2" x-show="!fare && !error">{{ __('Enter trip details to see the fare.') }}</div>
                        <div class="small text-danger" x-show="error" x-text="error" x-cloak></div>
                    </div>
                    <div class="card-body border-top"><button class="btn btn-primary btn-lg w-100"><x-icon name="check2" />{{ __('Save booking') }}</button>
                        <div class="form-text text-center">{{ __('Saved as a quote. Confirm after the customer agrees.') }}</div></div>
                </div>
            </div>
        </div>
    </form>
</x-layouts.panel>
