@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'help' => null])
@php $id = $attributes->get('id', 'f_'.$name); $current = (string) old($name, $value instanceof \BackedEnum ? $value->value : $value); @endphp
<div class="mb-3">
    @if ($label)<label for="{{ $id }}" class="form-label">{{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif</label>@endif
    <select name="{{ $name }}" id="{{ $id }}" @if ($required) required @endif {{ $attributes->except('id')->class(['form-select', 'is-invalid' => $errors->has($name)]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $v => $l)
            <option value="{{ $v }}" @selected($current === (string) $v)>{{ $l }}</option>
        @endforeach
    </select>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
