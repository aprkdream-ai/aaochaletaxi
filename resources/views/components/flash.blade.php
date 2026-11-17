@php
    $map = ['success' => ['check-circle-fill', 'success'], 'error' => ['exclamation-octagon-fill', 'danger'], 'warning' => ['exclamation-triangle-fill', 'warning'], 'info' => ['info-circle-fill', 'info']];
@endphp
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100">
    @foreach ($map as $key => [$icon, $tone])
        @if (session()->has($key))
            <div class="toast align-items-center text-bg-{{ $tone }}" role="status" aria-live="polite" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body d-flex gap-2 align-items-start fw-semibold"><x-icon :name="$icon" class="mt-1" /><span>{{ session($key) }}</span></div>
                    <button type="button" class="btn-close {{ in_array($tone, ['warning', 'info']) ? '' : 'btn-close-white' }} me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('Close') }}"></button>
                </div>
            </div>
        @endif
    @endforeach
    @if ($errors->any() && ! ($hideErrors ?? false))
        <div class="toast align-items-center text-bg-danger" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex gap-2 fw-semibold"><x-icon name="exclamation-octagon-fill" class="mt-1" />{{ __('Please fix the highlighted errors.') }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('Close') }}"></button>
            </div>
        </div>
    @endif
</div>
