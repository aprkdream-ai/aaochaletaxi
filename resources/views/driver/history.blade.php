<x-layouts.driver :title="__('Trip history')">
    <h1 class="h5 mb-3">{{ __('Completed trips') }}</h1>
    @forelse ($trips as $trip)
        <a href="{{ route('driver.trips.show', $trip) }}" class="card panel-card mb-2 text-reset text-decoration-none">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <span class="stat-ico tone-success" style="width:2.4rem;height:2.4rem"><x-icon name="check2-circle" /></span>
                <div class="min-w-0 me-auto">
                    <div class="fw-semibold text-truncate">{{ $trip->pickupLabel() ?: $trip->purpose }}@if ($trip->dropLabel()) → {{ $trip->dropLabel() }}@endif</div>
                    <div class="small text-muted-2">{{ $trip->completed_at?->format('d M Y, h:i A') }} · {{ $trip->number() }}@if ($trip->end_km && $trip->start_km) · {{ number_format($trip->end_km - $trip->start_km) }} km @endif</div>
                </div>
                <x-icon name="chevron-right" class="text-muted-2" />
            </div>
        </a>
    @empty
        <div class="card panel-card"><x-empty-state icon="clock-history" :title="__('No completed trips yet')" :text="__('Trips you complete will be listed here.')" /></div>
    @endforelse
    <div class="mt-3">{{ $trips->links() }}</div>
</x-layouts.driver>
