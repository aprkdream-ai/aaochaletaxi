@php use App\Support\Money; $q = ['from' => $from->toDateString(), 'to' => $to->toDateString()]; @endphp
<x-layouts.panel :title="__('Reports')" panel="operator">
    <x-page-header :title="__('Reports')" :subtitle="$from->format('d M Y').' – '.$to->format('d M Y')">
        <form method="GET" class="d-flex flex-nowrap gap-2 align-items-center">
            <input type="hidden" name="tab" value="{{ $tab === 'locked' ? 'summary' : $tab }}">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm w-auto" aria-label="{{ __('From') }}"><span>–</span>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm w-auto" aria-label="{{ __('To') }}">
            <button class="btn btn-sm btn-ink">{{ __('Apply') }}</button>
        </form>
        @if ($tab !== 'locked')<a href="{{ route('operator.reports.export', $q + ['tab' => $tab]) }}" class="btn btn-sm btn-light border"><x-icon name="filetype-csv" />{{ __('Export CSV') }}</a>@endif
    </x-page-header>
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach (['summary' => __('Summary'), 'vehicles' => __('Vehicle-wise'), 'bookings' => __('Booking-wise'), 'gst' => __('GST')] as $k => $l)
            <a href="{{ route('operator.reports.index', $q + ['tab' => $k]) }}" @class(['btn btn-sm rounded-pill', 'btn-ink' => $tab === $k || ($tab === 'locked' && false), 'btn-light border' => $tab !== $k])>{{ $l }}@if (in_array($k, ['vehicles', 'bookings']) && ! $advanced) <x-icon name="lock-fill" />@endif</a>
        @endforeach
        <div class="ms-auto d-flex gap-1">
            @foreach (['This month' => [now()->startOfMonth(), now()], 'Last month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()], 'This FY' => [now()->month >= 4 ? now()->startOfYear()->addMonths(3) : now()->subYear()->startOfYear()->addMonths(3), now()]] as $l => [$a, $b])
                <a href="{{ route('operator.reports.index', ['tab' => $tab === 'locked' ? 'summary' : $tab, 'from' => $a->toDateString(), 'to' => $b->toDateString()]) }}" class="btn btn-sm btn-light border">{{ __($l) }}</a>
            @endforeach
        </div>
    </div>

    @if ($tab === 'locked')
        <div class="card panel-card"><x-empty-state icon="lock" :title="__('Advanced reports are part of the Growth and Business plans')" :text="__('See profit per vehicle and per booking to grow faster.')">
            @can('subscription.manage')<a href="{{ route('operator.subscription') }}" class="btn btn-primary mt-3">{{ __('Upgrade plan') }}</a>@endcan
        </x-empty-state></div>
    @elseif ($summary)
        @php $s = $summary; @endphp
        <div class="row g-3 mb-3">
            <div class="col-6 col-xl-3"><x-stat-card :label="__('Revenue (excl. GST)')" :value="Money::format($s['revenue'], 0)" icon="graph-up-arrow" color="primary" :hint="trans_choice('{0} no trips|{1} 1 trip|[2,*] :n trips', $s['completed'], ['n' => $s['completed']])" /></div>
            <div class="col-6 col-xl-3"><x-stat-card :label="__('Collected')" :value="Money::format($s['collections'], 0)" icon="cash-stack" color="success" :hint="(float) $s['refunds'] ? __('Refunds :r', ['r' => Money::format($s['refunds'], 0)]) : null" /></div>
            <div class="col-6 col-xl-3"><x-stat-card :label="__('Costs')" :value="Money::format($s['costs'], 0)" icon="wallet2" color="warning" /></div>
            <div class="col-6 col-xl-3"><x-stat-card :label="__('Operating profit')" :value="Money::format($s['profit'], 0)" icon="piggy-bank" :color="(float) $s['profit'] >= 0 ? 'success' : 'danger'" /></div>
        </div>
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Daily revenue & collections') }}</div><div class="card-body">
                    @php $max = max(1, collect($daily)->max(fn ($d) => max((float) $d['revenue'], (float) $d['collections']))); @endphp
                    <div class="d-flex align-items-end gap-1" style="height: 180px" role="img" aria-label="{{ __('Daily revenue chart') }}">
                        @foreach ($daily as $day => $d)
                            <div class="flex-fill d-flex align-items-end gap-0 h-100" title="{{ \Illuminate\Support\Carbon::parse($day)->format('d M') }} · {{ __('Revenue') }} {{ Money::format($d['revenue'], 0) }} · {{ __('Collected') }} {{ Money::format($d['collections'], 0) }}">
                                <div class="flex-fill rounded-top" style="background: var(--brand); height: {{ max(1, (float) $d['revenue'] / $max * 100) }}%"></div>
                                <div class="flex-fill rounded-top" style="background: #12a150; opacity: .7; height: {{ max(1, (float) $d['collections'] / $max * 100) }}%"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="d-flex justify-content-between small text-muted-2 mt-2"><span>{{ $from->format('d M') }}</span><span><span class="d-inline-block rounded" style="width:10px;height:10px;background:var(--brand)"></span> {{ __('Revenue') }} &nbsp; <span class="d-inline-block rounded" style="width:10px;height:10px;background:#12a150"></span> {{ __('Collected') }}</span><span>{{ $to->format('d M') }}</span></div>
                </div></div>
            </div>
            <div class="col-lg-4">
                <div class="card panel-card mb-3"><div class="card-header">{{ __('Breakdown') }}</div><div class="list-group list-group-flush list-modern small">
                    @foreach ([__('Billed to customers (incl. GST)') => $s['billed'], __('GST collected') => $s['gst'], __('Reimbursements (tolls, parking)') => $s['reimbursements'], __('Vehicle expenses') => $s['vehicle_expenses'], __('Maintenance') => $s['maintenance'], __('Business expenses') => $s['general_expenses'], __('Driver payouts') => $s['driver_payouts']] as $l => $v)
                        <div class="list-group-item d-flex justify-content-between"><span>{{ $l }}</span><b>{{ Money::format($v, 0) }}</b></div>
                    @endforeach
                    <div class="list-group-item d-flex justify-content-between text-danger"><span>{{ __('Outstanding (all time)') }} · {{ $s['outstanding_count'] }}</span><b>{{ Money::format($s['outstanding'], 0) }}</b></div>
                </div></div>
                <div class="card panel-card"><div class="card-header">{{ __('Collections by method') }}</div><div class="list-group list-group-flush list-modern small">
                    @forelse ($s['by_method'] as $m => $v)<div class="list-group-item d-flex justify-content-between"><span class="text-uppercase">{{ str_replace('_', ' ', $m) }}</span><b>{{ Money::format($v, 0) }}</b></div>
                    @empty<div class="list-group-item text-muted-2">{{ __('No payments in this period') }}</div>@endforelse
                </div></div>
            </div>
        </div>
        <div class="small text-muted-2 mt-2">{{ __(':c bookings created · :x cancelled in this period.', ['c' => $s['bookings_created'], 'x' => $s['cancelled']]) }}</div>
    @elseif ($vehicles)
        <div class="card panel-card"><div class="table-responsive"><table class="table table-modern align-middle small">
            <thead><tr><th>{{ __('Vehicle') }}</th><th class="text-end">{{ __('Trips') }}</th><th class="text-end">{{ __('Km') }}</th><th class="text-end">{{ __('Revenue') }}</th><th class="text-end">{{ __('₹/km') }}</th><th class="text-end">{{ __('Expenses') }}</th><th class="text-end">{{ __('Maintenance') }}</th><th class="text-end">{{ __('Profit') }}</th></tr></thead>
            <tbody>
                @forelse ($vehicles as $r)
                    <tr><td><a href="{{ route('operator.vehicles.show', $r->vehicle) }}" class="fw-semibold">{{ $r->vehicle->displayRegistration() }}</a><div class="text-muted-2">{{ $r->vehicle->category?->name }}</div></td><td class="text-end">{{ $r->trips }}</td><td class="text-end">{{ number_format($r->km) }}</td><td class="text-end">{{ Money::format($r->revenue, 0) }}</td><td class="text-end">{{ $r->per_km ? '₹'.$r->per_km : '—' }}</td><td class="text-end">{{ Money::format($r->expenses, 0) }}</td><td class="text-end">{{ Money::format($r->maintenance, 0) }}</td><td class="text-end fw-bold {{ (float) $r->profit < 0 ? 'text-danger' : 'text-success' }}">{{ Money::format($r->profit, 0) }}</td></tr>
                @empty<tr><td colspan="8"><x-empty-state icon="car-front" :title="__('No vehicle activity in this period')" /></td></tr>@endforelse
            </tbody>
        </table></div></div>
    @elseif ($bookings)
        <div class="card panel-card"><div class="table-responsive"><table class="table table-modern align-middle small">
            <thead><tr><th>{{ __('Booking') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Vehicle / driver') }}</th><th class="text-end">{{ __('Revenue') }}</th><th class="text-end">{{ __('Allowance') }}</th><th class="text-end">GST</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Balance') }}</th></tr></thead>
            <tbody>
                @forelse ($bookings as $r)
                    <tr><td><a href="{{ route('operator.bookings.show', $r->booking) }}" class="fw-semibold">{{ $r->booking->booking_number }}</a><div class="text-muted-2">{{ $r->booking->trip->completed_at->format('d M') }} · {{ $r->booking->type->label() }}</div></td>
                        <td>{{ $r->booking->customer_name }}</td><td>{{ $r->booking->trip->vehicle?->displayRegistration() }}<div class="text-muted-2">{{ $r->booking->trip->driver?->name }}</div></td>
                        <td class="text-end">{{ Money::format($r->revenue, 0) }}</td><td class="text-end">{{ Money::format($r->allowance, 0) }}</td><td class="text-end">{{ Money::format($r->gst, 0) }}</td><td class="text-end fw-semibold">{{ Money::format($r->total, 0) }}</td><td class="text-end {{ (float) $r->balance > 0 ? 'text-danger fw-bold' : 'text-muted-2' }}">{{ Money::format($r->balance, 0) }}</td></tr>
                @empty<tr><td colspan="8"><x-empty-state icon="journal-bookmark" :title="__('No completed trips in this period')" /></td></tr>@endforelse
            </tbody>
        </table></div></div>
    @elseif ($gst)
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3"><x-stat-card :label="__('Taxable value')" :value="Money::format($gst['taxable'], 0)" icon="receipt" color="primary" /></div>
            <div class="col-6 col-lg-3"><x-stat-card label="CGST" :value="Money::format($gst['cgst'])" icon="bank" color="info" /></div>
            <div class="col-6 col-lg-3"><x-stat-card label="SGST" :value="Money::format($gst['sgst'])" icon="bank" color="info" /></div>
            <div class="col-6 col-lg-3"><x-stat-card label="IGST" :value="Money::format($gst['igst'])" icon="bank" color="secondary" /></div>
        </div>
        <div class="card panel-card"><div class="card-header">{{ __('Documents (for GSTR-1)') }}</div><div class="table-responsive"><table class="table table-modern align-middle small">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Number') }}</th><th>{{ __('Customer') }}</th><th class="text-end">{{ __('Taxable') }}</th><th class="text-end">{{ __('Tax') }}</th><th class="text-end">{{ __('Total') }}</th></tr></thead>
            <tbody>
                @forelse ($gst['documents'] as $i)
                    <tr class="{{ $i->isCreditNote() ? 'text-danger' : '' }}"><td>{{ $i->invoice_date->format('d M') }}</td><td><a href="{{ route('operator.invoices.show', $i) }}">{{ $i->number }}</a></td><td>{{ $i->buyer['name'] ?? '' }}@if (! empty($i->buyer['gstin'])) · {{ $i->buyer['gstin'] }}@endif</td><td class="text-end">{{ $i->isCreditNote() ? '−' : '' }}{{ Money::format($i->taxable) }}</td><td class="text-end">{{ $i->isCreditNote() ? '−' : '' }}{{ Money::format($i->tax()) }}</td><td class="text-end">{{ $i->isCreditNote() ? '−' : '' }}{{ Money::format($i->total) }}</td></tr>
                @empty<tr><td colspan="6"><x-empty-state icon="receipt" :title="__('No invoices in this period')" /></td></tr>@endforelse
            </tbody>
        </table></div></div>
    @endif
</x-layouts.panel>
