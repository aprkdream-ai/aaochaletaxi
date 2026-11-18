@props(['id', 'title', 'action', 'submit' => __('Save'), 'method' => 'POST', 'danger' => false, 'size' => null])
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true">
    <div @class(['modal-dialog modal-dialog-centered modal-fullscreen-sm-down', 'modal-lg' => $size === 'lg'])>
        <form method="POST" action="{{ $action }}" class="modal-content border-0 rounded-4" data-loading>
            @csrf
            @if (strtoupper($method) !== 'POST') @method($method) @endif
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title h5" id="{{ $id }}Label">{{ $title }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">{{ $slot }}</div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn {{ $danger ? 'btn-danger' : 'btn-primary' }}">{{ $submit }}</button>
            </div>
        </form>
    </div>
</div>
