@php use App\Support\FleetOptions; use App\Support\Money; @endphp
<div class="card panel-card">
    @if ($canManage)
        <details class="card-body border-bottom" @if ($errors->hasAny(['type', 'description', 'occurred_on'])) open @endif>
            <summary class="fw-semibold small">{{ __('Record accident, challan or damage') }}</summary>
            @include('operator.incidents._form', ['fixedVehicle' => $vehicle])
        </details>
    @endif
    <div class="list-group list-group-flush list-modern">
        @forelse ($vehicle->incidents as $i)
            @include('operator.incidents._row', ['i' => $i, 'showVehicle' => false])
        @empty
            <x-empty-state icon="exclamation-octagon" :title="__('No incidents')" />
        @endforelse
    </div>
</div>
