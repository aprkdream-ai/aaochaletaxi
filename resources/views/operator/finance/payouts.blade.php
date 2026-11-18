@php use App\Support\Money; use App\Support\FleetOptions; @endphp
<x-layouts.panel :title="__('Driver payouts')" panel="operator">
    <x-page-header :title="__('Driver payouts')" :subtitle="__('Earnings = pay (salary / per trip / commission) + driver allowances charged to customers')">
        <form method="GET" class="d-flex gap-2 align-items-center"><input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm w-auto" aria-label="{{ __('From') }}"><span>–</span><input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm w-auto" aria-label="{{ __('To') }}"><button class="btn btn-sm btn-ink">{{ __('Go') }}</button></form>
    </x-page-header>
    <div class="card panel-card mb-3"><div class="table-responsive"><table class="table table-modern align-middle">
        <thead><tr><th>{{ __('Driver') }}</th><th>{{ __('Pay type') }}</th><th class="text-end">{{ __('Trips') }}</th><th class="text-end">{{ __('Pay') }}</th><th class="text-end">{{ __('Allowances') }}</th><th class="text-end">{{ __('Earned') }}</th><th class="text-end">{{ __('Paid') }}</th><th class="text-end">{{ __('Due') }}</th><th></th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td class="fw-semibold">{{ $r->driver->name }}</td>
                    <td class="small">{{ FleetOptions::payTypes()[$r->driver->pay_type] ?? $r->driver->pay_type }}</td>
                    <td class="text-end">{{ $r->trips }}</td><td class="text-end">{{ Money::format($r->pay, 0) }}</td><td class="text-end">{{ Money::format($r->allowances, 0) }}</td>
                    <td class="text-end fw-semibold">{{ Money::format($r->earned, 0) }}</td><td class="text-end text-success">{{ Money::format($r->paid, 0) }}</td>
                    <td class="text-end fw-800 {{ (float) $r->due > 0 ? 'text-danger' : '' }}">{{ Money::format($r->due, 0) }}</td>
                    <td class="text-end"><a href="{{ route('operator.payouts.show', [$r->driver, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-sm btn-light">{{ __('Pay') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty-state icon="person-badge" :title="__('No drivers yet')" /></td></tr>
            @endforelse
        </tbody>
    </table></div></div>
    <div class="card panel-card"><div class="card-header">{{ __('Recent payouts') }}</div>
        <div class="list-group list-group-flush list-modern small">
            @forelse ($recent as $s)
                <div class="list-group-item d-flex justify-content-between"><span><b>{{ $s->driver->name }}</b> · {{ $s->period_from->format('d M') }}–{{ $s->period_to->format('d M Y') }} · {{ strtoupper($s->method) }} {{ $s->reference }}</span><span class="fw-bold">{{ Money::format($s->amount) }}</span></div>
            @empty
                <x-empty-state icon="cash-coin" :title="__('No payouts recorded')" />
            @endforelse
        </div>
    </div>
</x-layouts.panel>
