@php $b = $trip->booking; @endphp
<a href="{{ route('driver.trips.show', $trip) }}" class="trip-card card panel-card mb-2 text-reset text-decoration-none">
    <div class="card-body p-3">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge-soft text-nowrap {{ $trip->status === 'started' ? 'ok' : 'info' }}">{{ $trip->status === 'started' ? __('In progress') : $trip->start_at->format('d M · h:i A') }}</span>
            <span class="small text-muted-2 text-truncate min-w-0">{{ $b->type->label() }}</span>
            <span class="ms-auto small fw-semibold text-nowrap">{{ $b->booking_number }}</span>
        </div>
        <div class="route-line">
            <div><x-icon name="circle-fill" class="text-success" /><span class="text-truncate">{{ $b->pickup_address }}</span></div>
            @if ($b->drop_address)<div><x-icon name="geo-alt-fill" class="text-danger" /><span class="text-truncate">{{ $b->drop_address }}</span></div>@endif
        </div>
        <div class="d-flex align-items-center gap-2 small text-muted-2 mt-2">
            <x-icon name="car-front" /><span class="text-truncate min-w-0">{{ $trip->vehicle?->displayRegistration() }} · {{ $b->customer_name }}</span>
            <x-icon name="chevron-right" class="ms-auto" />
        </div>
    </div>
</a>
