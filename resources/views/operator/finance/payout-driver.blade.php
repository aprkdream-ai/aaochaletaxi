@php use App\Support\Money; @endphp
<x-layouts.panel :title="__('Payout · :n', ['n' => $driver->name])" panel="operator">
    <x-page-header :title="$driver->name" :subtitle="__('Statement :a – :b', ['a' => $from->format('d M Y'), 'b' => $to->format('d M Y')])">
        <a href="{{ route('operator.payouts.index', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
    </x-page-header>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3"><x-stat-card :label="__('Trips')" :value="$calc['trips']" icon="sign-turn-right" color="info" /></div>
                <div class="col-6 col-md-3"><x-stat-card :label="__('Earned')" :value="Money::format($calc['earned'], 0)" icon="graph-up" color="primary" /></div>
                <div class="col-6 col-md-3"><x-stat-card :label="__('Paid')" :value="Money::format($calc['paid'], 0)" icon="check2-circle" color="success" /></div>
                <div class="col-6 col-md-3"><x-stat-card :label="__('Due')" :value="Money::format($calc['due'], 0)" icon="hourglass-split" color="danger" /></div>
            </div>
            <div class="card panel-card"><div class="card-header">{{ __('Trips in period') }}</div><div class="table-responsive"><table class="table table-modern small">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Booking') }}</th><th class="text-end">{{ __('Fare (excl. GST)') }}</th><th class="text-end">{{ __('Driver allowance') }}</th></tr></thead>
                <tbody>
                    @forelse ($calc['lines'] as $l)<tr><td>{{ \Illuminate\Support\Carbon::parse($l['date'])->format('d M') }}</td><td>{{ $l['booking'] }}</td><td class="text-end">{{ Money::format($l['fare']) }}</td><td class="text-end">{{ Money::format($l['allowance']) }}</td></tr>
                    @empty<tr><td colspan="4"><x-empty-state icon="sign-turn-right" :title="__('No completed trips in this period')" /></td></tr>@endforelse
                </tbody>
            </table></div>
            <div class="card-body border-top small text-muted-2">{{ __('Pay :p (:t) + allowances :a = earned :e', ['p' => Money::format($calc['pay']), 't' => \App\Support\FleetOptions::payTypes()[$driver->pay_type] ?? '', 'a' => Money::format($calc['allowances']), 'e' => Money::format($calc['earned'])]) }}</div></div>
        </div>
        <div class="col-lg-5">
            <form method="POST" action="{{ route('operator.payouts.store', $driver) }}" class="card panel-card mb-3" data-loading>@csrf
                <div class="card-header">{{ __('Record payout') }}</div>
                <div class="card-body">
                    <input type="hidden" name="from" value="{{ $from->toDateString() }}"><input type="hidden" name="to" value="{{ $to->toDateString() }}">
                    <x-input name="amount" type="number" step="0.01" min="1" icon="currency-rupee" :label="__('Amount paid')" :value="(float) $calc['due'] > 0 ? $calc['due'] : null" required />
                    <x-input name="deductions" type="number" step="0.01" min="0" :label="__('Deductions (advance / fine)')" />
                    <x-select name="method" :label="__('Method')" :options="['upi' => 'UPI', 'bank_transfer' => __('Bank transfer'), 'cash' => __('Cash'), 'cheque' => __('Cheque'), 'other' => __('Other')]" value="upi" required />
                    <x-input name="reference" :label="__('Reference / UTR')" maxlength="100" />
                    <x-input name="notes" :label="__('Notes')" maxlength="500" />
                    <button class="btn btn-primary w-100">{{ __('Record payout') }}</button>
                </div>
            </form>
            <div class="card panel-card"><div class="card-header">{{ __('Payout history') }}</div>
                <div class="list-group list-group-flush list-modern small">
                    @forelse ($history as $s)
                        <div class="list-group-item"><div class="d-flex justify-content-between"><b>{{ Money::format($s->amount) }}</b><span class="text-muted-2">{{ $s->paid_on->format('d M Y') }}</span></div><div class="text-muted-2">{{ $s->period_from->format('d M') }}–{{ $s->period_to->format('d M Y') }} · {{ $s->trips }} {{ __('trips') }} · {{ strtoupper($s->method) }} {{ $s->reference }}</div></div>
                    @empty
                        <x-empty-state icon="cash-coin" :title="__('No payouts yet')" />
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.panel>
