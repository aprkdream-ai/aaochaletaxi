<x-layouts.panel :title="__('Website enquiries')" panel="admin">
    <x-page-header :title="__('Website enquiries')" :subtitle="trans_choice('{0} No open enquiries|{1} :n open enquiry|[2,*] :n open enquiries', $openCount, ['n' => $openCount])" />

    <div class="card panel-card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2" role="search">
                <div class="col-md-5"><div class="input-icon"><x-icon name="search" /><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Name, phone, email or message') }}" aria-label="{{ __('Search') }}"></div></div>
                <div class="col-6 col-md-3">
                    <select name="type" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Type') }}">
                        <option value="">{{ __('All types') }}</option>
                        @foreach (['enquiry' => __('Fare enquiry'), 'contact' => __('Contact form'), 'partner' => __('Partner / operator')] as $v => $l)
                            <option value="{{ $v }}" @selected(($filters['type'] ?? '') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="state" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Status') }}">
                        <option value="open" @selected($filters['state'] === 'open')>{{ __('Open') }}</option>
                        <option value="handled" @selected($filters['state'] === 'handled')>{{ __('Handled') }}</option>
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-ink w-100"><x-icon name="funnel" />{{ __('Filter') }}</button></div>
            </form>
        </div>
        <div class="list-group list-group-flush list-modern">
            @forelse ($messages as $m)
                <div class="list-group-item py-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge-soft {{ ['enquiry' => 'warn', 'partner' => 'info'][$m->type] ?? 'muted' }}">{{ ['enquiry' => __('Fare enquiry'), 'partner' => __('Partner'), 'contact' => __('Contact')][$m->type] ?? $m->type }}</span>
                            <span class="fw-bold">{{ $m->name }}</span>
                            @if ($m->subject)<span class="text-muted-2 small">· {{ $m->subject }}</span>@endif
                        </div>
                        <span class="small text-muted-2" title="{{ $m->created_at->format('d M Y, h:i A') }}">{{ $m->created_at->diffForHumans() }}</span>
                    </div>
                    <div class="small mb-2" style="white-space: pre-line">{{ $m->message }}</div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <a href="tel:+91{{ $m->phone }}" class="btn btn-sm btn-light border"><x-icon name="telephone" />{{ $m->phone }}</a>
                        <a href="https://wa.me/91{{ $m->phone }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><x-icon name="whatsapp" />WhatsApp</a>
                        @if ($m->email)<a href="mailto:{{ $m->email }}" class="btn btn-sm btn-light border"><x-icon name="envelope" />{{ $m->email }}</a>@endif
                        <form method="POST" action="{{ route('admin.enquiries.update', $m) }}" class="ms-auto d-flex gap-2">@csrf @method('PATCH')
                            <input type="hidden" name="handled" value="{{ $m->handled_at ? 0 : 1 }}">
                            <button class="btn btn-sm {{ $m->handled_at ? 'btn-light border' : 'btn-primary' }}"><x-icon :name="$m->handled_at ? 'arrow-counterclockwise' : 'check2'" />{{ $m->handled_at ? __('Reopen') : __('Mark handled') }}</button>
                        </form>
                    </div>
                    @if ($m->handled_at)<div class="small text-muted-2 mt-2">{{ __('Handled by :name, :when', ['name' => $m->handler?->name ?? '—', 'when' => $m->handled_at->diffForHumans()]) }}</div>@endif
                </div>
            @empty
                <x-empty-state icon="inbox" :title="__('Inbox zero')" :text="__('No enquiries match these filters.')" />
            @endforelse
        </div>
        @if ($messages->hasPages())<div class="card-body border-top">{{ $messages->links() }}</div>@endif
    </div>
</x-layouts.panel>
