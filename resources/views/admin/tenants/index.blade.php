<x-layouts.panel :title="__('Operators')" panel="admin">
    <x-page-header :title="__('Operators')" :subtitle="trans_choice('{0} No operators|{1} :n operator|[2,*] :n operators', $tenants->total(), ['n' => $tenants->total()])" />

    <div class="card panel-card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2 align-items-end" role="search">
                <div class="col-md-6">
                    <label class="visually-hidden" for="q">{{ __('Search') }}</label>
                    <div class="input-icon"><x-icon name="search" /><input type="search" name="q" id="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Search name, email, phone or city') }}"></div>
                </div>
                <div class="col-7 col-md-3">
                    <label class="visually-hidden" for="status">{{ __('Status') }}</label>
                    <select name="status" id="status" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-5 col-md-3 d-flex gap-2">
                    <button class="btn btn-ink flex-fill"><x-icon name="funnel" />{{ __('Filter') }}</button>
                    @if (array_filter($filters))<a href="{{ route('admin.tenants.index') }}" class="btn btn-light" aria-label="{{ __('Reset') }}"><x-icon name="x-lg" /></a>@endif
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-modern align-middle">
                <thead><tr><th>{{ __('Operator') }}</th><th>{{ __('City') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Users') }}</th><th>{{ __('Status') }}</th><th>{{ __('Registered') }}</th><th><span class="visually-hidden">{{ __('Actions') }}</span></th></tr></thead>
                <tbody>
                    @forelse ($tenants as $t)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="avatar">{{ mb_strtoupper(mb_substr($t->name, 0, 2)) }}</span>
                                    <div class="min-w-0"><a href="{{ route('admin.tenants.show', $t) }}" class="fw-semibold text-reset">{{ $t->name }}</a><div class="small text-muted-2 text-truncate">{{ $t->email }} · {{ $t->phone }}</div></div>
                                </div>
                            </td>
                            <td>{{ $t->city ?: '—' }}</td>
                            <td>
                                @if ($t->latestSubscription)
                                    <div class="fw-semibold small">{{ $t->latestSubscription->plan->name }}</div><x-status-badge :status="$t->latestSubscription->status" />
                                @else
                                    <span class="text-muted-2">—</span>
                                @endif
                            </td>
                            <td>{{ $t->users_count }}</td>
                            <td><x-status-badge :status="$t->status" /></td>
                            <td class="small text-muted-2 text-nowrap">{{ $t->created_at->format('d M Y') }}</td>
                            <td class="text-end"><a href="{{ route('admin.tenants.show', $t) }}" class="btn btn-sm btn-light">{{ __('Open') }}<x-icon name="chevron-right" /></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="buildings" :title="__('No operators found')" :text="__('Try a different search or status filter.')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tenants->hasPages())<div class="card-body border-top">{{ $tenants->links() }}</div>@endif
    </div>
</x-layouts.panel>
