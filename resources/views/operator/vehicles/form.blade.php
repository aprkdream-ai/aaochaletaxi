@php
    use App\Support\FleetOptions;
    $editing = $vehicle->exists;
    $catOptions = $categories->mapWithKeys(fn ($c) => [$c->id => $c->name.' ('.$c->seats.' '.__('seats').')'])->all();
@endphp
<x-layouts.panel :title="$editing ? __('Edit vehicle') : __('Add vehicle')" panel="operator">
    <x-page-header :title="$editing ? $vehicle->displayRegistration() : __('Add vehicle')">
        <a href="{{ $editing ? route('operator.vehicles.show', $vehicle) : route('operator.vehicles.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
    </x-page-header>
    <form method="POST" action="{{ $editing ? route('operator.vehicles.update', $vehicle) : route('operator.vehicles.store') }}" enctype="multipart/form-data" data-loading novalidate
          x-data="{ seats: @js(old('seats', $vehicle->seats)), cats: @js($categories->pluck('seats', 'id')) }">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Vehicle') }}</div><div class="card-body"><div class="row g-2">
                    <div class="col-md-6"><x-input name="registration_number" :label="__('Registration number')" :value="$editing ? $vehicle->displayRegistration() : null" required maxlength="15" placeholder="MH 12 AB 1234" style="text-transform:uppercase" autocomplete="off" /></div>
                    <div class="col-md-3"><x-select name="vehicle_type" :label="__('Vehicle type')" :options="FleetOptions::vehicleTypes()" :value="$vehicle->vehicle_type" required /></div>
                    <div class="col-md-3"><x-select name="vehicle_category_id" :label="__('Category')" :options="$catOptions" :value="$vehicle->vehicle_category_id" required :placeholder="__('Select…')" x-on:change="if (cats[$event.target.value] && !seats) seats = cats[$event.target.value]" /></div>
                    <div class="col-md-4"><x-input name="make" :label="__('Make')" :value="$vehicle->make" required placeholder="Maruti Suzuki" maxlength="60" /></div>
                    <div class="col-md-4"><x-input name="model" :label="__('Model')" :value="$vehicle->model" required placeholder="Dzire" maxlength="60" /></div>
                    <div class="col-md-4"><x-input name="year" type="number" :label="__('Year')" :value="$vehicle->year" min="1990" :max="now()->year + 1" /></div>
                    <div class="col-md-3"><div class="mb-3"><label class="form-label" for="f_seats">{{ __('Seats') }}<span class="text-danger"> *</span></label><input type="number" name="seats" id="f_seats" x-model="seats" min="1" max="60" class="form-control @error('seats') is-invalid @enderror" required>@error('seats')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div>
                    <div class="col-md-3"><x-select name="fuel_type" :label="__('Fuel')" :options="FleetOptions::fuelTypes()" :value="$vehicle->fuel_type" required /></div>
                    <div class="col-md-3"><x-input name="color" :label="__('Colour')" :value="$vehicle->color" maxlength="30" /></div>
                    <div class="col-md-3"><x-input name="odometer_km" type="number" :label="__('Odometer (km)')" :value="$vehicle->odometer_km" min="0" /></div>
                    <div class="col-md-6"><x-select name="ownership" :label="__('Ownership')" :options="FleetOptions::ownership()" :value="$vehicle->ownership" required /></div>
                    <div class="col-md-3"><x-select name="branch_id" :label="__('Branch')" :options="$branches->pluck('name', 'id')->all()" :value="$vehicle->branch_id" :placeholder="__('— None —')" /></div>
                    <div class="col-md-3"><x-input name="fastag_id" :label="__('FASTag ID')" :value="$vehicle->fastag_id" maxlength="30" autocomplete="off" /></div>
                    <div class="col-12"><x-textarea name="notes" :label="__('Notes')" :value="$vehicle->notes" rows="2" maxlength="1000" /></div>
                </div></div></div>
                <div class="card panel-card"><div class="card-header">{{ __('Document validity') }} <span class="small fw-normal text-muted-2">{{ __('We remind you 30, 15, 7 and 1 day before expiry') }}</span></div><div class="card-body"><div class="row g-2">
                    @foreach (FleetOptions::vehicleExpiryFields() as $field => $label)
                        <div class="col-6 col-md-3"><x-input :name="$field" type="date" :label="$label" :value="$vehicle->{$field}?->toDateString()" /></div>
                    @endforeach
                    @foreach (FleetOptions::optionalVehicleExpiryFields() as $field => $label)
                        <div class="col-6 col-md-3"><x-input :name="$field" type="date" :label="$label" :value="$vehicle->{$field}?->toDateString()" :help="__('Leave blank if lifetime / not applicable')" /></div>
                    @endforeach
                </div></div></div>
            </div>
            <div class="col-lg-4">
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Status') }}</div><div class="card-body">
                    <x-select name="status" :label="__('Status')" :options="collect(\App\Enums\VehicleStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" :value="$vehicle->status" required />
                    <x-switch name="is_available" :label="__('Available for bookings')" :checked="$vehicle->is_available" />
                    <x-switch name="is_ac" :label="__('Air-conditioned')" :checked="$vehicle->is_ac" />
                </div></div>
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Photo') }}</div><div class="card-body">
                    @if ($vehicle->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($vehicle->image_path) }}" alt="" class="rounded-3 w-100 mb-2" style="max-height:160px;object-fit:cover">@endif
                    <input type="file" name="image" accept="image/png,image/jpeg,image/webp" class="form-control @error('image') is-invalid @enderror" aria-label="{{ __('Photo') }}">
                    @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">{{ __('JPG, PNG or WEBP up to 2 MB') }}</div>
                </div></div>
                <button class="btn btn-primary btn-lg w-100"><x-icon name="check2" />{{ $editing ? __('Save changes') : __('Add vehicle') }}</button>
            </div>
        </div>
    </form>
    @unless ($editing)
        <details class="mt-3 small"><summary class="text-muted-2">{{ __('Need a category that is not listed?') }}</summary>
            <form method="POST" action="{{ route('operator.vehicle-categories.store') }}" class="d-flex flex-wrap gap-2 mt-2">@csrf
                <input type="text" name="name" class="form-control form-control-sm" style="max-width:220px" placeholder="{{ __('e.g. Urbania') }}" required maxlength="60" aria-label="{{ __('Category name') }}">
                <input type="number" name="seats" class="form-control form-control-sm" style="max-width:110px" placeholder="{{ __('Seats') }}" min="1" max="60" required aria-label="{{ __('Seats') }}">
                <button class="btn btn-sm btn-light border">{{ __('Add category') }}</button>
            </form>
            @error('category')<div class="text-danger mt-1">{{ $message }}</div>@enderror
        </details>
    @endunless
</x-layouts.panel>
