@props(['name', 'label', 'checked' => false, 'help' => null])
<div class="form-check form-switch mb-3">
    <input type="hidden" name="{{ $name }}" value="0">
    <input class="form-check-input" type="checkbox" role="switch" name="{{ $name }}" value="1" id="f_{{ $name }}" @checked(old($name, $checked)) {{ $attributes }}>
    <label class="form-check-label fw-semibold" for="f_{{ $name }}">{{ $label }}</label>
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
