@php use App\Support\Money; @endphp
<x-layouts.panel :title="__('Bookings')" panel="operator">
    <x-page-header :title="__('Bookings')" :subtitle="trans_choice('{0} No pickups today|{1} 1 pickup today|[2,*] :n pickups today', $today, ['n' => $today])">
        @can('bookings.create')<a href="{{ route('operator.bookings.create') }}" class="btn btn-primary"><x-icon name="plus-lg" />{{ __('New booking') }}</a>@endcan
    </x-page-header>

    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach (['active' => __('Active'), 'today' => __('Today'), 'upcoming' => __('Upcoming'), 'completed' => __('Completed'), 'cancelled' => __('Cancelled'), 'all' => __('All')] as $key => $label)
            <a href="{{ route('operator.bookings.index', ['view' => $key] + array_filter(['q' => $filters['q'] ?? null])) }}" @class(['btn btn-sm rounded-pill', 'btn-ink' => $view === $key, 'btn-light border' => $view !== $key])>{{ $label }}
                @if ($key === 'completed' || $key === 'cancelled')<span class="badge rounded-pill text-bg-light ms-1">{{ $counts[$key] ?? 0 }}</span>@endif
            </a>
        @endforeach
        <form method="GET" class="ms-auto d-flex gap-2" role="search">
            <input type="hidden" name="view" value="{{ $view }}">
            <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="form-control form-control-sm" onchange="this.form.submit()" aria-label="{{ __('Pickup date') }}">
            <div class="input-icon"><x-icon name="search" /><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="{{ __('Booking no., name or mobile') }}" aria-label="{{ __('Search') }}"></div>
        </form>
    </div>

    <div class="card panel-card">
        <div class="list-group list-group-flush list-modern">
            @forelse ($bookings as $b)
                <a href="{{ route('operator.bookings.show', $b) }}" class="list-group-item list-group-item-action py-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-2 col-5">
                            <div class="fw-800">{{ $b->pickup_at->format('d M') }}</div>
                            <div class="small text-muted-2">{{ $b->pickup_at->format('h:i A') }}</div>
                        </div>
                        <div class="col-md-4 col-7 min-w-0">
                            <div class="fw-semibold text-truncate">{{ $b->pickup_address }}@if ($b->drop_address) → {{ $b->drop_address }}@endif</div>
                            <div class="small text-muted-2 text-truncate">{{ $b->booking_number }} · {{ $b->type->label() }} · {{ $b->category->name }}</div>
                        </div>
                        <div class="col-md-2 col-6 small min-w-0">
                            <div class="fw-semibold text-truncate">{{ $b->customer_name }}</div><div class="text-muted-2">{{ $b->customer_phone }}</div>
                        </div>
                        <div class="col-md-2 col-6 small min-w-0">
                            @if ($b->trip)<div class="text-truncate"><x-icon name="car-front" /> {{ $b->trip->vehicle->displayRegistration() }}</div><div class="text-muted-2 text-truncate"><x-icon name="person" /> {{ $b->trip->driver->name }}</div>@else<span class="text-muted-2">{{ __('Not assigned') }}</span>@endif
                        </div>
                        <div class="col-md-2 col-12 text-md-end">
                            <div class="fw-bold">{{ $b->final_total ? Money::format($b->final_total, 0) : ($b->quoted_total ? Money::format($b->quoted_total, 0) : '—') }}</div>
                            <div class="d-flex gap-1 justify-content-md-end flex-wrap"><x-status-badge :status="$b->status" /><x-status-badge :status="$b->payment_status" /></div>
                        </div>
                    </div>
                </a>
            @empty
                <x-empty-state icon="journal-bookmark" :title="__('No bookings here')" :text="__('New bookings and enquiries will show up here.')">
                    @can('bookings.create')<a href="{{ route('operator.bookings.create') }}" class="btn btn-primary mt-3"><x-icon name="plus-lg" />{{ __('Create a booking') }}</a>@endcan
                </x-empty-state>
            @endforelse
        </div>
        @if ($bookings->hasPages())<div class="card-body border-top">{{ $bookings->links() }}</div>@endif
    </div>
</x-layouts.panel>
