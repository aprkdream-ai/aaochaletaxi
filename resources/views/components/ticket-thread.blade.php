@props(['replies', 'staffView' => false])
<div class="d-grid gap-3">
    @foreach ($replies as $r)
        @php $mine = $staffView ? $r->from_staff : ! $r->from_staff; @endphp
        <div @class(['d-flex gap-3', 'flex-row-reverse text-end' => $mine])>
            <span class="avatar {{ $r->from_staff ? '' : 'bg-ink text-white' }}" style="{{ $r->from_staff ? '' : 'background: var(--ink); color: #fff' }}">{{ mb_strtoupper(mb_substr($r->author?->name ?? '?', 0, 1)) }}</span>
            <div class="flex-grow-1" style="max-width: 720px">
                <div class="small text-muted-2 mb-1">
                    <span class="fw-semibold text-body">{{ $r->from_staff && ! $staffView ? __('AaoChaleTaxi Support') : ($r->author?->name ?? __('Deleted user')) }}</span>
                    · {{ $r->created_at->format('d M Y, h:i A') }}
                    @if ($r->is_internal)<span class="badge-soft warn ms-1">{{ __('Internal note') }}</span>@endif
                </div>
                <div @class(['p-3 rounded-4 text-start', 'bg-surface-2' => ! $r->is_internal, 'border border-warning' => $r->is_internal]) style="white-space: pre-line; {{ $r->is_internal ? 'background: #fff8e6' : '' }}">{{ $r->body }}</div>
            </div>
        </div>
    @endforeach
</div>
