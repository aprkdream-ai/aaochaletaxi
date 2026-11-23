@php $canManage = auth()->user()->hasPermission('fleet_trips.manage'); @endphp
<x-layouts.panel :title="$trip->trip_number" panel="operator">
    <x-page-header :title="$trip->trip_number" :subtitle="$trip->purpose">
        <a href="{{ route('operator.fleet-trips.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
        @if ($canManage && $trip->status === 'assigned')
            <a href="{{ route('operator.fleet-trips.edit', $trip) }}" class="btn btn-light border"><x-icon name="pencil" />{{ __('Edit') }}</a>
            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal"><x-icon name="x-circle" />{{ __('Cancel trip') }}</button>
        @endif
    </x-page-header>
    @foreach (['status', 'start_km', 'end_km', 'reason'] as $f)@error($f)<div class="alert alert-danger small">{{ $message }}</div>@enderror @endforeach

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card panel-card mb-3"><div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">@include('operator.fleet-trips._status', ['status' => $trip->status])
                    <span class="small text-muted-2">{{ $trip->start_at->format('D, d M Y · h:i A') }} → {{ $trip->end_at->format($trip->end_at->isSameDay($trip->start_at) ? 'h:i A' : 'd M, h:i A') }}</span></div>
                @if ($trip->pickup_point || $trip->drop_point)
                    <div class="route-line mb-3">
                        @if ($trip->pickup_point)<div><x-icon name="circle-fill" class="text-success" /><span>{{ $trip->pickup_point }}</span></div>@endif
                        @if ($trip->drop_point)<div><x-icon name="geo-alt-fill" class="text-danger" /><span>{{ $trip->drop_point }}</span></div>@endif
                    </div>
                @endif
                <dl class="row small mb-0">
                    <dt class="col-sm-4 text-muted-2 fw-semibold">{{ __('Vehicle') }}</dt><dd class="col-sm-8"><a href="{{ route('operator.vehicles.show', $trip->vehicle_id) }}">{{ $trip->vehicle?->displayRegistration() }}</a> · {{ $trip->vehicle?->make }} {{ $trip->vehicle?->model }}</dd>
                    <dt class="col-sm-4 text-muted-2 fw-semibold">{{ __('Driver') }}</dt><dd class="col-sm-8">{{ $trip->driver?->name }} · <a href="tel:{{ $trip->driver?->phone }}">{{ $trip->driver?->phone }}</a></dd>
                    <dt class="col-sm-4 text-muted-2 fw-semibold">{{ __('Department') }}</dt><dd class="col-sm-8">{{ $trip->department?->label() ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted-2 fw-semibold">{{ __('Requested by') }}</dt><dd class="col-sm-8">{{ $trip->requested_by ?: '—' }}@if ($trip->requested_phone) · <a href="tel:{{ $trip->requested_phone }}">{{ $trip->requested_phone }}</a>@endif</dd>
                    @if ($trip->passengers)<dt class="col-sm-4 text-muted-2 fw-semibold">{{ __('Passengers') }}</dt><dd class="col-sm-8">{{ $trip->passengers }}</dd>@endif
                    @if ($trip->notes)<dt class="col-sm-4 text-muted-2 fw-semibold">{{ __('Notes') }}</dt><dd class="col-sm-8">{{ $trip->notes }}</dd>@endif
                    @if ($trip->cancel_reason)<dt class="col-sm-4 text-muted-2 fw-semibold">{{ __('Cancel reason') }}</dt><dd class="col-sm-8 text-danger">{{ $trip->cancel_reason }}</dd>@endif
                </dl>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card panel-card mb-3"><div class="card-header">{{ __('Odometer') }}</div>
                <div class="list-group list-group-flush list-modern small">
                    <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Start') }}</span><span>{{ $trip->start_km !== null ? number_format($trip->start_km).' km' : '—' }}@if ($trip->started_at)<span class="text-muted-2"> · {{ $trip->started_at->format('d M h:i A') }}</span>@endif</span></div>
                    <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('End') }}</span><span>{{ $trip->end_km !== null ? number_format($trip->end_km).' km' : '—' }}@if ($trip->completed_at)<span class="text-muted-2"> · {{ $trip->completed_at->format('d M h:i A') }}</span>@endif</span></div>
                    <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Distance') }}</span><span class="fw-bold">{{ $trip->km() !== null ? number_format($trip->km()).' km' : '—' }}</span></div>
                    @if ($trip->hours() !== null)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Duration') }}</span><span>{{ __(':h h', ['h' => $trip->hours()]) }}</span></div>@endif
                </div>
                @if ($canManage && $trip->status === 'assigned')
                    <form method="POST" action="{{ route('operator.fleet-trips.start', $trip) }}" class="card-body border-top">@csrf
                        <label class="form-label small" for="s_km">{{ __('Start km (if the driver has no app)') }}</label>
                        <div class="input-group input-group-sm"><input type="number" name="start_km" id="s_km" min="0" value="{{ $trip->vehicle?->odometer_km }}" class="form-control" required><button class="btn btn-success"><x-icon name="play-fill" />{{ __('Start') }}</button></div>
                    </form>
                @elseif ($canManage && $trip->status === 'started')
                    <form method="POST" action="{{ route('operator.fleet-trips.complete', $trip) }}" class="card-body border-top">@csrf
                        <label class="form-label small" for="e_km">{{ __('Closing km') }}</label>
                        <div class="input-group input-group-sm"><input type="number" name="end_km" id="e_km" min="{{ $trip->start_km }}" class="form-control" required><button class="btn btn-ink"><x-icon name="flag-fill" />{{ __('Complete') }}</button></div>
                    </form>
                @endif
            </div>
            @if ($trip->status === 'started' && $trip->last_location_at)
                <div class="card panel-card"><div class="card-body small">
                    <x-icon name="geo-alt" /> {{ __('Last location :t', ['t' => $trip->last_location_at->diffForHumans()]) }}
                    <a href="https://www.google.com/maps?q={{ $trip->last_lat }},{{ $trip->last_lng }}" target="_blank" rel="noopener" class="ms-1">{{ __('Open map') }}</a>
                </div></div>
            @endif
        </div>
    </div>

    @if ($canManage && $trip->status === 'assigned')
        <x-modal id="cancelModal" :title="__('Cancel trip')" :action="route('operator.fleet-trips.cancel', $trip)" :submit="__('Cancel trip')" danger>
            <label class="form-label" for="c_reason">{{ __('Reason') }}</label>
            <textarea name="reason" id="c_reason" rows="2" class="form-control" required maxlength="300"></textarea>
        </x-modal>
    @endif
</x-layouts.panel>
