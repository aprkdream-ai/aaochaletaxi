@php $editing = $member->exists; @endphp
<x-layouts.panel :title="$editing ? __('Edit staff') : __('Add staff')" panel="admin">
    <x-page-header :title="$editing ? $member->name : __('Add staff member')">
        <a href="{{ route('admin.staff.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
    </x-page-header>
    <form method="POST" action="{{ $editing ? route('admin.staff.update', $member) : route('admin.staff.store') }}" data-loading novalidate>
        @csrf @if ($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card panel-card"><div class="card-header">{{ __('Account') }}</div><div class="card-body">
                    <div class="row g-2">
                        <div class="col-12"><x-input name="name" icon="person" :label="__('Full name')" :value="$member->name" required /></div>
                        <div class="col-md-6"><x-input name="email" type="email" icon="envelope" :label="__('Email (login)')" :value="$member->email" required autocomplete="off" /></div>
                        <div class="col-md-6"><x-input name="phone" type="tel" icon="phone" :label="__('Mobile')" :value="$member->phone" inputmode="numeric" /></div>
                        <div class="col-md-6"><x-input name="password" type="password" icon="lock" :label="$editing ? __('New password (optional)') : __('Password')" :required="! $editing" autocomplete="new-password" :help="__('Min 10 characters with letters and numbers.')" /></div>
                        <div class="col-md-6"><x-input name="password_confirmation" type="password" icon="lock" :label="__('Confirm password')" autocomplete="new-password" /></div>
                        <div class="col-md-6 mb-3"><label class="form-label" for="status">{{ __('Status') }}</label>
                            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror">
                                @foreach (\App\Enums\UserStatus::cases() as $s)<option value="{{ $s->value }}" @selected(old('status', $member->status?->value ?? 'active') === $s->value)>{{ ucfirst($s->value) }}</option>@endforeach
                            </select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div></div>
            </div>
            <div class="col-lg-5">
                <div class="card panel-card"><div class="card-header">{{ __('Roles') }}</div><div class="card-body">
                    @if ($member->type?->value === 'super_admin')
                        <p class="text-muted-2 small mb-0">{{ __('Super admins have every permission.') }}</p>
                    @else
                        @foreach ($roles as $role)
                            <div class="form-check p-3 border rounded-3 mb-2 ps-5">
                                <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="role{{ $role->id }}" @checked(in_array($role->id, old('roles', $assigned)))>
                                <label class="form-check-label" for="role{{ $role->id }}"><span class="fw-semibold">{{ $role->name }}</span><span class="d-block small text-muted-2">{{ $role->permissions->pluck('label')->join(' · ') }}</span></label>
                            </div>
                        @endforeach
                    @endif
                    <button class="btn btn-primary w-100 mt-2">{{ $editing ? __('Save changes') : __('Create staff member') }}</button>
                </div></div>
            </div>
        </div>
    </form>
</x-layouts.panel>
