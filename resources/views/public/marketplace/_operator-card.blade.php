@php $t = $tenant; $s = $stats; @endphp
<div class="card panel-card h-100"><div class="card-body d-flex flex-column">
    <div class="d-flex align-items-center gap-3 mb-2">
        @if ($t->logoUrl())<img src="{{ $t->logoUrl() }}" alt="" class="rounded-3 bg-white border" style="width:48px;height:48px;object-fit:contain" loading="lazy">@else<span class="avatar" style="width:48px;height:48px">{{ mb_strtoupper(mb_substr($t->name, 0, 2)) }}</span>@endif
        <div class="min-w-0"><div class="fw-bold text-truncate">{{ $t->name }}</div>
            <div class="small text-muted-2 d-flex flex-wrap gap-2">
                @if ($t->kyc_status->value === 'verified')<span class="text-success"><x-icon name="patch-check-fill" /> {{ __('Verified') }}</span>@endif
                @if ($s['reviews'])<span><x-icon name="star-fill" class="text-brand" /> {{ $s['rating'] }} ({{ $s['reviews'] }})</span>@endif
                @if ($s['completed'])<span>{{ trans_choice('{1} 1 trip|[2,*] :n trips', $s['completed'], ['n' => $s['completed']]) }}</span>@endif
            </div></div>
    </div>
    {{ $slot ?? '' }}
</div></div>
