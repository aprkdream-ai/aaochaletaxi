<x-layouts.panel :title="$ticket->number" panel="operator">
    <x-page-header :title="$ticket->subject" :subtitle="$ticket->number.' · '.$ticket->category->label()">
        <x-status-badge :status="$ticket->status" />
        <a href="{{ route('operator.support.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
    </x-page-header>
    <div style="max-width: 900px">
        <div class="card panel-card mb-3"><div class="card-body"><x-ticket-thread :replies="$ticket->publicReplies" /></div></div>
        @if ($ticket->status->value !== 'closed')
            <form method="POST" action="{{ route('operator.support.reply', $ticket) }}" class="card panel-card mb-3" data-loading>
                @csrf
                <div class="card-body">
                    <label class="form-label" for="body">{{ __('Reply') }}</label>
                    <textarea name="body" id="body" rows="4" class="form-control @error('body') is-invalid @enderror" required maxlength="5000">{{ old('body') }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="text-end mt-3"><button class="btn btn-primary"><x-icon name="send" />{{ __('Send reply') }}</button></div>
                </div>
            </form>
            <form method="POST" action="{{ route('operator.support.close', $ticket) }}" data-confirm="{{ __('Close this ticket?') }}">@csrf
                <button class="btn btn-sm btn-light border"><x-icon name="check2-all" />{{ __('Mark as solved & close') }}</button>
            </form>
        @else
            <div class="alert alert-secondary small">{{ __('This ticket is closed.') }} <a href="{{ route('operator.support.create') }}">{{ __('Open a new ticket') }}</a></div>
        @endif
    </div>
</x-layouts.panel>
