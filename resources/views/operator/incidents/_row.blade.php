@php use App\Support\FleetOptions; use App\Support\Money; $can = auth()->user()->hasPermission('vehicles.manage'); @endphp
<div class="list-group-item" x-data="{ open: false }">
    <div class="d-flex flex-wrap align-items-center gap-3">
        <span class="date-tile"><b>{{ $i->occurred_on->format('d') }}</b><small>{{ $i->occurred_on->format('M') }}</small></span>
        <div class="me-auto min-w-0">
            <div class="fw-semibold">{{ FleetOptions::incidentTypes()[$i->type] ?? $i->type }}@if ($showVehicle) · <a href="{{ route('operator.vehicles.show', [$i->vehicle_id, 'tab' => 'incidents']) }}">{{ $i->vehicle?->displayRegistration() }}</a>@endif
                @if ($i->reference)<span class="text-muted-2 fw-normal">· {{ $i->reference }}</span>@endif</div>
            <div class="small text-muted-2 text-truncate" style="max-width:520px">{{ $i->description }}</div>
            <div class="small text-muted-2">@if ($i->driver){{ $i->driver->name }} · @endif @if ((float) $i->amount > 0){{ Money::format($i->amount, 0) }} · @endif {{ FleetOptions::claimStatuses()[$i->claim_status] }}@if ($i->settled_amount !== null) ({{ Money::format($i->settled_amount, 0) }})@endif
                @if ($i->expense)<span class="text-success"> · {{ __('Paid :a', ['a' => Money::format($i->expense->amount, 0)]) }}</span>@endif</div>
        </div>
        @if ($i->photo_path)<a href="{{ route('operator.incidents.photo', $i) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border" aria-label="{{ __('Photo') }}"><x-icon name="paperclip" /></a>@endif
        <span class="badge-soft {{ $i->status === 'open' ? 'warn' : 'muted' }}">{{ $i->status === 'open' ? __('Open') : __('Closed') }}</span>
        @if ($can && $i->status === 'open')<button type="button" class="btn btn-sm btn-light border" @click="open = !open"><x-icon name="pencil" />{{ __('Update') }}</button>@endif
    </div>
    @if ($can && $i->status === 'open')
        <div class="row g-2 mt-2" x-show="open" x-cloak>
            <form method="POST" action="{{ route('operator.incidents.claim', $i) }}" class="col-lg-6 row g-2 align-items-end m-0">@csrf
                <div class="col-6"><label class="form-label small" for="cs{{ $i->id }}">{{ __('Insurance claim') }}</label><select name="claim_status" id="cs{{ $i->id }}" class="form-select form-select-sm">@foreach (FleetOptions::claimStatuses() as $v => $l)<option value="{{ $v }}" @selected($i->claim_status === $v)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label small" for="cn{{ $i->id }}">{{ __('Claim no.') }}</label><input name="claim_number" id="cn{{ $i->id }}" value="{{ $i->claim_number }}" class="form-control form-control-sm" maxlength="60"></div>
                <div class="col-6"><label class="form-label small" for="ci{{ $i->id }}">{{ __('Insurer') }}</label><input name="insurer" id="ci{{ $i->id }}" value="{{ $i->insurer }}" class="form-control form-control-sm" maxlength="120"></div>
                <div class="col-4"><label class="form-label small" for="ca{{ $i->id }}">{{ __('Settled ₹') }}</label><input type="number" step="0.01" min="0" name="settled_amount" id="ca{{ $i->id }}" value="{{ $i->settled_amount }}" class="form-control form-control-sm"></div>
                <div class="col-2"><button class="btn btn-sm btn-ink w-100" aria-label="{{ __('Save claim') }}"><x-icon name="check2" /></button></div>
            </form>
            <div class="col-lg-6">
                @unless ($i->expense_id)
                    <form method="POST" action="{{ route('operator.incidents.pay', $i) }}" class="row g-2 align-items-end">@csrf
                        <div class="col-4"><label class="form-label small" for="pa{{ $i->id }}">{{ __('Paid ₹') }}</label><input type="number" step="0.01" min="1" name="amount" id="pa{{ $i->id }}" value="{{ (float) $i->amount ?: '' }}" class="form-control form-control-sm" required></div>
                        <div class="col-4"><label class="form-label small" for="pd{{ $i->id }}">{{ __('On') }}</label><input type="date" name="paid_on" id="pd{{ $i->id }}" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" required></div>
                        <div class="col-4"><button class="btn btn-sm btn-primary w-100">{{ __('Record payment') }}</button></div>
                    </form>
                @endunless
                <form method="POST" action="{{ route('operator.incidents.close', $i) }}" class="mt-2 text-end" data-confirm="{{ __('Close this incident?') }}">@csrf<button class="btn btn-sm btn-light border">{{ __('Close incident') }}</button></form>
            </div>
        </div>
    @endif
</div>
