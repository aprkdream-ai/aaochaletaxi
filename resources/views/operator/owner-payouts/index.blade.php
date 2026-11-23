@php use App\Support\Money; use App\Support\FleetOptions; $q = ['from' => $from->toDateString(), 'to' => $to->toDateString()]; @endphp
<x-layouts.panel :title="__('Owner payouts')" panel="operator">
    <x-page-header :title="__('Owner payouts')" :subtitle="__('Statements for attached and vendor vehicles')" />
    <form method="GET" class="card panel-card mb-3"><div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-3"><label class="form-label small" for="o_from">{{ __('From') }}</label><input type="date" name="from" id="o_from" value="{{ $q['from'] }}" class="form-control"></div>
        <div class="col-6 col-md-3"><label class="form-label small" for="o_to">{{ __('To') }}</label><input type="date" name="to" id="o_to" value="{{ $q['to'] }}" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-ink w-100"><x-icon name="funnel" />{{ __('Apply') }}</button></div>
    </div></form>
    <div class="card panel-card mb-3">
        <div class="table-responsive"><table class="table table-modern align-middle small">
            <thead><tr><th>{{ __('Vehicle / owner') }}</th><th>{{ __('Basis') }}</th><th class="text-end">{{ __('Trips') }}</th><th class="text-end">{{ __('Km') }}</th><th class="text-end">{{ __('Earned') }}</th><th class="text-end">{{ __('Expenses') }}</th><th class="text-end">{{ __('Paid') }}</th><th class="text-end">{{ __('Due') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td><span class="fw-semibold">{{ $r->vehicle->displayRegistration() }}</span><div class="text-muted-2">{{ $r->vehicle->owner_name ?: __('Owner not set') }}</div></td>
                        <td>{{ $r->rate_type ? FleetOptions::ownerRateTypes()[$r->rate_type] : '—' }}</td>
                        <td class="text-end">{{ $r->trips }}</td><td class="text-end">{{ number_format($r->km) }}</td>
                        <td class="text-end">{{ Money::format($r->earned, 0) }}</td><td class="text-end">{{ Money::format($r->expenses, 0) }}</td>
                        <td class="text-end">{{ Money::format($r->paid, 0) }}</td><td class="text-end fw-bold">{{ Money::format($r->due, 0) }}</td>
                        <td class="text-end"><a href="{{ route('operator.owner-payouts.show', [$r->vehicle] + $q) }}" class="btn btn-sm btn-light border">{{ __('Open') }}<x-icon name="chevron-right" /></a></td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-empty-state icon="person-vcard" :title="__('No attached vehicles')" :text="__('Set a vehicle’s ownership to “Attached” or “Vendor” and add the owner’s rate.')" /></td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
    @if ($recent->isNotEmpty())
        <div class="card panel-card"><div class="card-header">{{ __('Recent payouts') }}</div>
            <div class="list-group list-group-flush list-modern small">
                @foreach ($recent as $p)
                    <div class="list-group-item d-flex justify-content-between gap-2"><span><span class="fw-semibold">{{ $p->vehicle?->displayRegistration() }}</span> · {{ $p->vehicle?->owner_name }} · {{ $p->period_from->format('d M') }}–{{ $p->period_to->format('d M Y') }}</span><span class="fw-bold">{{ Money::format($p->amount) }}</span></div>
                @endforeach
            </div>
        </div>
    @endif
</x-layouts.panel>
