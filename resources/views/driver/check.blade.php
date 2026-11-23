<x-layouts.driver :title="__('Daily vehicle check')">
    <h1 class="h5 mb-1">{{ __('Daily vehicle check') }}</h1>
    <p class="small text-muted-2 mb-3">{{ __('Check the vehicle before your first trip. Mark anything wrong as a problem — your operator is alerted right away.') }}</p>

    @if ($todayChecks->isNotEmpty())
        <div class="card panel-card mb-3">
            <div class="card-header small">{{ __('Submitted today') }}</div>
            <div class="list-group list-group-flush list-modern small">
                @foreach ($todayChecks as $c)
                    <div class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <span class="fw-semibold">{{ $c->vehicle?->displayRegistration() }}</span>
                        @if ($c->issues_count)<span class="badge-soft danger">{{ trans_choice('{1} 1 problem|[2,*] :n problems', $c->issues_count, ['n' => $c->issues_count]) }}</span>@else<span class="badge-soft ok">{{ __('All OK') }}</span>@endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($vehicles->isEmpty())
        <div class="card panel-card"><x-empty-state icon="car-front" :title="__('No vehicles yet')" :text="__('Your operator has not added any vehicles.')" /></div>
    @else
        <form method="POST" action="{{ route('driver.check.store') }}" enctype="multipart/form-data" data-loading novalidate
              x-data="{ items: @js(collect($items)->map(fn ($l, $k) => old('items.'.$k))->all()), get issues() { return Object.values(this.items).filter((v) => v === 'issue').length }, allOk() { Object.keys(this.items).forEach((k) => this.items[k] = 'ok') } }">
            @csrf
            <div class="card panel-card mb-3"><div class="card-body">
                <label class="form-label" for="c_vehicle">{{ __('Vehicle') }}</label>
                <select name="vehicle_id" id="c_vehicle" class="form-select @error('vehicle_id') is-invalid @enderror" required>
                    <option value="">{{ __('Select…') }}</option>
                    @foreach ($vehicles as $v)<option value="{{ $v->id }}" @selected($selected === $v->id)>{{ $v->displayRegistration() }} · {{ $v->make }} {{ $v->model }}</option>@endforeach
                </select>
                @error('vehicle_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <label class="form-label mt-3" for="c_km">{{ __('Odometer (km)') }}</label>
                <input type="number" name="odometer_km" id="c_km" value="{{ old('odometer_km') }}" min="0" inputmode="numeric" class="form-control @error('odometer_km') is-invalid @enderror">
                @error('odometer_km')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div></div>

            <div class="card panel-card mb-3">
                <div class="card-header d-flex align-items-center"><span class="me-auto">{{ __('Checklist') }}</span><button type="button" class="btn btn-sm btn-light border" @click="allOk()"><x-icon name="check2-all" />{{ __('All OK') }}</button></div>
                <div class="list-group list-group-flush list-modern">
                    @foreach ($items as $key => $label)
                        <div class="list-group-item d-flex align-items-center gap-2">
                            <span class="me-auto small fw-semibold">{{ $label }}</span>
                            <div class="seg seg-sm" role="radiogroup" aria-label="{{ $label }}">
                                <label :class="{ active: items.{{ $key }} === 'ok' }"><input type="radio" class="visually-hidden" name="items[{{ $key }}]" value="ok" x-model="items.{{ $key }}">{{ __('OK') }}</label>
                                <label :class="{ active: items.{{ $key }} === 'issue', 'text-danger': items.{{ $key }} === 'issue' }"><input type="radio" class="visually-hidden" name="items[{{ $key }}]" value="issue" x-model="items.{{ $key }}">{{ __('Problem') }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if ($errors->has('items.*'))<div class="card-body border-top py-2 small text-danger">{{ __('Mark every item as OK or Problem.') }}</div>@endif
            </div>

            <div class="card panel-card mb-3"><div class="card-body">
                <label class="form-label" for="c_notes">{{ __('Notes') }} <span class="small text-muted-2" x-show="issues > 0" x-cloak>{{ __('(describe the problem or add a photo)') }}</span></label>
                <textarea name="notes" id="c_notes" rows="2" maxlength="500" class="form-control @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <label class="form-label mt-3" for="c_photo">{{ __('Photo (optional)') }}</label>
                <input type="file" name="photo" id="c_photo" accept="image/*" capture="environment" class="form-control @error('photo') is-invalid @enderror">
                @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div></div>

            <button class="btn btn-lg w-100" :class="issues > 0 ? 'btn-danger' : 'btn-primary'">
                <x-icon name="clipboard-check" /><span x-text="issues > 0 ? @js(__('Submit & alert operator')) : @js(__('Submit check'))">{{ __('Submit check') }}</span>
            </button>
        </form>
    @endif
</x-layouts.driver>
