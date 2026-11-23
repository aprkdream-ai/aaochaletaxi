@php use App\Support\Money; use App\Support\FleetOptions; $q = ['from' => $from->toDateString(), 'to' => $to->toDateString()]; @endphp
<x-layouts.panel :title="__('Owner statement')" panel="operator">
    <x-page-header :title="$vehicle->displayRegistration().' · '.($vehicle->owner_name ?: __('Owner'))" :subtitle="$vehicle->owner_rate_type ? FleetOptions::ownerRateTypes()[$vehicle->owner_rate_type].': '.($vehicle->owner_rate_type === 'revenue_share' ? rtrim(rtrim((string) $vehicle->owner_rate, '0'), '.').'%' : Money::format($vehicle->owner_rate)) : __('Set the payment basis on the vehicle')">
        <a href="{{ route('operator.owner-payouts.index', $q) }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
        <a href="{{ route('operator.vehicles.edit', $vehicle) }}" class="btn btn-light border"><x-icon name="pencil" />{{ __('Owner details') }}</a>
    </x-page-header>
    <form method="GET" class="card panel-card mb-3"><div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-3"><label class="form-label small" for="s_from">{{ __('From') }}</label><input type="date" name="from" id="s_from" value="{{ $q['from'] }}" class="form-control"></div>
        <div class="col-6 col-md-3"><label class="form-label small" for="s_to">{{ __('To') }}</label><input type="date" name="to" id="s_to" value="{{ $q['to'] }}" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-ink w-100">{{ __('Apply') }}</button></div>
    </div></form>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card panel-card mb-3"><div class="list-group list-group-flush list-modern small">
                @foreach ([__('Completed trips') => $calc['trips'], __('Km') => number_format($calc['km']), __('Trip revenue (excl. GST)') => Money::format($calc['revenue']), __('Earned by owner') => Money::format($calc['earned']), __('Expenses paid by you (suggested deduction)') => Money::format($calc['expenses']), __('Already paid for this period') => Money::format($calc['paid'])] as $l => $v)
                    <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ $l }}</span><span class="fw-semibold">{{ $v }}</span></div>
                @endforeach
                <div class="list-group-item d-flex justify-content-between"><span class="fw-bold">{{ __('Due') }}</span><span class="fw-bold fs-5">{{ Money::format($calc['due']) }}</span></div>
            </div></div>
            @if ($calc['lines'])
                <div class="card panel-card"><div class="table-responsive"><table class="table table-modern small mb-0">
                    <thead><tr><th>{{ __('Trip') }}</th><th>{{ __('Date') }}</th><th class="text-end">{{ __('Km') }}</th><th class="text-end">{{ __('Fare') }}</th></tr></thead>
                    <tbody>@foreach ($calc['lines'] as $l)<tr><td>{{ $l['trip'] }}</td><td>{{ \Illuminate\Support\Carbon::parse($l['date'])->format('d M') }}</td><td class="text-end">{{ $l['km'] ?? '—' }}</td><td class="text-end">{{ (float) $l['fare'] ? Money::format($l['fare']) : '—' }}</td></tr>@endforeach</tbody>
                </table></div></div>
            @endif
        </div>
        <div class="col-lg-5">
            <form method="POST" action="{{ route('operator.owner-payouts.store', $vehicle) }}" class="card panel-card mb-3" data-loading>@csrf
                <div class="card-header">{{ __('Record payout') }}</div>
                <div class="card-body">
                    <input type="hidden" name="from" value="{{ $q['from'] }}"><input type="hidden" name="to" value="{{ min($q['to'], now()->toDateString()) }}">
                    <x-input name="deductions" type="number" step="0.01" min="0" :label="__('Deductions ₹')" :value="$calc['expenses']" />
                    <x-input name="amount" type="number" step="0.01" min="1" :label="__('Amount paid ₹')" :value="max(0, (float) $calc['due'])" required />
                    <x-select name="method" :label="__('Method')" :options="FleetOptions::payoutMethods()" required />
                    <x-input name="reference" :label="__('Reference / UTR')" maxlength="100" />
                    <x-input name="notes" :label="__('Notes')" maxlength="500" />
                    @if ($vehicle->owner_bank)<div class="small text-muted-2 mb-3">{{ __('Pay to: :b', ['b' => $vehicle->owner_bank]) }}</div>@endif
                    <button class="btn btn-primary w-100"><x-icon name="check2" />{{ __('Record payout') }}</button>
                </div>
            </form>
            <div class="card panel-card"><div class="card-header">{{ __('Payout history') }}</div>
                <div class="list-group list-group-flush list-modern small">
                    @forelse ($history as $p)
                        <div class="list-group-item"><div class="d-flex justify-content-between"><span>{{ $p->period_from->format('d M') }}–{{ $p->period_to->format('d M Y') }}</span><span class="fw-bold">{{ Money::format($p->amount) }}</span></div><div class="text-muted-2">{{ $p->paid_on->format('d M Y') }} · {{ FleetOptions::payoutMethods()[$p->method] ?? $p->method }}@if ($p->reference) · {{ $p->reference }}@endif · {{ $p->recorder?->name }}</div></div>
                    @empty
                        <x-empty-state icon="person-vcard" :title="__('No payouts yet')" />
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.panel>
