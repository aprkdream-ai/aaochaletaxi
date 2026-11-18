<x-layouts.panel :title="__('Audit log')" panel="admin">
    <x-page-header :title="__('Audit log')" :subtitle="__('Every important action on the platform')" />
    <div class="card panel-card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2">
                <div class="col-md-3"><input type="text" name="event" value="{{ $filters['event'] ?? '' }}" class="form-control" placeholder="{{ __('Event, e.g. tenant.') }}" aria-label="{{ __('Event') }}"></div>
                <div class="col-md-3"><select name="tenant" class="form-select" aria-label="{{ __('Operator') }}"><option value="">{{ __('All operators') }}</option>@foreach ($tenants as $t)<option value="{{ $t->id }}" @selected((int) ($filters['tenant'] ?? 0) === $t->id)>{{ $t->name }}</option>@endforeach</select></div>
                <div class="col-6 col-md-2"><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control" aria-label="{{ __('From') }}"></div>
                <div class="col-6 col-md-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control" aria-label="{{ __('To') }}"></div>
                <div class="col-md-2"><button class="btn btn-ink w-100"><x-icon name="funnel" />{{ __('Filter') }}</button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-modern align-middle small">
                <thead><tr><th>{{ __('When') }}</th><th>{{ __('Event') }}</th><th>{{ __('By') }}</th><th>{{ __('Operator') }}</th><th>{{ __('Changes') }}</th><th>IP</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ $log->created_at->format('d M Y') }}<div class="text-muted-2">{{ $log->created_at->format('h:i:s A') }}</div></td>
                            <td><code>{{ $log->event }}</code>@if ($log->auditable_type)<div class="text-muted-2">{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}</div>@endif</td>
                            <td>{{ $log->user?->name ?? __('System') }}</td>
                            <td>@if ($log->tenant)<a href="{{ route('admin.tenants.show', $log->tenant) }}">{{ $log->tenant->name }}</a>@else — @endif</td>
                            <td style="max-width: 340px">
                                @if ($log->old_values || $log->new_values)
                                    <details><summary class="text-muted-2">{{ __('View') }}</summary>
                                        <pre class="small mb-0 mt-1 p-2 rounded bg-surface-2" style="white-space: pre-wrap">{{ json_encode(['old' => $log->old_values, 'new' => $log->new_values], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </details>
                                @endif
                            </td>
                            <td class="text-muted-2">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="journal-text" :title="__('No entries')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())<div class="card-body border-top">{{ $logs->links() }}</div>@endif
    </div>
</x-layouts.panel>
