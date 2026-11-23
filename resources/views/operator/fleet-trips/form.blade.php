@php
    $editing = $trip->exists;
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('Y-m-d\TH:i') : null;
@endphp
<x-layouts.panel :title="$editing ? __('Edit trip :no', ['no' => $trip->trip_number]) : __('New fleet trip')" panel="operator">
    <x-page-header :title="$editing ? __('Edit trip :no', ['no' => $trip->trip_number]) : __('New fleet trip')" :subtitle="__('Assign a vehicle and driver for official use')">
        <a href="{{ $editing ? route('operator.fleet-trips.show', $trip) : route('operator.fleet-trips.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('operator.fleet-trips.update', $trip) : route('operator.fleet-trips.store') }}" data-loading novalidate
          x-data="{ start: @js(old('start_at', $fmt($trip->start_at))), end: @js(old('end_at', $fmt($trip->end_at))), busyV: [], busyD: [],
                    async check() { if (!this.start || !this.end || this.end <= this.start) return;
                        const q = new URLSearchParams({ start_at: this.start, end_at: this.end, except: @js($trip->id ?? '') });
                        try { const r = await fetch(@js(route('operator.fleet-trips.availability')) + '?' + q, { headers: { Accept: 'application/json' } }); if (r.ok) { const j = await r.json(); this.busyV = j.vehicles; this.busyD = j.drivers; } } catch (e) {} } }"
          x-init="check()">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card panel-card mb-3"><div class="card-header">{{ __('When & who') }}</div><div class="card-body"><div class="row g-2">
                    <div class="col-md-6"><label class="form-label" for="f_start">{{ __('Start') }}<span class="text-danger"> *</span></label><input type="datetime-local" name="start_at" id="f_start" x-model="start" @change="check()" class="form-control @error('start_at') is-invalid @enderror" required>@error('start_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="f_end">{{ __('Return / free by') }}<span class="text-danger"> *</span></label><input type="datetime-local" name="end_at" id="f_end" x-model="end" @change="check()" class="form-control @error('end_at') is-invalid @enderror" required>@error('end_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="f_vehicle">{{ __('Vehicle') }}<span class="text-danger"> *</span></label>
                        <select name="vehicle_id" id="f_vehicle" class="form-select @error('vehicle_id') is-invalid @enderror" required>
                            <option value="">{{ __('Select…') }}</option>
                            @foreach ($vehicles as $v)<option value="{{ $v->id }}" @selected((int) old('vehicle_id', $trip->vehicle_id) === $v->id) x-text="@js($v->displayRegistration().' · '.$v->make.' '.$v->model.' ('.$v->seats.' '.__('seats').')') + (busyV.includes({{ $v->id }}) ? @js(' — '.__('busy')) : '')">{{ $v->displayRegistration() }} · {{ $v->make }} {{ $v->model }}</option>@endforeach
                        </select>@error('vehicle_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="f_driver">{{ __('Driver') }}<span class="text-danger"> *</span></label>
                        <select name="driver_id" id="f_driver" class="form-select @error('driver_id') is-invalid @enderror" required>
                            <option value="">{{ __('Select…') }}</option>
                            @foreach ($drivers as $d)<option value="{{ $d->id }}" @selected((int) old('driver_id', $trip->driver_id) === $d->id) x-text="@js($d->name.' · '.$d->phone) + (busyD.includes({{ $d->id }}) ? @js(' — '.__('busy')) : '')">{{ $d->name }} · {{ $d->phone }}</option>@endforeach
                        </select>@error('driver_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><div class="form-text">{{ __('Vehicles and drivers marked “busy” already have a trip in this time. Only active, available vehicles with valid documents and on-duty drivers are listed.') }}</div></div>
                </div></div></div>
                <div class="card panel-card"><div class="card-header">{{ __('Trip details') }}</div><div class="card-body"><div class="row g-2">
                    <div class="col-12"><x-input name="purpose" :label="__('Purpose')" :value="$trip->purpose" required maxlength="200" :placeholder="__('e.g. Site visit, airport pickup for MD, school route 3')" /></div>
                    <div class="col-md-6"><x-input name="pickup_point" :label="__('From')" :value="$trip->pickup_point" maxlength="255" /></div>
                    <div class="col-md-6"><x-input name="drop_point" :label="__('To')" :value="$trip->drop_point" maxlength="255" /></div>
                    <div class="col-12"><x-textarea name="notes" :label="__('Notes for the driver')" :value="$trip->notes" rows="2" maxlength="500" /></div>
                </div></div></div>
            </div>
            <div class="col-lg-4">
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Requested by') }}</div><div class="card-body">
                    <x-select name="department_id" :label="__('Department')" :options="$departments->mapWithKeys(fn ($d) => [$d->id => $d->label()])->all()" :value="$trip->department_id" :placeholder="__('— None —')" />
                    @if ($departments->isEmpty() && auth()->user()->hasPermission('departments.manage'))<div class="form-text mt-n2 mb-3"><a href="{{ route('operator.departments.index') }}">{{ __('Add departments') }}</a></div>@endif
                    <x-input name="requested_by" :label="__('Name')" :value="$trip->requested_by" maxlength="150" />
                    <x-input name="requested_phone" type="tel" :label="__('Mobile')" :value="$trip->requested_phone" inputmode="numeric" maxlength="14" />
                    <x-input name="passengers" type="number" :label="__('Passengers')" :value="$trip->passengers" min="1" max="60" />
                </div></div>
                @error('status')<div class="alert alert-danger small">{{ $message }}</div>@enderror
                <button class="btn btn-primary btn-lg w-100"><x-icon name="check2" />{{ $editing ? __('Save changes') : __('Create & notify driver') }}</button>
            </div>
        </div>
    </form>
</x-layouts.panel>
