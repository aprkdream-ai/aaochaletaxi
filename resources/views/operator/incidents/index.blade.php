@php use App\Support\FleetOptions; @endphp
<x-layouts.panel :title="__('Incidents & challans')" panel="operator">
    <x-page-header :title="__('Incidents & challans')" :subtitle="__('Accidents, traffic challans, damage and theft — with insurance claims')" />
    @foreach (['amount', 'settled_amount', 'claim_status'] as $f)@error($f)<div class="alert alert-danger small">{{ $message }}</div>@enderror @endforeach
    @can('vehicles.manage')
        <details class="card panel-card mb-3" @if ($errors->hasAny(['vehicle_id', 'type', 'description', 'occurred_on'])) open @endif><summary class="card-header">{{ __('Record an incident') }}</summary>
            <div class="card-body pt-0">@include('operator.incidents._form')</div>
        </details>
    @endcan
    <div class="card panel-card">
        <form method="GET" class="card-body border-bottom row g-2">
            <div class="col-6 col-md-3"><select name="type" class="form-select" aria-label="{{ __('Type') }}" onchange="this.form.submit()"><option value="">{{ __('All types') }}</option>@foreach (FleetOptions::incidentTypes() as $v => $l)<option value="{{ $v }}" @selected(($filters['type'] ?? '') === $v)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-6 col-md-3"><select name="status" class="form-select" aria-label="{{ __('Status') }}" onchange="this.form.submit()"><option value="">{{ __('Open & closed') }}</option><option value="open" @selected(($filters['status'] ?? '') === 'open')>{{ __('Open') }}</option><option value="closed" @selected(($filters['status'] ?? '') === 'closed')>{{ __('Closed') }}</option></select></div>
            <div class="col-md-3"><select name="vehicle" class="form-select" aria-label="{{ __('Vehicle') }}" onchange="this.form.submit()"><option value="">{{ __('All vehicles') }}</option>@foreach ($vehicles as $v)<option value="{{ $v->id }}" @selected((int) ($filters['vehicle'] ?? 0) === $v->id)>{{ $v->displayRegistration() }}</option>@endforeach</select></div>
        </form>
        <div class="list-group list-group-flush list-modern">
            @forelse ($incidents as $i)
                @include('operator.incidents._row', ['i' => $i, 'showVehicle' => true])
            @empty
                <x-empty-state icon="exclamation-octagon" :title="__('No incidents')" :text="__('Record challans and accidents to track fines, repairs and claims.')" />
            @endforelse
        </div>
        @if ($incidents->hasPages())<div class="card-body border-top">{{ $incidents->links() }}</div>@endif
    </div>
</x-layouts.panel>
