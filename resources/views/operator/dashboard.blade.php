@php
    use App\Support\Money;
    $plan = $subscription?->plan;
    $steps = [
        ['building', __('Complete business profile'), filled($tenant->gstin) || filled($tenant->address_line), route('operator.profile.edit'), 'settings.manage'],
        ['car-front', __('Add your first vehicle'), $usage['vehicles'] > 0, route('operator.vehicles.create'), 'vehicles.manage'],
        ['person-badge', __('Add your first driver'), $usage['drivers'] > 0, route('operator.drivers.create'), 'drivers.manage'],
        ['people', __('Invite your team'), $usage['users'] > 1, route('operator.staff.create'), 'staff.manage'],
    ];
    $done = collect($steps)->where(2, true)->count();
    $kpis = $taxi ? [
        ['label' => __('Pickups today'), 'value' => $today['pickups'], 'icon' => 'calendar-event', 'href' => auth()->user()->hasPermission('bookings.view') ? route('operator.bookings.index', ['view' => 'today']) : null],
        ['label' => __('On trip now'), 'value' => $on_trip, 'icon' => 'sign-turn-right'],
        ['label' => __('Enquiries & quotes'), 'value' => $today['to_quote'], 'icon' => 'chat-square-text', 'href' => auth()->user()->hasPermission('bookings.view') ? route('operator.bookings.index') : null],
        ['label' => __('Revenue this month'), 'value' => Money::format($today['revenue_month'], 0), 'icon' => 'graph-up-arrow'],
    ] : [
        ['label' => __('Vehicles available'), 'value' => $fleet['vehicles_available'].' / '.$usage['vehicles'], 'icon' => 'car-front'],
        ['label' => __('On trip now'), 'value' => $on_trip, 'icon' => 'sign-turn-right'],
        ['label' => __('In maintenance'), 'value' => $fleet['vehicles_maintenance'], 'icon' => 'tools'],
        ['label' => __('Fleet expenses this month'), 'value' => Money::format($fleet['expenses_month'], 0), 'icon' => 'fuel-pump'],
    ];
@endphp
<x-layouts.panel :title="__('Dashboard')" panel="operator">
    <x-hero-banner
        :title="__('Welcome, :name', ['name' => \Illuminate\Support\Str::before(auth()->user()->name, ' ')])"
        :subtitle="$tenant->name.' · '.now()->format('l, d M Y')"
        :kpis="$kpis">
        @can('bookings.create')<a href="{{ route('operator.bookings.create') }}" class="btn btn-primary"><x-icon name="plus-lg" />{{ __('New booking') }}</a>@endcan
        @can('vehicles.manage')<a href="{{ route('operator.vehicles.create') }}" class="btn btn-glass"><x-icon name="car-front" />{{ __('Vehicle') }}</a>@endcan
        @can('drivers.manage')<a href="{{ route('operator.drivers.create') }}" class="btn btn-glass"><x-icon name="person-plus" />{{ __('Driver') }}</a>@endcan
    </x-hero-banner>

    @if ($subscription && $subscription->status->value === 'trialing')
        <div class="alert border-0 d-flex flex-wrap align-items-center gap-3 rounded-4 mb-4" style="background: var(--brand-soft)">
            <span class="icon-tile tile-ink tile-sm"><x-icon name="gift" /></span>
            <div class="flex-grow-1"><div class="fw-bold">{{ __('Free trial: :days days left', ['days' => $subscription->daysLeft()]) }}</div><div class="small">{{ __('You are on the :plan plan trial until :date.', ['plan' => $plan->name, 'date' => $subscription->ends_at->format('d M Y')]) }}</div></div>
            @can('subscription.manage')<a href="{{ route('operator.subscription') }}" class="btn btn-ink btn-sm">{{ __('Choose a plan') }}</a>@endcan
        </div>
    @elseif ($subscription && $subscription->status->value === 'grace')
        <div class="alert alert-warning d-flex gap-2 rounded-4"><x-icon name="exclamation-triangle-fill" class="mt-1" />{{ __('Your subscription has ended. Renew before :d to avoid interruption.', ['d' => $subscription->grace_ends_at?->format('d M Y')]) }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Vehicles available')" :value="$fleet['vehicles_available'].' / '.$usage['vehicles']" icon="car-front" color="primary" :href="route('operator.vehicles.index')" :hint="$fleet['vehicles_maintenance'] ? __(':n in maintenance', ['n' => $fleet['vehicles_maintenance']]) : null" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Drivers available')" :value="$fleet['drivers_available'].' / '.$usage['drivers']" icon="person-badge" color="success" :href="route('operator.drivers.index')" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Fleet expenses this month')" :value="Money::format($fleet['expenses_month'], 0)" icon="fuel-pump" color="info" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Documents expiring (30 d)')" :value="count($expiring)" icon="exclamation-triangle" :color="count($expiring) ? 'danger' : 'secondary'" :href="route('operator.vehicles.index', ['compliance' => 'attention'])" /></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            @can('bookings.view')
            <div class="card panel-card mb-3">
                <div class="card-header"><span class="d-flex align-items-center gap-2"><x-icon name="calendar-event" class="text-warning" />{{ __('Upcoming pickups') }}</span><a href="{{ route('operator.bookings.index', ['view' => 'upcoming']) }}" class="small fw-semibold">{{ __('View all') }}</a></div>
                <div class="list-group list-group-flush list-modern">
                    @forelse ($upcoming as $b)
                        <a href="{{ route('operator.bookings.show', $b) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                            <span class="date-tile"><b>{{ $b->pickup_at->format('d') }}</b><small>{{ $b->pickup_at->format('M') }}</small></span>
                            <div class="me-auto min-w-0">
                                <div class="fw-semibold text-truncate">{{ $b->pickup_address }}@if ($b->drop_address) → {{ $b->drop_address }}@endif</div>
                                <div class="small text-muted-2 text-truncate">{{ $b->pickup_at->format('h:i A') }} · {{ $b->customer_name }}@if ($b->trip?->driver) · {{ $b->trip->driver->name }}@endif</div>
                            </div>
                            <x-status-badge :status="$b->status" />
                        </a>
                    @empty
                        <x-empty-state icon="calendar-event" :title="__('No upcoming pickups')" :text="__('New bookings will show up here.')" />
                    @endforelse
                </div>
            </div>
            <div class="card panel-card mb-3">
                <div class="card-header"><span class="d-flex align-items-center gap-2"><x-icon name="bar-chart" class="text-warning" />{{ __('Bookings · last 14 days') }}</span></div>
                <div class="card-body"><x-bar-chart :series="$chart" :label="__('Bookings per day')" /></div>
            </div>
            @endcan
            @if ($done < count($steps))
                <div class="card panel-card mb-3">
                    <div class="card-header">{{ __('Get started') }} <span class="small fw-normal text-muted-2">{{ __(':done of :total done', ['done' => $done, 'total' => count($steps)]) }}</span></div>
                    <div class="px-3 pt-3"><div class="progress" style="height:6px" role="progressbar" aria-valuenow="{{ $done }}" aria-valuemax="{{ count($steps) }}" aria-label="{{ __('Setup progress') }}"><div class="progress-bar bg-warning" style="width: {{ $done / count($steps) * 100 }}%"></div></div></div>
                    <div class="list-group list-group-flush list-modern">
                        @foreach ($steps as [$icon, $label, $isDone, $url, $perm])
                            @if (auth()->user()->hasPermission($perm))
                                <a href="{{ $url }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                                    <x-icon :name="$isDone ? 'check-circle-fill' : $icon" class="{{ $isDone ? 'text-success' : 'text-muted-2' }}" />
                                    <span @class(['me-auto', 'text-decoration-line-through text-muted-2' => $isDone])>{{ $label }}</span>
                                    @unless ($isDone)<x-icon name="chevron-right" class="text-muted-2" />@endunless
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="card panel-card">
                <div class="card-header"><span class="d-flex align-items-center gap-2"><x-icon name="calendar-x" class="text-danger" />{{ __('Documents needing attention') }}</span></div>
                <div class="list-group list-group-flush list-modern">
                    @forelse ($expiring as $item)
                        <a href="{{ $item['url'] }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                            <span class="stat-ico tone-{{ $item['days'] < 0 ? 'danger' : 'warning' }}" style="width:2.2rem;height:2.2rem"><x-icon :name="$item['kind'] === 'vehicle' ? 'car-front' : 'person-badge'" /></span>
                            <div class="me-auto min-w-0"><div class="fw-semibold text-truncate">{{ $item['name'] }}</div><div class="small text-muted-2">{{ $item['what'] }} · {{ $item['date'] }}</div></div>
                            <span class="badge-soft {{ $item['days'] < 0 ? 'danger' : ($item['days'] <= 7 ? 'danger' : 'warn') }}">{{ $item['days'] < 0 ? __('Expired') : trans_choice('{0} Today|{1} 1 day|[2,*] :n days', $item['days'], ['n' => $item['days']]) }}</span>
                        </a>
                    @empty
                        <x-empty-state icon="shield-check" :title="__('All documents are valid')" :text="__('We will remind you 30 days before anything expires.')" />
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card panel-card mb-3">
                <div class="card-header">{{ __('Plan usage') }} @if ($plan)<span class="badge-soft info">{{ $plan->name }}</span>@endif</div>
                <div class="card-body d-grid gap-3">
                    @foreach (['vehicles' => [__('Vehicles'), 'max_vehicles'], 'drivers' => [__('Drivers'), 'max_drivers'], 'users' => [__('Staff users'), 'max_users'], 'branches' => [__('Branches'), 'max_branches']] as $key => [$label, $col])
                        @php $max = $plan?->{$col} ?? 0; $pct = $max ? min(100, round($usage[$key] / $max * 100)) : 0; @endphp
                        <div>
                            <div class="d-flex justify-content-between small mb-1"><span>{{ $label }}</span><b>{{ $usage[$key] }} / {{ $max ?: '—' }}</b></div>
                            <div class="progress" style="height:8px" role="progressbar" aria-valuenow="{{ $usage[$key] }}" aria-valuemax="{{ $max }}" aria-label="{{ $label }}"><div class="progress-bar {{ $pct >= 100 ? 'bg-danger' : ($pct >= 80 ? 'bg-warning' : 'bg-success') }}" style="width: {{ $pct }}%"></div></div>
                        </div>
                    @endforeach
                    @can('subscription.manage')<a href="{{ route('operator.subscription') }}" class="btn btn-sm btn-light border">{{ __('Manage subscription') }}</a>@endcan
                </div>
            </div>
            @if ($taxi)
            <div class="card panel-card">
                <div class="card-header">{{ __('Your booking page') }}</div>
                <div class="card-body" x-data="{ copied: false }">
                    <div class="input-group">
                        <input type="text" class="form-control" readonly value="{{ url('/o/'.$tenant->slug) }}" x-ref="link" aria-label="{{ __('Booking page link') }}">
                        <button class="btn btn-ink" type="button" @click="navigator.clipboard?.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 2000)" aria-label="{{ __('Copy link') }}">
                            <span x-show="!copied"><x-icon name="clipboard" /></span><span x-show="copied" x-cloak><x-icon name="check2" /></span>
                        </button>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <a href="{{ url('/o/'.$tenant->slug) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><x-icon name="box-arrow-up-right" />{{ __('Open') }}</a>
                        <a href="https://wa.me/?text={{ rawurlencode(__('Book your taxi with :name: :url', ['name' => $tenant->name, 'url' => url('/o/'.$tenant->slug)])) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><x-icon name="whatsapp" class="text-success" />{{ __('Share on WhatsApp') }}</a>
                    </div>
                    <div class="small text-muted-2 mt-2">{{ __('Customers can get a fare, verify their mobile and book online from this page.') }}</div>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-layouts.panel>
