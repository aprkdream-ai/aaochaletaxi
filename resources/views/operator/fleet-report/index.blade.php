@php
    use App\Support\FleetOptions;
    use App\Support\Money;
    $q = ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    $num = fn ($v) => $v === null ? '—' : rtrim(rtrim((string) $v, '0'), '.');
@endphp
<x-layouts.panel :title="__('Fleet cost report')" panel="operator">
    <x-page-header :title="__('Fleet cost report')" :subtitle="__('Running cost, cost per km and utilisation of each vehicle')">
        <a href="{{ route('operator.fleet-report.export', $q + ['tab' => 'vehicles']) }}" class="btn btn-light border"><x-icon name="download" />{{ __('Vehicles CSV') }}</a>
        @if ($fleet)<a href="{{ route('operator.fleet-report.export', $q + ['tab' => 'departments']) }}" class="btn btn-light border"><x-icon name="download" />{{ __('Departments CSV') }}</a>@endif
    </x-page-header>

    <form method="GET" class="card panel-card mb-3"><div class="card-body row g-2 align-items-end">
        <div class="col-6 col-md-3"><label class="form-label small" for="r_from">{{ __('From') }}</label><input type="date" name="from" id="r_from" value="{{ $q['from'] }}" class="form-control"></div>
        <div class="col-6 col-md-3"><label class="form-label small" for="r_to">{{ __('To') }}</label><input type="date" name="to" id="r_to" value="{{ $q['to'] }}" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-ink w-100"><x-icon name="funnel" />{{ __('Apply') }}</button></div>
        @error('to')<div class="col-12 small text-danger">{{ $message }}</div>@enderror
    </div></form>

    <div class="row g-3 mb-3">
        <div class="col-4"><x-stat-card :label="__('Km driven')" :value="number_format($totals->km)" icon="speedometer" color="primary" /></div>
        <div class="col-4"><x-stat-card :label="__('Completed trips')" :value="$totals->trips" icon="signpost-split" color="success" /></div>
        <div class="col-4"><x-stat-card :label="__('Running cost')" :value="Money::format($totals->cost, 0)" icon="cash-stack" color="warning" :hint="$totals->km ? __(':r per km', ['r' => Money::format(bcdiv($totals->cost, (string) $totals->km, 2))]) : null" /></div>
    </div>

    <div class="card panel-card mb-3">
        <div class="card-header">{{ __('By vehicle') }}</div>
        <div class="table-responsive"><table class="table table-modern align-middle small">
            <thead><tr><th>{{ __('Vehicle') }}</th><th class="text-end">{{ __('Trips') }}</th><th class="text-end">{{ __('Km') }}</th><th class="text-end">{{ __('Hours') }}</th><th class="text-end">{{ __('Fuel') }}</th><th class="text-end">{{ __('Avg km/l') }}</th><th class="text-end">{{ __('Fuel ₹') }}</th><th class="text-end">{{ __('Other ₹') }}</th><th class="text-end">{{ __('Maintenance ₹') }}</th><th class="text-end">{{ __('Total ₹') }}</th><th class="text-end">{{ __('₹ / km') }}</th><th class="text-end">{{ __('Book value') }}</th></tr></thead>
            <tbody>
                @forelse ($vehicles as $r)
                    <tr>
                        <td><a href="{{ route('operator.vehicles.show', $r->vehicle->id) }}" class="fw-semibold">{{ $r->vehicle->displayRegistration() }}</a><div class="text-muted-2">{{ FleetOptions::vehicleTypes()[$r->vehicle->vehicle_type] ?? '' }} · {{ $r->vehicle->category?->name }}</div></td>
                        <td class="text-end">{{ $r->trips }}@if ($r->fleet_trips && $r->fleet_trips !== $r->trips)<div class="text-muted-2">{{ __(':n fleet', ['n' => $r->fleet_trips]) }}</div>@endif</td>
                        <td class="text-end">{{ number_format($r->km) }}</td>
                        <td class="text-end">{{ $r->hours ?: '—' }}</td>
                        <td class="text-end">{{ (float) $r->litres > 0 ? $num($r->litres) : '—' }}</td>
                        <td class="text-end">{{ $num($r->kmpl) }}</td>
                        <td class="text-end">{{ Money::format($r->fuel, 0) }}</td>
                        <td class="text-end">{{ Money::format($r->other, 0) }}</td>
                        <td class="text-end">{{ Money::format($r->maintenance, 0) }}</td>
                        <td class="text-end fw-semibold">{{ Money::format($r->total, 0) }}</td>
                        <td class="text-end fw-bold">{{ $r->per_km !== null ? Money::format($r->per_km) : '—' }}</td>
                        <td class="text-end">{{ $r->book_value !== null ? Money::format($r->book_value, 0) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="12"><x-empty-state icon="speedometer" :title="__('No activity in this period')" :text="__('Completed trips and recorded expenses will appear here.')" /></td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="card-body border-top small text-muted-2">{{ __('Km comes from completed trips (start/end odometer). Cost per km = fuel + other expenses + maintenance in the period ÷ km.') }}</div>
    </div>

    @if ($fleet)
        <div class="card panel-card">
            <div class="card-header">{{ __('By department') }}</div>
            <div class="table-responsive"><table class="table table-modern align-middle small">
                <thead><tr><th>{{ __('Department') }}</th><th class="text-end">{{ __('Trips') }}</th><th class="text-end">{{ __('Km') }}</th><th class="text-end">{{ __('Hours') }}</th><th class="text-end">{{ __('Allocated cost') }}</th></tr></thead>
                <tbody>
                    @forelse ($departments as $d)
                        <tr><td class="fw-semibold">{{ $d->department }}</td><td class="text-end">{{ $d->trips }}</td><td class="text-end">{{ number_format($d->km) }}</td><td class="text-end">{{ $d->hours ?: '—' }}</td><td class="text-end fw-bold">{{ Money::format($d->cost, 0) }}</td></tr>
                    @empty
                        <tr><td colspan="5"><x-empty-state icon="building" :title="__('No fleet trips in this period')" /></td></tr>
                    @endforelse
                </tbody>
            </table></div>
            <div class="card-body border-top small text-muted-2">{{ __('Allocated cost = department’s trip km × that vehicle’s cost per km in the period.') }}</div>
        </div>
    @endif
</x-layouts.panel>
