@php use App\Support\FleetOptions; @endphp
<div class="card panel-card">
    @if ($canManage)
        <div class="card-body border-bottom">
            @if ($stockParts->isEmpty())
                <div class="small text-muted-2">{{ __('No parts in stock.') }} <a href="{{ route('operator.parts.index') }}">{{ __('Add tyres, batteries or parts') }}</a></div>
            @else
                <form method="POST" action="" class="row g-2 align-items-end" x-data="{ part: '' }" :action="part ? @js(route('operator.parts.fit', '__ID__')).replace('__ID__', part) : ''" data-loading>@csrf
                    <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
                    <div class="col-md-4"><label class="form-label small" for="fp_part">{{ __('Fit from stock') }}</label><select id="fp_part" class="form-select form-select-sm" x-model="part" required><option value="">{{ __('Select…') }}</option>@foreach ($stockParts as $p)<option value="{{ $p->id }}">{{ FleetOptions::partTypes()[$p->type] }} · {{ $p->label() }}</option>@endforeach</select></div>
                    <div class="col-6 col-md-2"><label class="form-label small" for="fp_pos">{{ __('Position') }}</label><select name="position" id="fp_pos" class="form-select form-select-sm"><option value="">—</option>@foreach (FleetOptions::partPositions() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="col-6 col-md-2"><label class="form-label small" for="fp_km">{{ __('Odometer km') }}</label><input type="number" name="odometer_km" id="fp_km" min="0" value="{{ $vehicle->odometer_km }}" class="form-control form-control-sm"></div>
                    <div class="col-6 col-md-2"><label class="form-label small" for="fp_on">{{ __('Date') }}</label><input type="date" name="moved_on" id="fp_on" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" required></div>
                    <div class="col-6 col-md-2"><button class="btn btn-sm btn-primary w-100" :disabled="!part"><x-icon name="plus-lg" />{{ __('Fit') }}</button></div>
                </form>
            @endif
            @foreach (['part', 'position', 'odometer_km'] as $f)@error($f)<div class="text-danger small mt-1">{{ $message }}</div>@enderror @endforeach
        </div>
    @endif
    <div class="table-responsive"><table class="table table-modern align-middle small">
        <thead><tr><th>{{ __('Part') }}</th><th>{{ __('Position') }}</th><th>{{ __('Fitted') }}</th><th class="text-end">{{ __('Km run') }}</th><th>{{ __('Warranty') }}</th><th></th></tr></thead>
        <tbody>
            @forelse ($vehicle->parts as $p)
                <tr>
                    <td><span class="fw-semibold">{{ FleetOptions::partTypes()[$p->type] }}</span> · {{ $p->label() }}</td>
                    <td>{{ FleetOptions::partPositions()[$p->position] ?? '—' }}</td>
                    <td class="text-nowrap">{{ $p->fitted_on?->format('d M Y') }}@if ($p->fitted_at_km !== null)<div class="text-muted-2">{{ number_format($p->fitted_at_km) }} km</div>@endif</td>
                    <td class="text-end fw-semibold">{{ number_format($p->setRelation('vehicle', $vehicle)->totalKm()) }}</td>
                    <td>@if ($p->warranty_until)<x-expiry-badge :expiry="\App\Support\Expiry::of($p->warranty_until)" />@else — @endif</td>
                    <td class="text-end">@if ($canManage)
                        <form method="POST" action="{{ route('operator.parts.remove', $p) }}" class="d-inline-flex gap-1" data-confirm="{{ __('Remove this part?') }}">@csrf
                            <input type="hidden" name="moved_on" value="{{ now()->toDateString() }}"><input type="hidden" name="odometer_km" value="{{ $vehicle->odometer_km }}">
                            <button name="to" value="in_stock" class="btn btn-sm btn-light border">{{ __('Remove') }}</button>
                            <button name="to" value="scrapped" class="btn btn-sm btn-light border text-danger">{{ __('Scrap') }}</button>
                        </form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state icon="gear" :title="__('No parts fitted')" :text="__('Track tyres and batteries to see km run and warranty.')" /></td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
