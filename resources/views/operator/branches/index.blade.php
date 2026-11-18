<x-layouts.panel :title="__('Branches')" panel="operator">
    <x-page-header :title="__('Branches')" :subtitle="__('Offices or cities you operate from')">
        <a href="{{ route('operator.branches.create') }}" class="btn btn-primary"><x-icon name="plus-lg" />{{ __('Add branch') }}</a>
    </x-page-header>
    @error('branch')<div class="alert alert-danger small">{{ $message }}</div>@enderror
    <div class="row g-3">
        @forelse ($branches as $b)
            <div class="col-md-6 col-xl-4">
                <div class="card panel-card h-100"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div><div class="fw-800 fs-5">{{ $b->name }}</div><div class="small text-muted-2"><code>{{ $b->code }}</code> · {{ $b->city }}</div></div>
                        <div class="d-flex gap-1">@if ($b->is_head_office)<span class="badge-soft warn">{{ __('Head office') }}</span>@endif<span class="badge-soft {{ $b->is_active ? 'ok' : 'muted' }}">{{ $b->is_active ? __('Active') : __('Inactive') }}</span></div>
                    </div>
                    @if ($b->address)<div class="small text-muted-2 mt-2">{{ $b->address }}</div>@endif
                    <div class="d-flex gap-3 small mt-3"><span><x-icon name="car-front" /> {{ $b->vehicles_count }}</span><span><x-icon name="person-badge" /> {{ $b->drivers_count }}</span><span><x-icon name="people" /> {{ $b->users_count }}</span></div>
                    <div class="d-flex gap-2 mt-3">
                        <a href="{{ route('operator.branches.edit', $b) }}" class="btn btn-sm btn-light border flex-fill"><x-icon name="pencil" />{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('operator.branches.destroy', $b) }}" data-confirm="{{ __('Delete this branch?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" aria-label="{{ __('Delete') }}"><x-icon name="trash" /></button></form>
                    </div>
                </div></div>
            </div>
        @empty
            <div class="col-12"><div class="card panel-card"><x-empty-state icon="diagram-3" :title="__('No branches yet')" :text="__('Branches are optional — add them if you operate from more than one office or city.')"><a href="{{ route('operator.branches.create') }}" class="btn btn-primary mt-3">{{ __('Add branch') }}</a></x-empty-state></div></div>
        @endforelse
    </div>
</x-layouts.panel>
