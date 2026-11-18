<x-layouts.panel :title="__('Support')" panel="operator">
    <x-page-header :title="__('Support')" :subtitle="__('We usually reply within a few working hours')">
        <a href="{{ route('operator.support.create') }}" class="btn btn-primary"><x-icon name="plus-lg" />{{ __('New ticket') }}</a>
    </x-page-header>
    <div class="card panel-card">
        <div class="list-group list-group-flush list-modern">
            @forelse ($tickets as $t)
                <a href="{{ route('operator.support.show', $t) }}" class="list-group-item list-group-item-action py-3 d-flex flex-wrap justify-content-between gap-2">
                    <div class="min-w-0"><div class="fw-semibold text-truncate">{{ $t->subject }}</div><div class="small text-muted-2">{{ $t->number }} · {{ $t->category->label() }} · {{ $t->creator?->name }}</div></div>
                    <div class="text-end"><x-status-badge :status="$t->status" /><div class="small text-muted-2 mt-1">{{ $t->last_reply_at?->diffForHumans() }}</div></div>
                </a>
            @empty
                <x-empty-state icon="life-preserver" :title="__('No tickets yet')" :text="__('Stuck somewhere or have a question? Our team is here to help.')">
                    <a href="{{ route('operator.support.create') }}" class="btn btn-primary mt-3">{{ __('Create a ticket') }}</a>
                </x-empty-state>
            @endforelse
        </div>
        @if ($tickets->hasPages())<div class="card-body border-top">{{ $tickets->links() }}</div>@endif
    </div>
</x-layouts.panel>
