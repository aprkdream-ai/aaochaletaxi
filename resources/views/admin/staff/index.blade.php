<x-layouts.panel :title="__('Staff & roles')" panel="admin">
    <x-page-header :title="__('Platform staff')" :subtitle="__('People who can access the admin panel')">
        <a href="{{ route('admin.staff.create') }}" class="btn btn-primary"><x-icon name="person-plus" />{{ __('Add staff') }}</a>
    </x-page-header>
    <div class="card panel-card">
        <div class="table-responsive">
            <table class="table table-modern align-middle">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Roles') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last login') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($staff as $m)
                        <tr>
                            <td><div class="d-flex align-items-center gap-3"><span class="avatar">{{ mb_strtoupper(mb_substr($m->name, 0, 1)) }}</span><div><div class="fw-semibold">{{ $m->name }}</div><div class="small text-muted-2">{{ $m->email }}</div></div></div></td>
                            <td>{{ $m->type->label() }}</td>
                            <td class="small">{{ $m->type->value === 'super_admin' ? __('All permissions') : ($m->tenantRoles()->pluck('name')->join(', ') ?: '—') }}</td>
                            <td><span class="badge-soft {{ $m->status->value === 'active' ? 'ok' : 'danger' }}">{{ ucfirst($m->status->value) }}</span></td>
                            <td class="small text-muted-2">{{ $m->last_login_at?->diffForHumans() ?? '—' }}</td>
                            <td class="text-end">@if ($m->type->value !== 'super_admin' || auth()->user()->isSuperAdmin())<a href="{{ route('admin.staff.edit', $m) }}" class="btn btn-sm btn-light">{{ __('Edit') }}</a>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.panel>
