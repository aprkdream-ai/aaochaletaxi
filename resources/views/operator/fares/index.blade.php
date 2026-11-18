@php use App\Support\Money; use App\Enums\BookingType; @endphp
<x-layouts.panel :title="__('Fare rules')" panel="operator">
    <x-page-header :title="__('Fare rules')" :subtitle="__('Your rates per vehicle type and trip. Changes apply to new quotes only.')" />
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach (BookingType::cases() as $t)
            <a href="{{ route('operator.fares.index', ['type' => $t->value]) }}" @class(['btn btn-sm rounded-pill', 'btn-ink' => $type === $t, 'btn-light border' => $type !== $t])>{{ $t->label() }}</a>
        @endforeach
    </div>
    <div class="card panel-card"><div class="table-responsive">
        <table class="table table-modern align-middle">
            <thead><tr>
                <th>{{ __('Vehicle type') }}</th>
                @if ($type->isRental())<th>{{ __('Package') }}</th><th>{{ __('Extra km') }}</th><th>{{ __('Extra hour') }}</th>@else<th>{{ __('Base') }}</th><th>{{ __('Per km') }}</th><th>{{ __('Min km') }}</th>@endif
                <th>{{ __('Driver/day') }}</th><th>{{ __('Night') }}</th><th>GST</th><th></th>
            </tr></thead>
            <tbody>
                @foreach ($categories as $c)
                    @php $r = $rules[$c->id] ?? null; @endphp
                    <tr @class(['opacity-50' => $r && ! $r->is_active])>
                        <td class="fw-semibold"><x-icon :name="$c->icon" class="text-muted-2" /> {{ $c->name }}</td>
                        @if ($r)
                            @if ($type->isRental())
                                <td>{{ Money::format($r->base_fare, 0) }} <span class="small text-muted-2">({{ $r->included_hours }}h/{{ $r->included_km }}km)</span></td><td>₹{{ (float) $r->extra_km_rate }}</td><td>₹{{ (float) $r->extra_hour_rate }}</td>
                            @else
                                <td>{{ Money::format($r->base_fare, 0) }}</td><td class="fw-bold">₹{{ (float) $r->per_km }}</td><td>{{ $r->min_km }}@if ($type === BookingType::OutstationRound)<span class="small text-muted-2">/{{ __('day') }}</span>@endif</td>
                            @endif
                            <td>{{ (float) $r->driver_allowance ? Money::format($r->driver_allowance, 0) : '—' }}</td>
                            <td>{{ (float) $r->night_charge ? Money::format($r->night_charge, 0) : '—' }}</td>
                            <td>{{ (float) $r->gst_percent }}%</td>
                            <td class="text-end"><a href="{{ route('operator.fares.edit', $r) }}" class="btn btn-sm btn-light">{{ __('Edit') }}</a></td>
                        @else
                            <td colspan="7" class="text-muted-2 small">{{ __('No rule — bookings of this type cannot be priced.') }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div></div>
    <p class="small text-muted-2 mt-2">{{ __('Tolls, parking and state taxes are added per booking at actuals and are not charged GST.') }}</p>
</x-layouts.panel>
