<x-layouts.panel :title="__('Business profile')" panel="operator">
    <x-page-header :title="__('Business profile')" :subtitle="__('Shown on invoices and your booking page')" />
    <form method="POST" action="{{ route('operator.profile.update') }}" enctype="multipart/form-data" data-loading novalidate>
        @csrf @method('PUT')
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Business') }}</div><div class="card-body"><div class="row g-2">
                    <div class="col-md-6"><x-input name="name" icon="building" :label="__('Business / brand name')" :value="$tenant->name" required /></div>
                    <div class="col-md-6"><x-input name="legal_name" :label="__('Legal name (as per GST/PAN)')" :value="$tenant->legal_name" /></div>
                    <div class="col-md-6"><x-input name="email" type="email" icon="envelope" :label="__('Business email')" :value="$tenant->email" required /></div>
                    <div class="col-md-6"><x-input name="phone" type="tel" icon="phone" :label="__('Business phone')" :value="$tenant->phone" required inputmode="numeric" /></div>
                    <div class="col-md-6"><x-input name="gstin" label="GSTIN" :value="$tenant->gstin" maxlength="15" style="text-transform:uppercase" :help="__('Optional. 15 characters, e.g. 24ABCDE1234F1Z5')" /></div>
                    <div class="col-md-6"><x-input name="pan" label="PAN" :value="$tenant->pan" maxlength="10" style="text-transform:uppercase" /></div>
                </div></div></div>
                <div class="card panel-card"><div class="card-header">{{ __('Address') }}</div><div class="card-body"><div class="row g-2">
                    <div class="col-12"><x-input name="address_line" icon="geo-alt" :label="__('Street address')" :value="$tenant->address_line" maxlength="255" /></div>
                    <div class="col-md-5"><x-input name="city" :label="__('City')" :value="$tenant->city" required /></div>
                    <div class="col-md-4"><x-input name="state" :label="__('State')" :value="$tenant->state" /></div>
                    <div class="col-md-3"><x-input name="pincode" :label="__('PIN code')" :value="$tenant->pincode" inputmode="numeric" maxlength="6" /></div>
                </div></div></div>
            </div>
            <div class="col-lg-4">
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Branding') }}</div><div class="card-body" x-data="{ preview: @js($tenant->logoUrl()) }">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-4 bg-surface-2 d-flex align-items-center justify-content-center overflow-hidden border" style="width:72px;height:72px">
                            <img x-show="preview" :src="preview" alt="{{ __('Logo preview') }}" style="max-width:100%;max-height:100%;object-fit:contain" x-cloak>
                            <span x-show="!preview" class="text-muted-2"><x-icon name="image" class="icon-lg" /></span>
                        </div>
                        <div class="small text-muted-2">{{ __('PNG, JPG or WEBP, up to 1 MB. Square works best.') }}</div>
                    </div>
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="form-control mb-2 @error('logo') is-invalid @enderror" aria-label="{{ __('Logo') }}" @change="const f = $event.target.files[0]; if (f) preview = URL.createObjectURL(f)">
                    @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if ($tenant->logo_path)<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo"><label class="form-check-label small" for="remove_logo">{{ __('Remove current logo') }}</label></div>@endif
                    <label class="form-label mt-2" for="primary_color">{{ __('Brand colour') }}</label>
                    <input type="color" name="primary_color" id="primary_color" value="{{ old('primary_color', $tenant->primary_color ?: '#ffb703') }}" class="form-control form-control-color w-100">
                </div></div>
                <div class="card panel-card"><div class="card-body">
                    <div class="small text-muted-2 mb-1">{{ __('Booking page address') }}</div>
                    <code>{{ url('/o/'.$tenant->slug) }}</code>
                    <button class="btn btn-primary w-100 mt-3"><x-icon name="check2" />{{ __('Save profile') }}</button>
                </div></div>
            </div>
        </div>
    </form>
</x-layouts.panel>
