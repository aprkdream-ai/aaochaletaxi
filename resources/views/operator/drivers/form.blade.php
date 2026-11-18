@php use App\Support\FleetOptions; $editing = $driver->exists; @endphp
<x-layouts.panel :title="$editing ? __('Edit driver') : __('Add driver')" panel="operator">
    <x-page-header :title="$editing ? $driver->name : __('Add driver')">
        <a href="{{ $editing ? route('operator.drivers.show', $driver) : route('operator.drivers.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
    </x-page-header>
    <form method="POST" action="{{ $editing ? route('operator.drivers.update', $driver) : route('operator.drivers.store') }}" enctype="multipart/form-data" data-loading novalidate x-data="{ app: @js((bool) old('app_access', false)) }">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Personal details') }}</div><div class="card-body"><div class="row g-2">
                    <div class="col-md-6"><x-input name="name" icon="person" :label="__('Full name')" :value="$driver->name" required maxlength="150" /></div>
                    <div class="col-md-6"><x-input name="phone" type="tel" icon="phone" :label="__('Mobile number')" :value="$driver->phone" required inputmode="numeric" maxlength="14" /></div>
                    <div class="col-md-6"><x-input name="alt_phone" type="tel" :label="__('Alternate mobile')" :value="$driver->alt_phone" inputmode="numeric" maxlength="14" /></div>
                    <div class="col-md-6"><x-input name="email" type="email" :label="__('Email')" :value="$driver->email" /></div>
                    <div class="col-md-4"><x-input name="date_of_birth" type="date" :label="__('Date of birth')" :value="$driver->date_of_birth?->toDateString()" /></div>
                    <div class="col-md-4"><x-input name="aadhaar_last4" :label="__('Aadhaar (last 4 digits)')" :value="$driver->aadhaar_last4" inputmode="numeric" maxlength="4" :help="__('Never store the full Aadhaar number.')" /></div>
                    <div class="col-md-4"><x-input name="joined_on" type="date" :label="__('Joined on')" :value="$driver->joined_on?->toDateString()" /></div>
                    <div class="col-12"><x-input name="address" :label="__('Address')" :value="$driver->address" maxlength="255" /></div>
                </div></div></div>
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Driving licence') }}</div><div class="card-body"><div class="row g-2">
                    <div class="col-md-5"><x-input name="licence_number" :label="__('Licence number')" :value="$driver->licence_number" required maxlength="20" style="text-transform:uppercase" placeholder="MH1220190012345" /></div>
                    <div class="col-md-4"><x-select name="licence_type" :label="__('Class')" :options="FleetOptions::licenceTypes()" :value="$driver->licence_type" :placeholder="__('Select…')" /></div>
                    <div class="col-md-3"><x-input name="licence_expiry" type="date" :label="__('Valid till')" :value="$driver->licence_expiry?->toDateString()" required /></div>
                </div></div></div>
                <div class="card panel-card"><div class="card-header">{{ __('Pay') }}</div><div class="card-body"><div class="row g-2">
                    <div class="col-md-6"><x-select name="pay_type" :label="__('Pay type')" :options="FleetOptions::payTypes()" :value="$driver->pay_type" required /></div>
                    <div class="col-md-6"><x-input name="pay_amount" type="number" step="0.01" min="0" icon="currency-rupee" :label="__('Amount / rate')" :value="$driver->pay_amount" /></div>
                    <div class="col-12"><x-textarea name="notes" :label="__('Notes')" :value="$driver->notes" rows="2" maxlength="1000" /></div>
                </div></div></div>
            </div>
            <div class="col-lg-4">
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Status') }}</div><div class="card-body">
                    <x-select name="status" :label="__('Status')" :options="collect(\App\Enums\DriverStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all()" :value="$driver->status" required />
                    <x-select name="branch_id" :label="__('Branch')" :options="$branches->pluck('name', 'id')->all()" :value="$driver->branch_id" :placeholder="__('— None —')" />
                    <x-switch name="is_available" :label="__('Available for trips')" :checked="$driver->is_available" />
                </div></div>
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Photo') }}</div><div class="card-body">
                    @if ($driver->photoUrl())<img src="{{ $driver->photoUrl() }}" alt="" class="rounded-circle mb-2" style="width:72px;height:72px;object-fit:cover">@endif
                    <input type="file" name="photo" accept="image/png,image/jpeg,image/webp" class="form-control @error('photo') is-invalid @enderror" aria-label="{{ __('Photo') }}">
                    @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div></div>
                @unless ($editing)
                    <div class="card panel-card mb-3"><div class="card-header">{{ __('Driver app') }}</div><div class="card-body">
                        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="app_access" value="1" id="app_access" x-model="app"><label class="form-check-label fw-semibold" for="app_access">{{ __('Give driver app login') }}</label></div>
                        <div class="small text-muted-2 mb-2">{{ __('Driver logs in with their mobile number and this password.') }}</div>
                        <div x-show="app" x-cloak>
                            <x-input name="app_password" type="password" :label="__('Password')" autocomplete="new-password" />
                            <x-input name="app_password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" />
                        </div>
                    </div></div>
                @endunless
                <button class="btn btn-primary btn-lg w-100"><x-icon name="check2" />{{ $editing ? __('Save changes') : __('Add driver') }}</button>
            </div>
        </div>
    </form>
</x-layouts.panel>
