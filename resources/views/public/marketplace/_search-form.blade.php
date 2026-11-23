@php $q ??= []; @endphp
<form method="GET" action="{{ route('marketplace.search') }}" class="card panel-card shadow-sm" x-data="{ type: @js($q['type'] ?? 'outstation_oneway') }">
    <div class="card-body row g-2 align-items-end">
        <div class="col-md-3"><x-city-input name="from_city" :label="__('From city')" :value="$q['from_city'] ?? null" required /></div>
        <div class="col-md-3" x-show="!['hourly','full_day','local','airport'].includes(type)"><x-city-input name="to_city" icon="flag" :label="__('To city')" :value="$q['to_city'] ?? null" /></div>
        <div class="col-6 col-md-3"><div class="mb-3"><label class="form-label" for="s_type">{{ __('Trip') }}</label><select name="type" id="s_type" class="form-select" x-model="type">@foreach ($types as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div></div>
        <div class="col-6 col-md-3"><x-select name="vehicle_category_id" :label="__('Vehicle')" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->name.' · '.$c->seats.' '.__('seats')])->all()" :value="$q['vehicle_category_id'] ?? $categories->firstWhere('slug', 'sedan')?->id" required /></div>
        <div class="col-6 col-md-3"><x-input name="pickup_at" type="datetime-local" :label="__('Pickup')" :value="isset($q['pickup_at']) ? \Illuminate\Support\Carbon::parse($q['pickup_at'])->format('Y-m-d\TH:i') : now()->addDay()->setTime(8, 0)->format('Y-m-d\TH:i')" required /></div>
        <div class="col-6 col-md-3" x-show="type === 'outstation_round'" x-cloak><x-input name="return_at" type="datetime-local" :label="__('Return')" :value="isset($q['return_at']) ? \Illuminate\Support\Carbon::parse($q['return_at'])->format('Y-m-d\TH:i') : null" /></div>
        <div class="col-6 col-md-2" x-show="type === 'hourly' || type === 'full_day'" x-cloak><x-input name="rental_hours" type="number" min="1" max="24" :label="__('Hours')" :value="$q['rental_hours'] ?? 8" /></div>
        <div class="col-md-3"><button class="btn btn-primary btn-lg w-100 mb-3"><x-icon name="search" />{{ __('Compare cabs') }}</button></div>
    </div>
</form>
