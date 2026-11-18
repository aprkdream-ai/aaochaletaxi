<x-layouts.panel :title="__('Settings')" panel="admin">
    <x-page-header :title="__('Platform settings')" :subtitle="__('Changes apply immediately and are recorded in the audit log')" />
    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card panel-card"><div class="list-group list-group-flush list-modern">
                @foreach ($groups as $key => $g)
                    <a href="{{ route('admin.settings.edit', ['group' => $key]) }}" @class(['list-group-item list-group-item-action d-flex align-items-center gap-2', 'active fw-semibold' => $group === $key]) @if ($group === $key) aria-current="page" @endif><x-icon :name="$g['icon']" />{{ $g['label'] }}</a>
                @endforeach
            </div></div>
        </div>
        <div class="col-lg-9">
            <form method="POST" action="{{ route('admin.settings.update') }}" class="card panel-card" data-loading novalidate>
                @csrf @method('PUT')
                <input type="hidden" name="group" value="{{ $group }}">
                <div class="card-header">{{ $groups[$group]['label'] }}</div>
                <div class="card-body">
                    @if ($group === 'integrations')
                        <div class="alert alert-info small d-flex gap-2"><x-icon name="shield-lock" class="mt-1" />{{ __('Secrets are encrypted at rest and never shown again. Leave a secret blank to keep the current value. All integrations are optional — features fall back gracefully when not configured.') }}</div>
                    @endif
                    <div class="row g-2">
                        @foreach ($groups[$group]['fields'] as $key => $field)
                            <div class="{{ $field['type'] === 'textarea' ? 'col-12' : 'col-md-6' }}">
                                @if ($field['type'] === 'plan')
                                    <div class="mb-3"><label class="form-label" for="f_{{ $key }}">{{ $field['label'] }}</label>
                                        <select name="{{ $key }}" id="f_{{ $key }}" class="form-select @error($key) is-invalid @enderror">
                                            @foreach ($plans as $p)<option value="{{ $p->code }}" @selected(old($key, $values[$key]) === $p->code)>{{ $p->name }}</option>@endforeach
                                        </select>@error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                @elseif ($field['type'] === 'textarea')
                                    <div class="mb-3"><label class="form-label" for="f_{{ $key }}">{{ $field['label'] }}</label>
                                        <textarea name="{{ $key }}" id="f_{{ $key }}" rows="3" class="form-control @error($key) is-invalid @enderror">{{ old($key, $values[$key]) }}</textarea>@error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                @elseif ($field['type'] === 'secret')
                                    @php $isSet = in_array($key, $secretsSet, true); @endphp
                                    <div class="mb-3"><label class="form-label d-flex justify-content-between" for="f_{{ $key }}">{{ $field['label'] }} <span class="badge-soft {{ $isSet ? 'ok' : 'muted' }}">{{ $isSet ? __('Configured') : __('Not set') }}</span></label>
                                        <input type="password" name="{{ $key }}" id="f_{{ $key }}" class="form-control @error($key) is-invalid @enderror" autocomplete="new-password" placeholder="{{ $isSet ? '••••••••  '.__('(unchanged)') : '' }}">
                                        @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        @if ($isSet)<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_{{ $key }}" value="1" id="clear_{{ $key }}"><label class="form-check-label small text-muted-2" for="clear_{{ $key }}">{{ __('Remove this secret') }}</label></div>@endif
                                    </div>
                                @else
                                    <x-input :name="$key" :type="$field['type']" :label="$field['label']" :value="$values[$key]" :step="$field['step'] ?? null" />
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="card-body border-top text-end"><button class="btn btn-primary"><x-icon name="check2" />{{ __('Save settings') }}</button></div>
            </form>
        </div>
    </div>
</x-layouts.panel>
