<x-layouts.driver :title="__('My trips')">
    <div class="d-flex align-items-center gap-3 mb-3">
        <span class="avatar" style="width:3rem;height:3rem">{{ mb_strtoupper(mb_substr($driver->name, 0, 1)) }}</span>
        <div class="min-w-0 me-auto"><h1 class="h5 mb-0 text-truncate">{{ __('Hi, :name', ['name' => \Illuminate\Support\Str::before($driver->name.' ', ' ')]) }}</h1><div class="small text-muted-2 text-truncate">{{ auth()->user()->tenant->name }}</div></div>
        <form method="POST" action="{{ route('driver.availability') }}">@csrf
            <button class="duty-toggle {{ $driver->is_available ? 'on' : '' }}" aria-pressed="{{ $driver->is_available ? 'true' : 'false' }}">
                <x-icon name="power" />{{ $driver->is_available ? __('On duty') : __('Off duty') }}
            </button>
        </form>
    </div>

    @if ($active)
        <div class="small fw-bold text-uppercase text-muted-2 mb-2">{{ __('Current trip') }}</div>
        @include('driver._trip-card', ['trip' => $active])
    @endif

    <div class="row g-2 mb-3">
        <div class="col-6"><x-stat-card :label="__('Today’s trips')" :value="$today->count() + ($active ? 1 : 0)" icon="sign-turn-right" color="primary" /></div>
        <div class="col-6"><x-stat-card :label="__('Completed today')" :value="$completedToday" icon="check2-circle" color="success" /></div>
    </div>

    @if ($overdue->isNotEmpty())
        <div class="small fw-bold text-uppercase text-danger mb-2">{{ __('Pending (past pickup time)') }}</div>
        @foreach ($overdue as $trip) @include('driver._trip-card') @endforeach
    @endif

    <div class="small fw-bold text-uppercase text-muted-2 mb-2">{{ __('Today') }}</div>
    @forelse ($today as $trip)
        @include('driver._trip-card')
    @empty
        <div class="card panel-card mb-3"><x-empty-state icon="sign-turn-right" :title="__('No trips today')" :text="__('New trips assigned by your operator will appear here automatically.')" /></div>
    @endforelse

    @if ($upcoming->isNotEmpty())
        <div class="small fw-bold text-uppercase text-muted-2 mb-2 mt-3">{{ __('Upcoming') }}</div>
        @foreach ($upcoming as $trip) @include('driver._trip-card') @endforeach
    @endif
</x-layouts.driver>
