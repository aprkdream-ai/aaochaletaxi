<x-layouts.panel :title="__('Message log')" panel="admin">
    <x-page-header :title="__('Message log')" :subtitle="trans_choice('{0} No failures in the last 24 hours|{1} :n failure in the last 24 hours|[2,*] :n failures in the last 24 hours', $failed24h, ['n' => $failed24h])">
        <a href="{{ route('admin.settings.edit', ['group' => 'email']) }}" class="btn btn-light border"><x-icon name="gear" />{{ __('Channel settings') }}</a>
    </x-page-header>
    <div class="card panel-card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2">
                <div class="col-md-5"><div class="input-icon"><x-icon name="search" /><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Recipient') }}" aria-label="{{ __('Recipient') }}"></div></div>
                <div class="col-6 col-md-3"><select name="channel" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Channel') }}"><option value="">{{ __('All channels') }}</option>@foreach (['mail' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'push' => 'Push'] as $v => $l)<option value="{{ $v }}" @selected(($filters['channel'] ?? '') === $v)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-6 col-md-2"><select name="status" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Status') }}"><option value="">{{ __('Any status') }}</option>@foreach (['sent', 'failed', 'skipped'] as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
                <div class="col-md-2"><button class="btn btn-ink w-100"><x-icon name="funnel" />{{ __('Filter') }}</button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-modern align-middle small">
                <thead><tr><th>{{ __('When') }}</th><th>{{ __('Channel') }}</th><th>{{ __('Recipient') }}</th><th>{{ __('Message') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ $log->created_at->format('d M, h:i A') }}</td>
                            <td><span class="d-inline-flex align-items-center gap-1"><x-icon :name="['mail' => 'envelope', 'sms' => 'chat-left-text', 'whatsapp' => 'whatsapp', 'push' => 'bell'][$log->channel] ?? 'send'" />{{ ucfirst($log->channel) }}</span><div class="text-muted-2">{{ $log->provider }}</div></td>
                            <td class="text-break" style="max-width: 200px">{{ $log->recipient }}</td>
                            <td style="max-width: 360px"><div class="text-truncate">{{ $log->subject ?: $log->body }}</div>@if ($log->context)<div class="text-muted-2">{{ $log->context }}</div>@endif</td>
                            <td><span class="badge-soft {{ ['sent' => 'ok', 'failed' => 'danger'][$log->status] ?? 'muted' }}">{{ ucfirst($log->status) }}</span>@if ($log->error)<div class="text-danger mt-1 text-break" style="max-width: 260px">{{ \Illuminate\Support\Str::limit($log->error, 140) }}</div>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="send-check" :title="__('No messages yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())<div class="card-body border-top">{{ $logs->links() }}</div>@endif
    </div>
</x-layouts.panel>
