@php $editing = $role->exists; @endphp
<x-layouts.panel :title="$editing ? __('Edit role') : __('New role')" panel="operator">
    <x-page-header :title="$editing ? $role->name : __('New role')"><a href="{{ route('operator.roles.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a></x-page-header>
    <form method="POST" action="{{ $editing ? route('operator.roles.update', $role) : route('operator.roles.store') }}" data-loading novalidate x-data>
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-lg-4"><div class="card panel-card"><div class="card-body">
                <x-input name="name" :label="__('Role name')" :value="$role->name" required maxlength="100" placeholder="{{ __('e.g. Dispatcher') }}" />
                <x-textarea name="description" :label="__('Description')" :value="$role->description" rows="2" maxlength="255" />
                @error('permissions')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                <button class="btn btn-primary w-100">{{ $editing ? __('Save role') : __('Create role') }}</button>
            </div></div></div>
            <div class="col-lg-8"><div class="card panel-card"><div class="card-header">{{ __('Permissions') }}
                <span class="d-flex gap-2"><button type="button" class="btn btn-sm btn-light border" @click="$root.querySelectorAll('[name=\'permissions[]\']').forEach(c => c.checked = true)">{{ __('Select all') }}</button><button type="button" class="btn btn-sm btn-light border" @click="$root.querySelectorAll('[name=\'permissions[]\']').forEach(c => c.checked = false)">{{ __('Clear') }}</button></span>
            </div><div class="card-body"><div class="row g-3">
                @foreach ($permissions as $group => $items)
                    <div class="col-md-6"><div class="p-3 rounded-4 bg-surface-2 h-100">
                        <div class="small-caps text-muted-2 mb-2">{{ __($group) }}</div>
                        @foreach ($items as $p)
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $p->id }}" id="p{{ $p->id }}" @checked(in_array($p->id, old('permissions', $assigned)))><label class="form-check-label small" for="p{{ $p->id }}">{{ __($p->label) }}</label></div>
                        @endforeach
                    </div></div>
                @endforeach
            </div></div></div></div>
        </div>
    </form>
</x-layouts.panel>
