<x-layouts.panel :title="__('Support tickets')" panel="admin">
    <x-page-header :title="__('Support tickets')" :subtitle="__('Questions and issues raised by operators')" />
    <div class="card panel-card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2">
                <div class="col-md-5"><div class="input-icon"><x-icon name="search" /><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Ticket number or subject') }}" aria-label="{{ __('Search') }}"></div></div>
                <div class="col-6 col-md-3"><select name="status" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Status') }}"><option value="">{{ __('Open & awaiting') }}</option>@foreach (\App\Enums\TicketStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
                <div class="col-6 col-md-2"><label class="btn btn-light border w-100 mb-0"><input type="checkbox" class="form-check-input me-1" name="mine" value="1" onchange="this.form.submit()" @checked($filters['mine'] ?? false)>{{ __('Mine') }}</label></div>
                <div class="col-md-2"><button class="btn btn-ink w-100"><x-icon name="funnel" />{{ __('Filter') }}</button></div>
            </form>
        </div>
        <div class="list-group list-group-flush list-modern">
            @forelse ($tickets as $t)
                <a href="{{ route('admin.support.show', $t) }}" class="list-group-item list-group-item-action py-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate">{{ $t->subject }}</div>
                            <div class="small text-muted-2">{{ $t->number }} · {{ $t->tenant->name }} · {{ $t->category->label() }}@if ($t->assignee) · <x-icon name="person" /> {{ $t->assignee->name }}@endif</div>
                        </div>
                        <div class="text-end">
                            <x-status-badge :status="$t->status" /> <x-status-badge :status="$t->priority" />
                            <div class="small text-muted-2 mt-1">{{ $t->last_reply_at?->diffForHumans() }}</div>
                        </div>
                    </div>
                </a>
            @empty
                <x-empty-state icon="life-preserver" :title="__('No tickets here')" :text="__('Great — nothing waiting for a reply.')" />
            @endforelse
        </div>
        @if ($tickets->hasPages())<div class="card-body border-top">{{ $tickets->links() }}</div>@endif
    </div>
</x-layouts.panel>
