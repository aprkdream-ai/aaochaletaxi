@php $editing = $member->exists; @endphp
<x-layouts.panel :title="$editing ? __('Edit staff') : __('Add staff')" panel="operator">
    <x-page-header :title="$editing ? $member->name : __('Add staff member')"><a href="{{ route('operator.staff.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a></x-page-header>
    <form method="POST" action="{{ $editing ? route('operator.staff.update', $member) : route('operator.staff.store') }}" data-loading novalidate>
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-lg-7"><div class="card panel-card"><div class="card-header">{{ __('Login details') }}</div><div class="card-body"><div class="row g-2">
                <div class="col-12"><x-input name="name" icon="person" :label="__('Full name')" :value="$member->name" required /></div>
                <div class="col-md-6"><x-input name="email" type="email" icon="envelope" :label="__('Email (login)')" :value="$member->email" required autocomplete="off" /></div>
                <div class="col-md-6"><x-input name="phone" type="tel" icon="phone" :label="__('Mobile (optional, can also log in)')" :value="$member->phone" inputmode="numeric" /></div>
                <div class="col-md-6"><x-input name="password" type="password" icon="lock" :label="$editing ? __('New password (optional)') : __('Password')" :required="! $editing" autocomplete="new-password" :help="__('Min 8 characters with letters and numbers.')" /></div>
                <div class="col-md-6"><x-input name="password_confirmation" type="password" icon="lock" :label="__('Confirm password')" autocomplete="new-password" /></div>
                <div class="col-md-6"><x-select name="branch_id" :label="__('Branch')" :options="$branches->pluck('name', 'id')->all()" :value="$member->branch_id" :placeholder="__('All branches')" /></div>
                @if ($editing)<div class="col-md-6"><x-select name="status" :label="__('Status')" :options="['active' => __('Active'), 'inactive' => __('Inactive'), 'blocked' => __('Blocked')]" :value="$member->status" required /></div>@endif
            </div></div></div></div>
            <div class="col-lg-5"><div class="card panel-card"><div class="card-header">{{ __('Roles') }}</div><div class="card-body">
                @foreach ($roles as $role)
                    <div class="form-check p-3 border rounded-3 mb-2 ps-5">
                        <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="r{{ $role->id }}" @checked(in_array($role->id, old('roles', $assigned)))>
                        <label class="form-check-label" for="r{{ $role->id }}"><span class="fw-semibold">{{ $role->name }}</span> <span class="small text-muted-2">· {{ trans_choice('{1} :n permission|[2,*] :n permissions', $role->permissions_count, ['n' => $role->permissions_count]) }}</span>@if ($role->description)<span class="d-block small text-muted-2">{{ $role->description }}</span>@endif</label>
                    </div>
                @endforeach
                @error('roles')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                <button class="btn btn-primary w-100 mt-2">{{ $editing ? __('Save changes') : __('Add staff member') }}</button>
            </div></div></div>
        </div>
    </form>
</x-layouts.panel>
