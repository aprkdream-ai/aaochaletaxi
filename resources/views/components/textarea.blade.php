@props(['name', 'label' => null, 'value' => null, 'rows' => 3, 'required' => false])
@php $id = $attributes->get('id', 'f_'.$name); @endphp
<div class="mb-3">
    @if ($label)<label for="{{ $id }}" class="form-label">{{ $label }}@if ($required)<span class="text-danger" aria-hidden="true"> *</span>@endif</label>@endif
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" @if ($required) required @endif {{ $attributes->except('id')->class(['form-control', 'is-invalid' => $errors->has($name)]) }}>{{ old($name, $value) }}</textarea>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
