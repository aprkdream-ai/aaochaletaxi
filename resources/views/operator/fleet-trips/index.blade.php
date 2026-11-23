<x-layouts.panel :title="__('Fleet trips')" panel="operator">
    <x-page-header :title="__('Fleet trips')" :subtitle="__('Vehicle use by your staff, departments or institution — no customer fare')">
        @can('fleet_trips.manage')<a href="{{ route('operator.fleet-trips.create') }}" class="btn btn-primary"><x-icon name="plus-lg" />{{ __('New trip') }}</a>@endcan
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-4"><x-stat-card :label="__('Today')" :value="$counts['today']" icon="calendar-event" color="primary" :href="route('operator.fleet-trips.index', ['from' => today()->toDateString(), 'to' => today()->toDateString()])" /></div>
        <div class="col-4"><x-stat-card :label="__('In progress')" :value="$counts['running']" icon="sign-turn-right" color="success" :href="route('operator.fleet-trips.index', ['status' => 'started'])" /></div>
        <div class="col-4"><x-stat-card :label="__('Upcoming')" :value="$counts['upcoming']" icon="clock" color="info" :href="route('operator.fleet-trips.index', ['status' => 'assigned'])" /></div>
    </div>

    <div class="card panel-card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2 align-items-end" role="search">
                <div class="col-md-3"><label class="visually-hidden" for="q">{{ __('Search') }}</label><div class="input-icon"><x-icon name="search" /><input type="search" name="q" id="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Trip no., purpose, requester') }}"></div></div>
                <div class="col-6 col-md-2"><label class="visually-hidden" for="f_status">{{ __('Status') }}</label><select name="status" id="f_status" class="form-select">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (['open' => __('Open (assigned + running)'), 'assigned' => __('Assigned'), 'started' => __('In progress'), 'completed' => __('Completed'), 'released' => __('Cancelled')] as $v => $l)<option value="{{ $v }}" @selected(($filters['status'] ?? '') === $v)>{{ $l }}</option>@endforeach
                </select></div>
                <div class="col-6 col-md-2"><label class="visually-hidden" for="f_vehicle">{{ __('Vehicle') }}</label><select name="vehicle" id="f_vehicle" class="form-select"><option value="">{{ __('All vehicles') }}</option>@foreach ($vehicles as $v)<option value="{{ $v->id }}" @selected((int) ($filters['vehicle'] ?? 0) === $v->id)>{{ $v->displayRegistration() }}</option>@endforeach</select></div>
                <div class="col-6 col-md-2"><label class="visually-hidden" for="f_dept">{{ __('Department') }}</label><select name="department" id="f_dept" class="form-select"><option value="">{{ __('All departments') }}</option>@foreach ($departments as $d)<option value="{{ $d->id }}" @selected((int) ($filters['department'] ?? 0) === $d->id)>{{ $d->name }}</option>@endforeach</select></div>
                <div class="col-6 col-md-1"><label class="visually-hidden" for="f_from">{{ __('From') }}</label><input type="date" name="from" id="f_from" value="{{ $filters['from'] ?? '' }}" class="form-control" title="{{ __('From') }}"></div>
                <div class="col-6 col-md-1"><label class="visually-hidden" for="f_to">{{ __('To') }}</label><input type="date" name="to" id="f_to" value="{{ $filters['to'] ?? '' }}" class="form-control" title="{{ __('To') }}"></div>
                <div class="col-6 col-md-1 d-flex gap-1"><button class="btn btn-ink flex-fill" aria-label="{{ __('Filter') }}"><x-icon name="funnel" /></button>@if (array_filter($filters))<a href="{{ route('operator.fleet-trips.index') }}" class="btn btn-light" aria-label="{{ __('Reset') }}"><x-icon name="x-lg" /></a>@endif</div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-modern align-middle small">
                <thead><tr><th>{{ __('Trip') }}</th><th>{{ __('When') }}</th><th>{{ __('Vehicle / driver') }}</th><th>{{ __('Department') }}</th><th>{{ __('Km') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($trips as $t)
                        <tr>
                            <td><a href="{{ route('operator.fleet-trips.show', $t) }}" class="fw-semibold">{{ $t->trip_number }}</a><div class="text-muted-2 text-truncate" style="max-width:260px">{{ $t->purpose }}</div></td>
                            <td class="text-nowrap">{{ $t->start_at->format('d M, h:i A') }}<div class="text-muted-2">{{ __('to :t', ['t' => $t->end_at->format($t->end_at->isSameDay($t->start_at) ? 'h:i A' : 'd M, h:i A')]) }}</div></td>
                            <td>{{ $t->vehicle?->displayRegistration() }}<div class="text-muted-2">{{ $t->driver?->name }}</div></td>
                            <td>{{ $t->department?->name ?? '—' }}@if ($t->requested_by)<div class="text-muted-2">{{ $t->requested_by }}</div>@endif</td>
                            <td>{{ $t->km() !== null ? number_format($t->km()) : '—' }}</td>
                            <td>@include('operator.fleet-trips._status', ['status' => $t->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="signpost-split" :title="__('No trips found')" :text="__('Create a trip to assign a vehicle and driver for official use.')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($trips->hasPages())<div class="card-body border-top">{{ $trips->links() }}</div>@endif
    </div>
</x-layouts.panel>
