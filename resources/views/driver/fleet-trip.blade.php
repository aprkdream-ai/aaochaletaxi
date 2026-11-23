@php
    $phone = preg_replace('/\D/', '', (string) $trip->requested_phone);
    $phone = strlen($phone) === 10 ? '91'.$phone : $phone;
    $statusLabel = ['assigned' => __('Assigned'), 'started' => __('In progress'), 'completed' => __('Completed'), 'released' => __('Cancelled')][$trip->status] ?? $trip->status;
@endphp
<x-layouts.driver :title="$trip->trip_number" :back="route('driver.home')">
    <div class="card panel-card mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge-soft {{ $trip->status === 'started' ? 'ok' : ($trip->status === 'released' ? 'muted' : 'info') }}">{{ $statusLabel }}</span>
                <span class="small text-muted-2 text-truncate">{{ $trip->purpose }}@if ($trip->department) · {{ $trip->department->name }}@endif</span>
            </div>
            <div class="fs-5 fw-bold">{{ $trip->start_at->format('D, d M · h:i A') }}</div>
            <div class="small text-muted-2">{{ __('Until :d', ['d' => $trip->end_at->format('d M, h:i A')]) }}</div>
            @if ($trip->pickup_point || $trip->drop_point)
                <div class="route-line mt-3">
                    @if ($trip->pickup_point)<div><x-icon name="circle-fill" class="text-success" /><span>{{ $trip->pickup_point }}</span></div>@endif
                    @if ($trip->drop_point)<div><x-icon name="geo-alt-fill" class="text-danger" /><span>{{ $trip->drop_point }}</span></div>@endif
                </div>
            @endif
            @if ($trip->notes)<div class="alert alert-light border small mt-3 mb-0"><x-icon name="info-circle" /> {{ $trip->notes }}</div>@endif
        </div>
        @if (in_array($trip->status, ['assigned', 'started'], true))
            <div class="trip-actions p-3 pt-0">
                @if ($phone)
                    <a href="tel:+{{ $phone }}" class="btn btn-light border"><x-icon name="telephone-fill" /><span class="d-block small">{{ __('Call') }}</span></a>
                    <a href="https://wa.me/{{ $phone }}" target="_blank" rel="noopener" class="btn btn-light border"><x-icon name="whatsapp" class="text-success" /><span class="d-block small">WhatsApp</span></a>
                @endif
                @if ($trip->pickup_point || $trip->drop_point)<a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="btn btn-primary"><x-icon name="sign-turn-right-fill" /><span class="d-block small">{{ __('Navigate') }}</span></a>@endif
            </div>
        @endif
    </div>

    <div class="card panel-card mb-3">
        <div class="list-group list-group-flush list-modern small">
            @if ($trip->requested_by)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Requested by') }}</span><span class="fw-semibold">{{ $trip->requested_by }}</span></div>@endif
            @if ($trip->passengers)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Passengers') }}</span><span>{{ $trip->passengers }}</span></div>@endif
            <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Vehicle') }}</span><span>{{ $trip->vehicle?->displayRegistration() }} · {{ $trip->vehicle?->make }} {{ $trip->vehicle?->model }}</span></div>
            @if ($trip->started_at)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Started') }}</span><span>{{ $trip->started_at->format('d M, h:i A') }} · {{ number_format($trip->start_km) }} km</span></div>@endif
            @if ($trip->completed_at)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Completed') }}</span><span>{{ $trip->completed_at->format('d M, h:i A') }} · {{ number_format($trip->end_km) }} km</span></div>@endif
            @if ($trip->km() !== null)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Distance') }}</span><span class="fw-bold">{{ number_format($trip->km()) }} km</span></div>@endif
        </div>
    </div>

    @if ($trip->status === 'assigned')
        <form method="POST" action="{{ route('driver.trips.start', $trip) }}" class="card panel-card mb-3">@csrf
            <div class="card-body">
                <div class="fw-bold mb-3">{{ __('Start trip') }}</div>
                <label class="form-label small" for="start_km">{{ __('Odometer reading (km)') }}</label>
                <input id="start_km" name="start_km" type="number" min="0" inputmode="numeric" class="form-control form-control-lg @error('start_km') is-invalid @enderror" required value="{{ old('start_km', $trip->vehicle?->odometer_km) }}">
                @error('start_km')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <button class="btn btn-success btn-lg w-100 mt-3"><x-icon name="play-fill" />{{ __('Start trip') }}</button>
            </div>
        </form>
    @elseif ($trip->status === 'started')
        <div class="small text-muted-2 mb-2 d-flex align-items-center gap-2" x-data="tripTracker(@js(route('driver.trips.location', $trip)))" x-init="start()">
            <x-icon name="broadcast" /><span x-text="status">{{ __('Sharing live location with your operator during this trip.') }}</span>
        </div>
        <form method="POST" action="{{ route('driver.trips.complete', $trip) }}" class="card panel-card mb-3" data-confirm="{{ __('Complete this trip?') }}" data-confirm-tone="primary">@csrf
            <div class="card-body">
                <div class="fw-bold mb-3">{{ __('Complete trip') }}</div>
                <label class="form-label small" for="end_km">{{ __('Closing odometer (km)') }}</label>
                <input id="end_km" name="end_km" type="number" min="{{ $trip->start_km }}" inputmode="numeric" class="form-control form-control-lg @error('end_km') is-invalid @enderror" required value="{{ old('end_km') }}">
                <div class="form-text">{{ __('Started at :n km', ['n' => number_format($trip->start_km)]) }}</div>
                @error('end_km')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <button class="btn btn-ink btn-lg w-100 mt-3"><x-icon name="flag-fill" />{{ __('Complete trip') }}</button>
            </div>
        </form>
    @endif
</x-layouts.driver>
