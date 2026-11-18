<x-layouts.panel :title="$ticket->number" panel="admin">
    <x-page-header :title="$ticket->subject" :subtitle="$ticket->number.' · '.$ticket->category->label().' · '.__('opened :t', ['t' => $ticket->created_at->diffForHumans()])">
        <a href="{{ route('admin.support.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
    </x-page-header>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card panel-card mb-3"><div class="card-body"><x-ticket-thread :replies="$ticket->replies" staff-view /></div></div>
            <form method="POST" action="{{ route('admin.support.reply', $ticket) }}" class="card panel-card" data-loading>
                @csrf
                <div class="card-body">
                    <label class="form-label" for="body">{{ __('Your reply') }}</label>
                    <textarea name="body" id="body" rows="5" class="form-control @error('body') is-invalid @enderror" required maxlength="5000">{{ old('body') }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="internal" value="1" id="internal"><label class="form-check-label small" for="internal">{{ __('Internal note (not visible to operator)') }}</label></div>
                        <button class="btn btn-primary"><x-icon name="send" />{{ __('Send') }}</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="col-lg-4">
            <div class="card panel-card mb-3"><div class="card-header">{{ __('Ticket') }}</div><div class="card-body">
                <form method="POST" action="{{ route('admin.support.update', $ticket) }}">@csrf @method('PATCH')
                    <label class="form-label" for="status">{{ __('Status') }}</label>
                    <select name="status" id="status" class="form-select mb-3">@foreach (\App\Enums\TicketStatus::cases() as $s)<option value="{{ $s->value }}" @selected($ticket->status === $s)>{{ $s->label() }}</option>@endforeach</select>
                    <label class="form-label" for="assigned_to">{{ __('Assigned to') }}</label>
                    <select name="assigned_to" id="assigned_to" class="form-select mb-3"><option value="">{{ __('Unassigned') }}</option>@foreach ($staff as $s)<option value="{{ $s->id }}" @selected($ticket->assigned_to === $s->id)>{{ $s->name }}</option>@endforeach</select>
                    <button class="btn btn-ink w-100">{{ __('Update ticket') }}</button>
                </form>
                <div class="small text-muted-2 mt-3">{{ __('Priority') }}: <x-status-badge :status="$ticket->priority" /></div>
            </div></div>
            <div class="card panel-card"><div class="card-header">{{ __('Operator') }}</div><div class="card-body small">
                <a href="{{ route('admin.tenants.show', $ticket->tenant) }}" class="fw-semibold">{{ $ticket->tenant->name }}</a>
                <div class="text-muted-2 mt-1">{{ $ticket->creator?->name }}</div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <a href="tel:+91{{ $ticket->tenant->phone }}" class="btn btn-sm btn-light border"><x-icon name="telephone" />{{ $ticket->tenant->phone }}</a>
                    <a href="mailto:{{ $ticket->tenant->email }}" class="btn btn-sm btn-light border"><x-icon name="envelope" />{{ __('Email') }}</a>
                </div>
            </div></div>
        </div>
    </div>
</x-layouts.panel>
