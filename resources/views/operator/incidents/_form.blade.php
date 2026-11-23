@php use App\Support\FleetOptions; $fixedVehicle ??= null; @endphp
<form method="POST" action="{{ route('operator.incidents.store') }}" enctype="multipart/form-data" class="row g-2 mt-2" data-loading>@csrf
    @if ($fixedVehicle)
        <input type="hidden" name="vehicle_id" value="{{ $fixedVehicle->id }}">
    @else
        <div class="col-md-4"><x-select name="vehicle_id" :label="__('Vehicle')" :options="$vehicles->mapWithKeys(fn ($v) => [$v->id => $v->displayRegistration()])->all()" required :placeholder="__('Select…')" /></div>
    @endif
    <div class="col-6 col-md-{{ $fixedVehicle ? 3 : 2 }}"><x-select name="type" :label="__('Type')" :options="FleetOptions::incidentTypes()" required /></div>
    <div class="col-6 col-md-{{ $fixedVehicle ? 3 : 2 }}"><x-input name="occurred_on" type="date" :label="__('Date')" :value="now()->toDateString()" :max="now()->toDateString()" required /></div>
    <div class="col-md-{{ $fixedVehicle ? 3 : 2 }}"><x-select name="driver_id" :label="__('Driver')" :options="$drivers->pluck('name', 'id')->all()" :placeholder="__('— None —')" /></div>
    <div class="col-md-{{ $fixedVehicle ? 3 : 2 }}"><x-input name="amount" type="number" step="0.01" min="0" :label="__('Fine / estimate ₹')" /></div>
    <div class="col-md-4"><x-input name="place" :label="__('Place')" maxlength="200" /></div>
    <div class="col-md-3"><x-input name="reference" :label="__('Challan / FIR no.')" maxlength="60" /></div>
    <div class="col-md-5"><label class="form-label" for="i_photo">{{ __('Photo / document') }}</label><input type="file" name="photo" id="i_photo" accept="image/*,application/pdf" class="form-control @error('photo') is-invalid @enderror">@error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    <div class="col-12"><x-textarea name="description" :label="__('What happened')" rows="2" maxlength="1000" required /></div>
    <div class="col-12"><button class="btn btn-primary btn-sm"><x-icon name="plus-lg" />{{ __('Record incident') }}</button></div>
</form>
