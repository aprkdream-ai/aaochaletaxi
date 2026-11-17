@php
    $messages = collect(['success' => 'check-circle-fill', 'error' => 'exclamation-octagon-fill', 'warning' => 'exclamation-triangle-fill', 'info' => 'info-circle-fill'])
        ->filter(fn ($icon, $key) => session()->has($key));
@endphp
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100">
    @foreach ($messages as $key => $icon)
        <div class="toast align-items-center border-0 text-bg-{{ $key === 'error' ? 'danger' : $key }}" role="status" aria-live="polite" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-{{ $icon }} me-2"></i>{{ session($key) }}</div>
                <button type="button" class="btn-close {{ in_array($key, ['warning', 'info']) ? '' : 'btn-close-white' }} me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    @endforeach
    @if ($errors->any() && ! ($hideErrors ?? false))
        <div class="toast align-items-center border-0 text-bg-danger" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-exclamation-octagon-fill me-2"></i>{{ __('Please fix the highlighted errors.') }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    @endif
</div>
