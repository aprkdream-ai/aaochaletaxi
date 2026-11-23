@php use App\Support\FleetOptions; use App\Support\Money; $can = auth()->user()->hasPermission('vehicles.manage'); @endphp
<x-layouts.panel :title="__('Tyres & parts')" panel="operator">
    <x-page-header :title="__('Tyres & parts')" :subtitle="__('Stock, fitted parts with km run, warranty and scrap history')" />
    <div class="row g-3 mb-3">
        <div class="col-4"><x-stat-card :label="__('Fitted')" :value="$counts['fitted'] ?? 0" icon="gear" color="primary" :href="route('operator.parts.index', ['status' => 'fitted'])" /></div>
        <div class="col-4"><x-stat-card :label="__('In stock')" :value="$counts['in_stock'] ?? 0" icon="box-seam" color="success" :href="route('operator.parts.index', ['status' => 'in_stock'])" /></div>
        <div class="col-4"><x-stat-card :label="__('Scrapped')" :value="$counts['scrapped'] ?? 0" icon="trash" color="secondary" :href="route('operator.parts.index', ['status' => 'scrapped'])" /></div>
    </div>
    @foreach (['part', 'position', 'odometer_km', 'vehicle_id', 'quantity'] as $f)@error($f)<div class="alert alert-danger small">{{ $message }}</div>@enderror @endforeach
    @if ($can)
        <details class="card panel-card mb-3" @if ($errors->hasAny(['type', 'brand', 'cost'])) open @endif><summary class="card-header">{{ __('Add to stock') }}</summary>
            <form method="POST" action="{{ route('operator.parts.store') }}" class="card-body row g-2" data-loading>@csrf
                <div class="col-6 col-md-2"><x-select name="type" :label="__('Type')" :options="FleetOptions::partTypes()" required /></div>
                <div class="col-6 col-md-2"><x-input name="brand" :label="__('Brand')" required maxlength="60" /></div>
                <div class="col-6 col-md-3"><x-input name="model" :label="__('Model / size')" maxlength="80" placeholder="185/65 R15" /></div>
                <div class="col-6 col-md-3"><x-input name="serial_number" :label="__('Serial no.')" maxlength="60" /></div>
                <div class="col-6 col-md-2"><x-input name="quantity" type="number" min="1" max="20" :label="__('Quantity')" value="1" :help="__('Without serial only')" /></div>
                <div class="col-6 col-md-2"><x-input name="cost" type="number" step="0.01" min="0" :label="__('Cost each ₹')" /></div>
                <div class="col-6 col-md-2"><x-input name="purchase_date" type="date" :label="__('Bought on')" :max="now()->toDateString()" /></div>
                <div class="col-6 col-md-2"><x-input name="warranty_until" type="date" :label="__('Warranty till')" /></div>
                <div class="col-md-3"><x-input name="vendor" :label="__('Vendor')" maxlength="120" /></div>
                <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100 mb-3"><x-icon name="plus-lg" />{{ __('Add') }}</button></div>
            </form>
        </details>
    @endif
    <div class="card panel-card">
        <form method="GET" class="card-body border-bottom row g-2">
            <div class="col-md-4"><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Brand, model or serial') }}" aria-label="{{ __('Search') }}"></div>
            <div class="col-6 col-md-3"><select name="type" class="form-select" aria-label="{{ __('Type') }}" onchange="this.form.submit()"><option value="">{{ __('All types') }}</option>@foreach (FleetOptions::partTypes() as $v => $l)<option value="{{ $v }}" @selected(($filters['type'] ?? '') === $v)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-6 col-md-3"><select name="status" class="form-select" aria-label="{{ __('Status') }}" onchange="this.form.submit()"><option value="">{{ __('Fitted & in stock') }}</option>@foreach (['fitted' => __('Fitted'), 'in_stock' => __('In stock'), 'scrapped' => __('Scrapped')] as $v => $l)<option value="{{ $v }}" @selected(($filters['status'] ?? '') === $v)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-ink w-100"><x-icon name="funnel" />{{ __('Filter') }}</button></div>
        </form>
        <div class="table-responsive"><table class="table table-modern align-middle small">
            <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Km run') }}</th><th class="text-end">{{ __('Cost') }}</th><th>{{ __('Warranty') }}</th><th>{{ __('Action') }}</th></tr></thead>
            <tbody>
                @forelse ($parts as $p)
                    <tr>
                        <td><span class="fw-semibold">{{ FleetOptions::partTypes()[$p->type] }}</span> · {{ $p->label() }}@if ($p->vendor)<div class="text-muted-2">{{ $p->vendor }}</div>@endif</td>
                        <td>@if ($p->status === 'fitted')<a href="{{ route('operator.vehicles.show', [$p->vehicle_id, 'tab' => 'parts']) }}">{{ $p->vehicle?->displayRegistration() }}</a><div class="text-muted-2">{{ FleetOptions::partPositions()[$p->position] ?? '' }}</div>@else<span class="badge-soft {{ $p->status === 'in_stock' ? 'ok' : 'muted' }}">{{ $p->status === 'in_stock' ? __('In stock') : __('Scrapped') }}</span>@endif</td>
                        <td class="text-end fw-semibold">{{ number_format($p->totalKm()) }}</td>
                        <td class="text-end">{{ Money::format($p->cost, 0) }}</td>
                        <td>@if ($p->warranty_until)<x-expiry-badge :expiry="\App\Support\Expiry::of($p->warranty_until)" />@else — @endif</td>
                        <td>@if ($can && $p->status === 'in_stock')
                                <form method="POST" action="{{ route('operator.parts.fit', $p) }}" class="d-flex gap-1">@csrf
                                    <input type="hidden" name="moved_on" value="{{ now()->toDateString() }}">
                                    <select name="vehicle_id" class="form-select form-select-sm" required aria-label="{{ __('Vehicle') }}" style="min-width:120px"><option value="">{{ __('Vehicle…') }}</option>@foreach ($vehicles as $v)<option value="{{ $v->id }}">{{ $v->displayRegistration() }}</option>@endforeach</select>
                                    <select name="position" class="form-select form-select-sm" aria-label="{{ __('Position') }}" style="min-width:90px"><option value="">—</option>@foreach (FleetOptions::partPositions() as $v => $l)<option value="{{ $v }}">{{ $v }}</option>@endforeach</select>
                                    <button class="btn btn-sm btn-primary">{{ __('Fit') }}</button>
                                </form>
                                <form method="POST" action="{{ route('operator.parts.remove', $p) }}" class="mt-1" data-confirm="{{ __('Scrap this part?') }}">@csrf<input type="hidden" name="to" value="scrapped"><input type="hidden" name="moved_on" value="{{ now()->toDateString() }}"><button class="btn btn-link btn-sm text-danger p-0">{{ __('Scrap') }}</button></form>
                            @elseif ($can && $p->status === 'fitted')
                                <form method="POST" action="{{ route('operator.parts.remove', $p) }}" class="d-flex gap-1" data-confirm="{{ __('Remove this part?') }}">@csrf
                                    <input type="hidden" name="moved_on" value="{{ now()->toDateString() }}">
                                    <input type="number" name="odometer_km" value="{{ $p->vehicle?->odometer_km }}" min="0" class="form-control form-control-sm" style="max-width:110px" aria-label="{{ __('Odometer km') }}">
                                    <button name="to" value="in_stock" class="btn btn-sm btn-light border">{{ __('Remove') }}</button><button name="to" value="scrapped" class="btn btn-sm btn-light border text-danger">{{ __('Scrap') }}</button>
                                </form>
                            @endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="gear" :title="__('No parts')" :text="__('Add tyres and batteries to track km run, warranty and replacements.')" /></td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if ($parts->hasPages())<div class="card-body border-top">{{ $parts->links() }}</div>@endif
    </div>
</x-layouts.panel>
