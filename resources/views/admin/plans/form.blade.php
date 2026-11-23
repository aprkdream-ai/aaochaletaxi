@php $editing = $plan->exists; @endphp
<x-layouts.panel :title="$editing ? __('Edit plan') : __('New plan')" panel="admin">
    <x-page-header :title="$editing ? __('Edit :name', ['name' => $plan->name]) : __('New plan')">
        <a href="{{ route('admin.plans.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
    </x-page-header>
    <form method="POST" action="{{ $editing ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" data-loading novalidate>
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Details') }}</div><div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-6"><x-input name="name" :label="__('Plan name')" :value="$plan->name" required maxlength="100" /></div>
                        <div class="col-md-6">
                            @if ($editing)
                                <label class="form-label">{{ __('Code') }}</label><input class="form-control" value="{{ $plan->code }}" disabled><div class="form-text">{{ __('Code cannot be changed.') }}</div>
                            @else
                                <x-input name="code" :label="__('Code')" required maxlength="30" :help="__('Lowercase, e.g. growth. Cannot be changed later.')" />
                            @endif
                        </div>
                        <div class="col-12"><x-input name="description" :label="__('Short description')" :value="$plan->description" maxlength="500" /></div>
                        <div class="col-12 mb-3">
                            <label class="form-label" for="features_text">{{ __('Features (one per line)') }}</label>
                            <textarea name="features_text" id="features_text" rows="5" class="form-control @error('features_text') is-invalid @enderror">{{ old('features_text', implode("\n", $plan->features ?? [])) }}</textarea>
                        </div>
                    </div>
                </div></div>
                <div class="card panel-card"><div class="card-header">{{ __('Limits') }}</div><div class="card-body">
                    <div class="row g-2">
                        <div class="col-6 col-md-3"><x-input name="max_vehicles" type="number" :label="__('Vehicles')" :value="$plan->max_vehicles" min="1" required /></div>
                        <div class="col-6 col-md-3"><x-input name="max_drivers" type="number" :label="__('Drivers')" :value="$plan->max_drivers" min="1" required /></div>
                        <div class="col-6 col-md-3"><x-input name="max_branches" type="number" :label="__('Branches')" :value="$plan->max_branches" min="1" required /></div>
                        <div class="col-6 col-md-3"><x-input name="max_users" type="number" :label="__('Staff users')" :value="$plan->max_users" min="1" required /></div>
                    </div>
                </div></div>
            </div>
            <div class="col-lg-4">
                <div class="card panel-card"><div class="card-header">{{ __('Pricing') }}</div><div class="card-body">
                    <x-input name="price_monthly" type="number" step="1" min="0" icon="currency-rupee" :label="__('Monthly price (excl. GST)')" :value="$plan->price_monthly ? (int) $plan->price_monthly : null" required />
                    <x-input name="price_yearly" type="number" step="1" min="0" icon="currency-rupee" :label="__('Yearly price (excl. GST)')" :value="$plan->price_yearly ? (int) $plan->price_yearly : null" required />
                    <div class="border rounded-3 p-2 pb-0 mb-3">
                        <div class="small fw-semibold mb-1">{{ __('Add-on: second module') }}</div>
                        <div class="form-text mt-0 mb-2">{{ __('Charged on top of the plan for operators running Taxi + Fleet. 0 = free.') }}</div>
                        <x-input name="addon_price_monthly" type="number" step="1" min="0" icon="currency-rupee" :label="__('Add-on per month (excl. GST)')" :value="(int) $plan->addon_price_monthly" />
                        <x-input name="addon_price_yearly" type="number" step="1" min="0" icon="currency-rupee" :label="__('Add-on per year (excl. GST)')" :value="(int) $plan->addon_price_yearly" />
                    </div>
                    <x-input name="trial_days" type="number" min="0" max="90" :label="__('Trial days')" :value="$plan->trial_days" required />
                    <x-input name="sort_order" type="number" min="0" :label="__('Display order')" :value="$plan->sort_order" />
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $plan->is_active))><label class="form-check-label" for="is_active">{{ __('Visible & available') }}</label></div>
                    <x-switch name="advanced_reports" :label="__('Advanced reports (vehicle & booking profitability)')" :checked="$plan->advanced_reports" />
                    <button class="btn btn-primary w-100">{{ $editing ? __('Save changes') : __('Create plan') }}</button>
                </div></div>
            </div>
        </div>
    </form>
</x-layouts.panel>
