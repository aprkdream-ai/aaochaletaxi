<x-layouts.panel :title="__('Staff')" panel="operator">
    <x-page-header :title="__('Staff')" :subtitle="__('People who can log in to your business dashboard')">
        <a href="{{ route('operator.roles.index') }}" class="btn btn-light border"><x-icon name="shield-lock" />{{ __('Roles') }}</a>
        <a href="{{ route('operator.staff.create') }}" class="btn btn-primary"><x-icon name="person-plus" />{{ __('Add staff') }}</a>
    </x-page-header>
    <div class="card panel-card"><div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Roles') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last login') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($staff as $m)
                    <tr>
                        <td><div class="d-flex align-items-center gap-3"><span class="avatar">{{ mb_strtoupper(mb_substr($m->name, 0, 1)) }}</span><div><div class="fw-semibold">{{ $m->name }} @if ($m->is(auth()->user()))<span class="badge-soft info">{{ __('You') }}</span>@endif</div><div class="small text-muted-2">{{ $m->email }}</div></div></div></td>
                        <td class="small">{{ $m->tenantRoles()->pluck('name')->join(', ') ?: '—' }}</td>
                        <td class="small">{{ $m->branch?->name ?? __('All') }}</td>
                        <td><span class="badge-soft {{ $m->status->value === 'active' ? 'ok' : 'danger' }}">{{ ucfirst($m->status->value) }}</span></td>
                        <td class="small text-muted-2">{{ $m->last_login_at?->diffForHumans() ?? __('never') }}</td>
                        <td class="text-end"><a href="{{ route('operator.staff.edit', $m) }}" class="btn btn-sm btn-light">{{ __('Edit') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div></div>
</x-layouts.panel>
