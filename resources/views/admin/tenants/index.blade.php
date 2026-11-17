<x-layouts.panel :title="__('Operators')" panel="admin">
    <x-page-header :title="__('Operators')" :subtitle="__('All taxi businesses registered on the platform')" />

    <div class="card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small" for="q">{{ __('Search') }}</label>
                    <input type="search" name="q" id="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Name, email, phone, city') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small" for="status">{{ __('Status') }}</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-dark flex-fill"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
                    <a href="{{ route('admin.tenants.index') }}" class="btn btn-light">{{ __('Reset') }}</a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>{{ __('Operator') }}</th><th>{{ __('City') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Users') }}</th><th>{{ __('Status') }}</th><th>{{ __('Registered') }}</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($tenants as $t)
                        <tr>
                            <td><div class="fw-semibold">{{ $t->name }}</div><div class="small text-body-secondary">{{ $t->email }} · {{ $t->phone }}</div></td>
                            <td>{{ $t->city }}</td>
                            <td>
                                @if ($t->latestSubscription)
                                    {{ $t->latestSubscription->plan->name }} <x-status-badge :status="$t->latestSubscription->status" class="ms-1" />
                                @else
                                    <span class="text-body-secondary">—</span>
                                @endif
                            </td>
                            <td>{{ $t->users_count }}</td>
                            <td><x-status-badge :status="$t->status" /></td>
                            <td class="small text-body-secondary">{{ $t->created_at->format('d M Y') }}</td>
                            <td class="text-end"><a href="{{ route('admin.tenants.show', $t) }}" class="btn btn-sm btn-light">{{ __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="buildings" :title="__('No operators found')" :text="__('Try changing the filters.')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tenants->hasPages())<div class="card-body border-top">{{ $tenants->links() }}</div>@endif
    </div>
</x-layouts.panel>
