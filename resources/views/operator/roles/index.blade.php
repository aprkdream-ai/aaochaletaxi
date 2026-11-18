<x-layouts.panel :title="__('Roles & access')" panel="operator">
    <x-page-header :title="__('Roles & access')" :subtitle="__('Control what each staff member can see and do')">
        <a href="{{ route('operator.roles.create') }}" class="btn btn-primary"><x-icon name="plus-lg" />{{ __('New role') }}</a>
    </x-page-header>
    @error('role')<div class="alert alert-danger small">{{ $message }}</div>@enderror
    <div class="row g-3">
        @foreach ($roles as $role)
            <div class="col-md-6 col-xl-4"><div class="card panel-card h-100"><div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start"><div class="fw-800 fs-5">{{ $role->name }}</div>@if ($role->is_system)<span class="badge-soft muted">{{ __('Built-in') }}</span>@endif</div>
                <div class="small text-muted-2 mb-3">{{ $role->description }}</div>
                <div class="d-flex gap-3 small mb-3"><span><x-icon name="key" /> {{ $role->permissions_count }} {{ __('permissions') }}</span><span><x-icon name="people" /> {{ $role->users_count }} {{ __('staff') }}</span></div>
                <div class="mt-auto d-flex gap-2">
                    @if ($role->slug === 'owner')
                        <span class="small text-muted-2"><x-icon name="lock" /> {{ __('Full access, cannot be changed') }}</span>
                    @else
                        <a href="{{ route('operator.roles.edit', $role) }}" class="btn btn-sm btn-light border flex-fill"><x-icon name="pencil" />{{ __('Edit') }}</a>
                        @if (! $role->is_system && $role->users_count === 0)<form method="POST" action="{{ route('operator.roles.destroy', $role) }}" data-confirm="{{ __('Delete this role?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" aria-label="{{ __('Delete') }}"><x-icon name="trash" /></button></form>@endif
                    @endif
                </div>
            </div></div></div>
        @endforeach
    </div>
</x-layouts.panel>
