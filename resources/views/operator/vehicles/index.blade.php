<x-layouts.panel :title="__('Vehicles')" panel="operator">
    <x-page-header :title="__('Vehicles')" :subtitle="__('Your fleet, documents and availability')">
        <x-limit-meter :used="$usage" :limit="$limit" :label="__('Plan')" />
        @can('vehicles.manage')<a href="{{ route('operator.vehicles.create') }}" class="btn btn-primary"><x-icon name="plus-lg" />{{ __('Add vehicle') }}</a>@endcan
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Total vehicles')" :value="$counts['total']" icon="car-front" color="primary" :href="route('operator.vehicles.index')" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Available now')" :value="$counts['available']" icon="check2-circle" color="success" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('In maintenance')" :value="$counts['maintenance']" icon="tools" color="warning" :href="route('operator.vehicles.index', ['status' => 'maintenance'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Documents due (30 d)')" :value="$counts['attention']" icon="exclamation-triangle" color="danger" :href="route('operator.vehicles.index', ['compliance' => 'attention'])" /></div>
    </div>

    <div class="card panel-card mb-3"><div class="card-body">
        <form method="GET" class="row g-2" role="search">
            <div class="col-md-4"><div class="input-icon"><x-icon name="search" /><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Registration, make or model') }}" aria-label="{{ __('Search') }}"></div></div>
            <div class="col-6 col-md-2"><select name="status" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Status') }}"><option value="">{{ __('Any status') }}</option>@foreach (\App\Enums\VehicleStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><select name="category" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Category') }}"><option value="">{{ __('All types') }}</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected((int) ($filters['category'] ?? 0) === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            @if ($branches->count() > 1)<div class="col-6 col-md-2"><select name="branch" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Branch') }}"><option value="">{{ __('All branches') }}</option>@foreach ($branches as $b)<option value="{{ $b->id }}" @selected((int) ($filters['branch'] ?? 0) === $b->id)>{{ $b->name }}</option>@endforeach</select></div>@endif
            <div class="col-6 col-md-2 d-flex gap-2"><button class="btn btn-ink flex-fill"><x-icon name="funnel" />{{ __('Filter') }}</button>@if (array_filter($filters))<a href="{{ route('operator.vehicles.index') }}" class="btn btn-light" aria-label="{{ __('Reset') }}"><x-icon name="x-lg" /></a>@endif</div>
        </form>
    </div></div>

    <div class="row g-3">
        @forelse ($vehicles as $v)
            @php $state = $v->complianceState(); @endphp
            <div class="col-md-6 col-xxl-4">
                <a href="{{ route('operator.vehicles.show', $v) }}" class="card panel-card card-hover h-100 text-reset text-decoration-none">
                    <div class="card-body">
                        <div class="d-flex gap-3 align-items-start">
                            <div class="rounded-4 bg-surface-2 d-flex align-items-center justify-content-center flex-shrink-0 overflow-hidden" style="width:64px;height:64px">
                                @if ($v->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($v->image_path) }}" alt="" loading="lazy" class="w-100 h-100" style="object-fit:cover">@else<x-icon :name="$v->category->icon ?? 'car-front'" class="fs-3 text-muted-2" />@endif
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex justify-content-between gap-2"><div class="fw-800 fs-5 ls-tight">{{ $v->displayRegistration() }}</div><x-status-badge :status="$v->status" /></div>
                                <div class="text-muted-2 small text-truncate">{{ $v->make }} {{ $v->model }}@if ($v->year) · {{ $v->year }}@endif · {{ $v->category->name }} · {{ $v->seats }} {{ __('seats') }}</div>
                                <div class="d-flex flex-wrap gap-1 mt-2">
                                    <span class="badge-soft {{ $v->is_available ? 'ok' : 'muted' }}">{{ $v->is_available ? __('Available') : __('Unavailable') }}</span>
                                    @if ($state !== 'ok')<span class="badge-soft {{ ['expired' => 'danger', 'due' => 'warn', 'missing' => 'muted'][$state] }}"><x-icon name="file-earmark-text" /> {{ ['expired' => __('Document expired'), 'due' => __('Renewal due'), 'missing' => __('Dates missing')][$state] }}</span>@endif
                                    @if ($v->branch)<span class="badge-soft muted">{{ $v->branch->name }}</span>@endif
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @empty
            <div class="col-12"><div class="card panel-card"><x-empty-state icon="car-front" :title="array_filter($filters) ? __('No vehicles match these filters') : __('Add your first vehicle')" :text="__('Track documents, maintenance and expenses for every car in your fleet.')">
                @can('vehicles.manage')<a href="{{ route('operator.vehicles.create') }}" class="btn btn-primary mt-3"><x-icon name="plus-lg" />{{ __('Add vehicle') }}</a>@endcan
            </x-empty-state></div></div>
        @endforelse
    </div>
    @if ($vehicles->hasPages())<div class="mt-3">{{ $vehicles->links() }}</div>@endif
</x-layouts.panel>
