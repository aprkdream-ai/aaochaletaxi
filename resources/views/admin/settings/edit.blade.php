@php
    use App\Support\PlatformSettings;
    $current = $groups[$group];
    $testHints = [
        'razorpay' => [__('Checks your keys against the Razorpay API.'), null, null],
        'mail' => [__('Sends a test email using the saved settings.'), 'email', __('you@example.com')],
        'sms' => [__('Sends a test SMS using the saved provider.'), 'tel', '98XXXXXXXX'],
        'whatsapp' => [__('Sends your notification template (or Meta’s hello_world) to this number.'), 'tel', '98XXXXXXXX'],
        'maps' => [__('Requests the Mumbai → Pune road distance.'), null, null],
        'push' => [__('Authenticates with Google; add a device token to send a notification.'), 'text', __('FCM device token (optional)')],
    ];
@endphp
<x-layouts.panel :title="__('Settings')" panel="admin">
    <x-page-header :title="__('Platform settings')" :subtitle="__('Changes apply immediately and are recorded in the audit log')">
        <a href="{{ route('admin.messages.index') }}" class="btn btn-light border"><x-icon name="send-check" />{{ __('Message log') }}</a>
    </x-page-header>
    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card panel-card"><div class="list-group list-group-flush list-modern">
                @foreach ($groups as $key => $g)
                    <a href="{{ route('admin.settings.edit', ['group' => $key]) }}" @class(['list-group-item list-group-item-action d-flex align-items-center gap-2', 'active fw-semibold' => $group === $key]) @if ($group === $key) aria-current="page" @endif><x-icon :name="$g['icon']" />{{ $g['label'] }}</a>
                @endforeach
            </div></div>
        </div>
        <div class="col-lg-9">
            <form method="POST" action="{{ route('admin.settings.update') }}" class="card panel-card mb-3" data-loading novalidate>
                @csrf @method('PUT')
                <input type="hidden" name="group" value="{{ $group }}">
                <div class="card-header">{{ $current['label'] }}</div>
                <div class="card-body">
                    @if (isset($current['test']))
                        <div class="alert alert-info small d-flex gap-2"><x-icon name="shield-lock" class="mt-1" />{{ __('Secrets are encrypted at rest and never shown again — leave blank to keep the saved value. Integrations are optional; the platform keeps working when they are off.') }}</div>
                    @endif
                    <div class="row g-2">
                        @foreach ($current['fields'] as $key => $field)
                            @php $wide = in_array($field['type'], ['textarea', 'secret_textarea', 'toggle'], true); @endphp
                            <div class="{{ $wide ? 'col-12' : 'col-md-6' }}">
                                @switch($field['type'])
                                    @case('plan')
                                        <div class="mb-3"><label class="form-label" for="f_{{ $key }}">{{ $field['label'] }}</label>
                                            <select name="{{ $key }}" id="f_{{ $key }}" class="form-select @error($key) is-invalid @enderror">@foreach ($plans as $p)<option value="{{ $p->code }}" @selected(old($key, $values[$key]) === $p->code)>{{ $p->name }}</option>@endforeach</select>
                                            @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                        @break
                                    @case('select')
                                        <div class="mb-3"><label class="form-label" for="f_{{ $key }}">{{ $field['label'] }}</label>
                                            <select name="{{ $key }}" id="f_{{ $key }}" class="form-select @error($key) is-invalid @enderror">@foreach ($field['options'] as $v => $l)<option value="{{ $v }}" @selected((string) old($key, $values[$key]) === (string) $v)>{{ $l }}</option>@endforeach</select>
                                            @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            @isset($field['help'])<div class="form-text">{{ $field['help'] }}</div>@endisset</div>
                                        @break
                                    @case('toggle')
                                        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="{{ $key }}" value="1" id="f_{{ $key }}" @checked(old($key, $values[$key]))><label class="form-check-label fw-semibold" for="f_{{ $key }}">{{ $field['label'] }}</label></div>
                                        @break
                                    @case('textarea')
                                        <div class="mb-3"><label class="form-label" for="f_{{ $key }}">{{ $field['label'] }}</label>
                                            <textarea name="{{ $key }}" id="f_{{ $key }}" rows="3" class="form-control @error($key) is-invalid @enderror">{{ old($key, $values[$key]) }}</textarea>@error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                        @break
                                    @case('secret')
                                    @case('secret_textarea')
                                        @php $isSet = in_array($key, $secretsSet, true); @endphp
                                        <div class="mb-3"><label class="form-label d-flex justify-content-between" for="f_{{ $key }}">{{ $field['label'] }} <span class="badge-soft {{ $isSet ? 'ok' : 'muted' }}">{{ $isSet ? __('Configured') : __('Not set') }}</span></label>
                                            @if ($field['type'] === 'secret')
                                                <input type="password" name="{{ $key }}" id="f_{{ $key }}" class="form-control @error($key) is-invalid @enderror" autocomplete="new-password" placeholder="{{ $isSet ? '••••••••  '.__('(unchanged)') : '' }}">
                                            @else
                                                <textarea name="{{ $key }}" id="f_{{ $key }}" rows="4" class="form-control font-monospace small @error($key) is-invalid @enderror" autocomplete="off" spellcheck="false" placeholder="{{ $isSet ? __('(unchanged — paste new JSON to replace)') : '{ "type": "service_account", … }' }}"></textarea>
                                            @endif
                                            @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            @isset($field['help'])<div class="form-text">{{ $field['help'] }}</div>@endisset
                                            @if ($isSet)<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_{{ $key }}" value="1" id="clear_{{ $key }}"><label class="form-check-label small text-muted-2" for="clear_{{ $key }}">{{ __('Remove this secret') }}</label></div>@endif
                                        </div>
                                        @break
                                    @default
                                        <x-input :name="$key" :type="$field['type']" :label="$field['label']" :value="$values[$key]" :step="$field['step'] ?? null" :help="$field['help'] ?? null" />
                                @endswitch
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="card-body border-top text-end"><button class="btn btn-primary"><x-icon name="check2" />{{ __('Save settings') }}</button></div>
            </form>

            @if (isset($current['test']))
                @php [$hint, $inputType, $placeholder] = $testHints[$current['test']]; @endphp
                <form method="POST" action="{{ route('admin.settings.test', $current['test']) }}" class="card panel-card" data-loading>
                    @csrf
                    <div class="card-body d-flex flex-wrap align-items-center gap-3">
                        <span class="stat-ico tone-info"><x-icon name="lightning-charge" /></span>
                        <div class="me-auto"><div class="fw-semibold">{{ __('Test connection') }}</div><div class="small text-muted-2">{{ $hint }} {{ __('Save first — tests use saved settings.') }}</div></div>
                        @if ($inputType)<input type="{{ $inputType }}" name="to" class="form-control" style="max-width: 280px" placeholder="{{ $placeholder }}" aria-label="{{ __('Recipient') }}" @if ($inputType !== 'text') required @endif>@endif
                        <button class="btn btn-ink"><x-icon name="send" />{{ __('Send test') }}</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-layouts.panel>
