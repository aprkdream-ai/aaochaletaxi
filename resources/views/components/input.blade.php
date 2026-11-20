@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'help' => null, 'required' => false, 'icon' => null])
@php
    $id = $attributes->get('id', 'f_'.$name);
    $isPassword = $type === 'password';
    $describedBy = trim(($errors->has($name) ? $id.'_err ' : '').($help ? $id.'_help' : ''));
@endphp
<div class="mb-3">
    @if ($label)
        @isset($aside)
            <div class="d-flex justify-content-between align-items-baseline gap-2"><label for="{{ $id }}" class="form-label">{{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif</label>{{ $aside }}</div>
        @else
            <label for="{{ $id }}" class="form-label">{{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif</label>
        @endisset
    @endif
    <div @class(['position-relative', 'input-icon' => $icon]) @if ($isPassword) x-data="{ show: false }" @endif>
        @if ($icon)<x-icon :name="$icon" />@endif
        <input name="{{ $name }}" id="{{ $id }}"
            @if ($isPassword) :type="show ? 'text' : 'password'" type="password" @else type="{{ $type }}" value="{{ old($name, $value) }}" @endif
            @if ($required) required aria-required="true" @endif
            @if ($errors->has($name)) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except('id')->class(['form-control', 'is-invalid' => $errors->has($name), 'pe-5' => $isPassword]) }}>
        @if ($isPassword)
            <button type="button" class="pw-toggle" @click="show = !show" :aria-label="show ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'" aria-label="{{ __('Show password') }}">
                <span x-show="!show"><x-icon name="eye" /></span><span x-show="show" x-cloak><x-icon name="eye-slash" /></span>
            </button>
        @endif
    </div>
    @error($name)<div class="invalid-feedback d-block" id="{{ $id }}_err">{{ $message }}</div>@enderror
    @if ($help)<div class="form-text" id="{{ $id }}_help">{{ $help }}</div>@endif
</div>
