<x-layouts.panel :title="__('Drivers')" panel="operator">
    <x-page-header :title="__('Drivers')" :subtitle="__('Profiles, licences and driver app access')">
        <x-limit-meter :used="$usage" :limit="$limit" :label="__('Plan')" />
        @can('drivers.manage')<a href="{{ route('operator.drivers.create') }}" class="btn btn-primary"><x-icon name="person-plus" />{{ __('Add driver') }}</a>@endcan
    </x-page-header>
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Total drivers')" :value="$counts['total']" icon="person-badge" color="primary" :href="route('operator.drivers.index')" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Available now')" :value="$counts['available']" icon="check2-circle" color="success" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Using driver app')" :value="$counts['app']" icon="phone" color="info" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Licence due (30 d)')" :value="$counts['attention']" icon="exclamation-triangle" color="danger" :href="route('operator.drivers.index', ['compliance' => 'attention'])" /></div>
    </div>
    <div class="card panel-card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2" role="search">
                <div class="col-md-5"><div class="input-icon"><x-icon name="search" /><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Name, mobile or licence') }}" aria-label="{{ __('Search') }}"></div></div>
                <div class="col-6 col-md-3"><select name="status" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Status') }}"><option value="">{{ __('Any status') }}</option>@foreach (\App\Enums\DriverStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
                @if ($branches->count() > 1)<div class="col-6 col-md-2"><select name="branch" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Branch') }}"><option value="">{{ __('All branches') }}</option>@foreach ($branches as $b)<option value="{{ $b->id }}" @selected((int) ($filters['branch'] ?? 0) === $b->id)>{{ $b->name }}</option>@endforeach</select></div>@endif
                <div class="col-md-2 d-flex gap-2"><button class="btn btn-ink flex-fill"><x-icon name="funnel" />{{ __('Filter') }}</button>@if (array_filter($filters))<a href="{{ route('operator.drivers.index') }}" class="btn btn-light" aria-label="{{ __('Reset') }}"><x-icon name="x-lg" /></a>@endif</div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-modern align-middle">
                <thead><tr><th>{{ __('Driver') }}</th><th>{{ __('Licence') }}</th><th>{{ __('Status') }}</th><th>{{ __('App') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($drivers as $d)
                        <tr>
                            <td><div class="d-flex align-items-center gap-3">
                                <span class="avatar overflow-hidden">@if ($d->photoUrl())<img src="{{ $d->photoUrl() }}" alt="" class="w-100 h-100" style="object-fit:cover" loading="lazy">@else{{ mb_strtoupper(mb_substr($d->name, 0, 1)) }}@endif</span>
                                <div class="min-w-0"><a href="{{ route('operator.drivers.show', $d) }}" class="fw-semibold text-reset">{{ $d->name }}</a><div class="small text-muted-2">{{ $d->phone }}@if ($d->branch) · {{ $d->branch->name }}@endif</div></div>
                            </div></td>
                            <td class="small"><div class="text-nowrap">{{ $d->licence_number }}</div><x-expiry-badge :expiry="$d->licence()" /></td>
                            <td><x-status-badge :status="$d->status" /> <span class="badge-soft {{ $d->is_available ? 'ok' : 'muted' }}">{{ $d->is_available ? __('Available') : __('Off duty') }}</span></td>
                            <td class="small">@if ($d->user)<span class="badge-soft {{ $d->user->status->value === 'active' ? 'info' : 'muted' }}"><x-icon name="phone" /> {{ $d->user->status->value === 'active' ? __('Enabled') : __('Disabled') }}</span>@else<span class="text-muted-2">—</span>@endif</td>
                            <td class="text-end"><a href="{{ route('operator.drivers.show', $d) }}" class="btn btn-sm btn-light">{{ __('Open') }}<x-icon name="chevron-right" /></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="person-badge" :title="array_filter($filters) ? __('No drivers match these filters') : __('Add your first driver')" :text="__('Keep licences, documents and app access in one place.')">
                            @can('drivers.manage')<a href="{{ route('operator.drivers.create') }}" class="btn btn-primary mt-3"><x-icon name="person-plus" />{{ __('Add driver') }}</a>@endcan
                        </x-empty-state></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($drivers->hasPages())<div class="card-body border-top">{{ $drivers->links() }}</div>@endif
    </div>
</x-layouts.panel>
