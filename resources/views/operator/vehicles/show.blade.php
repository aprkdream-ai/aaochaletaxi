@php
    use App\Support\FleetOptions;
    use App\Support\Money;
    $canManage = auth()->user()->hasPermission('vehicles.manage');
    $canExpense = $canManage || auth()->user()->hasPermission('expenses.manage');
    $tab = in_array(request('tab'), ['documents', 'maintenance', 'expenses', 'inspections'], true) ? request('tab') : 'documents';
    $kmpl = fn ($v) => $v === null ? '—' : rtrim(rtrim($v, '0'), '.').' '.($vehicle->fuel_type === 'electric' ? 'km/kWh' : 'km/l');
    $openIssues = $vehicle->inspections->filter->hasOpenIssues()->count();
@endphp
<x-layouts.panel :title="$vehicle->displayRegistration()" panel="operator">
    <x-page-header :title="$vehicle->displayRegistration()" :subtitle="$vehicle->make.' '.$vehicle->model.($vehicle->year ? ' · '.$vehicle->year : '').' · '.$vehicle->category->name">
        <a href="{{ route('operator.vehicles.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
        @if ($canManage)
            <form method="POST" action="{{ route('operator.vehicles.availability', $vehicle) }}">@csrf<button class="btn btn-light border"><x-icon :name="$vehicle->is_available ? 'pause-circle' : 'play-circle'" />{{ $vehicle->is_available ? __('Mark unavailable') : __('Mark available') }}</button></form>
            <a href="{{ route('operator.vehicles.edit', $vehicle) }}" class="btn btn-ink"><x-icon name="pencil" />{{ __('Edit') }}</a>
        @endif
    </x-page-header>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card panel-card h-100 overflow-hidden">
                @if ($vehicle->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($vehicle->image_path) }}" alt="{{ $vehicle->displayRegistration() }}" style="height:170px;object-fit:cover">@endif
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3"><x-status-badge :status="$vehicle->status" /><span class="badge-soft {{ $vehicle->is_available ? 'ok' : 'muted' }}">{{ $vehicle->is_available ? __('Available') : __('Unavailable') }}</span>@if ($vehicle->is_ac)<span class="badge-soft info">AC</span>@endif</div>
                    <dl class="row small mb-0">
                        <dt class="col-6 text-muted-2 fw-semibold">{{ __('Type') }}</dt><dd class="col-6">{{ FleetOptions::vehicleTypes()[$vehicle->vehicle_type] ?? $vehicle->vehicle_type }}</dd>
                        <dt class="col-6 text-muted-2 fw-semibold">{{ __('Seats') }}</dt><dd class="col-6">{{ $vehicle->seats }}</dd>
                        <dt class="col-6 text-muted-2 fw-semibold">{{ __('Fuel') }}</dt><dd class="col-6">{{ FleetOptions::fuelTypes()[$vehicle->fuel_type] ?? $vehicle->fuel_type }}</dd>
                        <dt class="col-6 text-muted-2 fw-semibold">{{ __('Ownership') }}</dt><dd class="col-6">{{ FleetOptions::ownership()[$vehicle->ownership] ?? $vehicle->ownership }}</dd>
                        <dt class="col-6 text-muted-2 fw-semibold">{{ __('Odometer') }}</dt><dd class="col-6">{{ $vehicle->odometer_km ? number_format($vehicle->odometer_km).' km' : '—' }}</dd>
                        <dt class="col-6 text-muted-2 fw-semibold">{{ __('Branch') }}</dt><dd class="col-6">{{ $vehicle->branch?->name ?? '—' }}</dd>
                        @if ($vehicle->fastag_id)<dt class="col-6 text-muted-2 fw-semibold">FASTag</dt><dd class="col-6 text-break">{{ $vehicle->fastag_id }}</dd>@endif
                        @if ($vehicle->color)<dt class="col-6 text-muted-2 fw-semibold">{{ __('Colour') }}</dt><dd class="col-6">{{ $vehicle->color }}</dd>@endif
                    </dl>
                    @if ($vehicle->notes)<div class="small text-muted-2 mt-2" style="white-space:pre-line">{{ $vehicle->notes }}</div>@endif
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="row g-3 mb-3">
                @foreach ($vehicle->expiries() as $field => $e)
                    <div class="col-6 col-md-3"><div class="stat">
                        <div class="stat-label">{{ $e['name'] }}</div>
                        <div class="fw-bold mt-1">{{ $e['date']?->format('d M Y') ?? '—' }}</div>
                        <x-expiry-badge :expiry="$e" class="mt-1 d-inline-block" />
                    </div></div>
                @endforeach
            </div>
            <div class="row g-3">
                <div class="col-6 col-md-3"><x-stat-card :label="__('Mileage (average)')" :value="$kmpl($fuel['average'])" icon="speedometer" color="success" :hint="$fuel['last'] !== null ? __('Last fill: :v', ['v' => $kmpl($fuel['last'])]) : __('Log full-tank fills with odometer')" /></div>
                <div class="col-6 col-md-3"><x-stat-card :label="__('Maintenance (all time)')" :value="Money::format($totals['maintenance'], 0)" icon="tools" color="warning" /></div>
                <div class="col-6 col-md-3"><x-stat-card :label="__('Expenses this month')" :value="Money::format($totals['expenses_month'], 0)" icon="fuel-pump" color="info" /></div>
                <div class="col-6 col-md-3"><x-stat-card :label="__('Expenses (all time)')" :value="Money::format($totals['expenses_all'], 0)" icon="cash-stack" color="secondary" /></div>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills gap-2 mb-3" role="tablist">
        @foreach (['documents' => [__('Documents'), 'folder2-open', $vehicle->documents->count()], 'maintenance' => [__('Maintenance'), 'tools', $vehicle->maintenance->count()], 'expenses' => [__('Expenses & fuel'), 'receipt', $vehicle->expenses->count()], 'inspections' => [__('Daily checks'), 'clipboard-check', $openIssues ?: $vehicle->inspections->count()]] as $key => [$label, $icon, $n])
            <li class="nav-item"><a href="{{ route('operator.vehicles.show', [$vehicle, 'tab' => $key]) }}" @class(['nav-link d-flex align-items-center gap-2', 'active bg-ink text-white' => $tab === $key, 'bg-white border text-body' => $tab !== $key])><x-icon :name="$icon" />{{ $label }} <span class="badge rounded-pill {{ $tab === $key ? 'text-bg-warning' : 'text-bg-light' }}">{{ $n }}</span></a></li>
        @endforeach
    </ul>

    @if ($tab === 'documents')
        <div class="card panel-card">
            @if ($canManage)<div class="card-body border-bottom"><x-doc-uploader :action="route('operator.vehicles.documents.store', $vehicle)" :types="FleetOptions::vehicleDocuments()" /><div class="form-text">{{ __('Adding a validity date for insurance, permit, fitness or PUC also updates the reminder date above.') }}</div></div>@endif
            <div class="list-group list-group-flush list-modern">
                @forelse ($vehicle->documents as $doc)
                    <div class="list-group-item d-flex flex-wrap align-items-center gap-3">
                        <span class="stat-ico tone-secondary"><x-icon :name="$doc->isImage() ? 'file-earmark-image' : 'file-earmark-pdf'" /></span>
                        <div class="me-auto min-w-0"><div class="fw-semibold">{{ FleetOptions::vehicleDocuments()[$doc->type] ?? $doc->type }} @if ($doc->number)<span class="text-muted-2 fw-normal">· {{ $doc->number }}</span>@endif</div><div class="small text-muted-2 text-truncate">{{ $doc->original_name }} · {{ $doc->created_at->format('d M Y') }}</div></div>
                        @if ($doc->expires_on)<x-expiry-badge :expiry="$doc->expiry()" />@endif
                        <a href="{{ route('operator.fleet-documents.show', $doc) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border" aria-label="{{ __('View') }}"><x-icon name="eye" /></a>
                        @if ($canManage)<form method="POST" action="{{ route('operator.fleet-documents.destroy', $doc) }}" data-confirm="{{ __('Delete this document?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" aria-label="{{ __('Delete') }}"><x-icon name="trash" /></button></form>@endif
                    </div>
                @empty
                    <x-empty-state icon="folder2-open" :title="__('No documents yet')" :text="__('Upload RC, insurance, permit, fitness and PUC so everything is one tap away.')" />
                @endforelse
            </div>
        </div>
    @elseif ($tab === 'maintenance')
        <div class="card panel-card">
            @if ($canManage)
                <div class="card-body border-bottom">
                    <form method="POST" action="{{ route('operator.vehicles.maintenance.store', $vehicle) }}" class="row g-2 align-items-end" data-loading>@csrf
                        <div class="col-6 col-md-2"><label class="form-label small" for="m_date">{{ __('Date') }}</label><input type="date" name="service_date" id="m_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" required></div>
                        <div class="col-6 col-md-2"><label class="form-label small" for="m_type">{{ __('Type') }}</label><select name="type" id="m_type" class="form-select form-select-sm">@foreach (FleetOptions::maintenanceTypes() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label small" for="m_desc">{{ __('Work done') }}</label><input type="text" name="description" id="m_desc" class="form-control form-control-sm" required maxlength="500"></div>
                        <div class="col-6 col-md-1"><label class="form-label small" for="m_cost">{{ __('Cost ₹') }}</label><input type="number" name="cost" id="m_cost" min="0" step="0.01" class="form-control form-control-sm" required></div>
                        <div class="col-6 col-md-1"><label class="form-label small" for="m_km">{{ __('Km') }}</label><input type="number" name="odometer_km" id="m_km" min="0" class="form-control form-control-sm"></div>
                        <div class="col-6 col-md-2"><label class="form-label small" for="m_next">{{ __('Next service') }}</label><input type="date" name="next_service_date" id="m_next" class="form-control form-control-sm"></div>
                        <div class="col-6 col-md-1"><label class="form-label small" for="m_next_km">{{ __('or at km') }}</label><input type="number" name="next_service_km" id="m_next_km" min="0" class="form-control form-control-sm"></div>
                        <div class="col-12 col-md-12 text-end"><button class="btn btn-sm btn-primary"><x-icon name="plus-lg" />{{ __('Add entry') }}</button></div>
                        <input type="hidden" name="vendor" value="">
                    </form>
                    <div class="form-text">{{ __('Set the next service date and/or km — you get a reminder 7 days or :km km before.', ['km' => \App\Services\FleetExpiryService::SERVICE_KM_WARNING]) }}</div>
                    @foreach (['service_date', 'description', 'cost', 'next_service_date', 'next_service_km'] as $f)@error($f)<div class="text-danger small mt-1">{{ $message }}</div>@enderror @endforeach
                </div>
            @endif
            <div class="table-responsive"><table class="table table-modern align-middle small">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Work') }}</th><th>{{ __('Km') }}</th><th class="text-end">{{ __('Cost') }}</th><th>{{ __('Next') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($vehicle->maintenance as $m)
                        <tr><td class="text-nowrap">{{ $m->service_date->format('d M Y') }}</td><td>{{ FleetOptions::maintenanceTypes()[$m->type] ?? $m->type }}</td><td>{{ $m->description }}</td><td>{{ $m->odometer_km ? number_format($m->odometer_km) : '—' }}</td><td class="text-end fw-semibold text-nowrap">{{ Money::format($m->cost) }}</td><td class="text-nowrap">{{ collect([$m->next_service_date?->format('d M Y'), $m->next_service_km ? number_format($m->next_service_km).' km' : null])->filter()->join(' / ') ?: '—' }}</td>
                            <td class="text-end">@if ($canManage)<form method="POST" action="{{ route('operator.maintenance.destroy', $m) }}" data-confirm="{{ __('Delete this entry?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger" aria-label="{{ __('Delete') }}"><x-icon name="trash" /></button></form>@endif</td></tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="tools" :title="__('No maintenance recorded')" /></td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </div>
    @elseif ($tab === 'expenses')
        <div class="card panel-card">
            @if ($fuel['rate'] !== null)<div class="card-body border-bottom small text-muted-2">{{ __('Fuel logged: :l · average rate :r per unit', ['l' => rtrim(rtrim($fuel['litres'], '0'), '.'), 'r' => Money::format($fuel['rate'])]) }}</div>@endif
            @if ($canExpense)
                <div class="card-body border-bottom">
                    <form method="POST" action="{{ route('operator.vehicles.expenses.store', $vehicle) }}" class="row g-2 align-items-end" data-loading x-data="{ cat: @js(old('category', 'fuel')) }">@csrf
                        <div class="col-6 col-md-2"><label class="form-label small" for="e_date">{{ __('Date') }}</label><input type="date" name="expense_date" id="e_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" required></div>
                        <div class="col-6 col-md-2"><label class="form-label small" for="e_cat">{{ __('Category') }}</label><select name="category" id="e_cat" class="form-select form-select-sm" x-model="cat">@foreach (FleetOptions::expenseCategories() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                        <div class="col-6 col-md-2"><label class="form-label small" for="e_amt">{{ __('Amount ₹') }}</label><input type="number" name="amount" id="e_amt" min="1" step="0.01" class="form-control form-control-sm" required></div>
                        <div class="col-6 col-md-2"><label class="form-label small" for="e_pm">{{ __('Paid by') }}</label><select name="payment_method" id="e_pm" class="form-select form-select-sm">@foreach (['cash' => __('Cash'), 'upi' => 'UPI', 'card' => __('Card'), 'fastag' => 'FASTag', 'bank_transfer' => __('Bank'), 'other' => __('Other')] as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label small" for="e_notes">{{ __('Notes') }}</label><input type="text" name="notes" id="e_notes" class="form-control form-control-sm" maxlength="500"></div>
                        <div class="col-md-1"><button class="btn btn-sm btn-primary w-100" aria-label="{{ __('Add expense') }}"><x-icon name="plus-lg" /></button></div>
                        <template x-if="cat === 'fuel'"><div class="row g-2 m-0 p-0 w-100 align-items-end">
                            <div class="col-6 col-md-2"><label class="form-label small" for="e_litres">{{ $vehicle->fuel_type === 'electric' ? __('kWh') : __('Litres') }}</label><input type="number" name="litres" id="e_litres" min="0.1" step="0.01" class="form-control form-control-sm" required></div>
                            <div class="col-6 col-md-2"><label class="form-label small" for="e_km">{{ __('Odometer km') }}</label><input type="number" name="odometer_km" id="e_km" min="0" class="form-control form-control-sm" placeholder="{{ $vehicle->odometer_km }}"></div>
                            <div class="col-12 col-md-4"><div class="form-check mb-1"><input type="hidden" name="full_tank" value="0"><input class="form-check-input" type="checkbox" name="full_tank" value="1" id="e_full" checked><label class="form-check-label small" for="e_full">{{ __('Full tank (needed for mileage)') }}</label></div></div>
                        </div></template>
                    </form>
                    @foreach (['expense_date', 'amount', 'category', 'litres', 'odometer_km'] as $f)@error($f)<div class="text-danger small mt-1">{{ $message }}</div>@enderror @endforeach
                </div>
            @endif
            <div class="table-responsive"><table class="table table-modern align-middle small">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Category') }}</th><th>{{ __('Fuel') }}</th><th>{{ __('Notes') }}</th><th>{{ __('Paid by') }}</th><th class="text-end">{{ __('Amount') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($vehicle->expenses as $e)
                        <tr><td class="text-nowrap">{{ $e->expense_date->format('d M Y') }}</td><td>{{ FleetOptions::expenseCategories()[$e->category] ?? $e->category }}</td>
                            <td class="text-nowrap">@if ($e->litres){{ rtrim(rtrim((string) $e->litres, '0'), '.') }} {{ $vehicle->fuel_type === 'electric' ? 'kWh' : 'L' }}@if ($e->odometer_km)<span class="text-muted-2"> · {{ number_format($e->odometer_km) }} km</span>@endif @unless ($e->full_tank)<span class="text-muted-2">· {{ __('partial') }}</span>@endunless
                                @if (isset($fuel['fills'][$e->id]))<div><span class="badge-soft {{ $fuel['fills'][$e->id]['low'] ? 'danger' : 'ok' }}" @if ($fuel['fills'][$e->id]['low']) title="{{ __('Much lower than this vehicle’s average — check for leak, theft or a wrong entry') }}" @endif>{{ $kmpl($fuel['fills'][$e->id]['kmpl']) }}</span></div>@endif
                                @else — @endif</td>
                            <td>{{ $e->notes ?: '—' }}</td><td class="text-uppercase">{{ $e->payment_method ?? '—' }}</td><td class="text-end fw-semibold text-nowrap">{{ Money::format($e->amount) }}</td>
                            <td class="text-end">@if ($canExpense)<form method="POST" action="{{ route('operator.expenses.destroy', $e) }}" data-confirm="{{ __('Delete this expense?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger" aria-label="{{ __('Delete') }}"><x-icon name="trash" /></button></form>@endif</td></tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state icon="receipt" :title="__('No expenses recorded')" /></td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </div>
    @else
        <div class="card panel-card">
            <div class="card-body border-bottom small text-muted-2">{{ __('Drivers fill the daily checklist from the driver app before driving. Items marked as an issue alert you here and on the dashboard.') }}</div>
            <div class="list-group list-group-flush list-modern">
                @forelse ($vehicle->inspections as $ins)
                    <div class="list-group-item">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <span class="date-tile"><b>{{ $ins->inspected_on->format('d') }}</b><small>{{ $ins->inspected_on->format('M') }}</small></span>
                            <div class="me-auto min-w-0">
                                <div class="fw-semibold">{{ $ins->driver?->name ?? __('Staff') }} @if ($ins->odometer_km)<span class="text-muted-2 fw-normal">· {{ number_format($ins->odometer_km) }} km</span>@endif</div>
                                @if ($ins->issues_count)
                                    <div class="small text-danger">{{ implode(', ', $ins->issueLabels()) }}</div>
                                @else
                                    <div class="small text-success">{{ __('All items OK') }}</div>
                                @endif
                                @if ($ins->notes)<div class="small text-muted-2">{{ $ins->notes }}</div>@endif
                                @if ($ins->resolved_at)<div class="small text-muted-2">{{ __('Resolved by :n on :d', ['n' => $ins->resolver?->name ?? '—', 'd' => $ins->resolved_at->format('d M Y')]) }}</div>@endif
                            </div>
                            @if ($ins->photo_path)<a href="{{ route('operator.inspections.photo', $ins) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><x-icon name="image" />{{ __('Photo') }}</a>@endif
                            @if ($ins->hasOpenIssues())
                                <span class="badge-soft danger">{{ trans_choice('{1} 1 issue|[2,*] :n issues', $ins->issues_count, ['n' => $ins->issues_count]) }}</span>
                                @if ($canManage)<form method="POST" action="{{ route('operator.inspections.resolve', $ins) }}">@csrf<button class="btn btn-sm btn-ink"><x-icon name="check2" />{{ __('Mark resolved') }}</button></form>@endif
                            @elseif (! $ins->issues_count)
                                <span class="badge-soft ok">{{ __('OK') }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="clipboard-check" :title="__('No daily checks yet')" :text="__('They appear here once a driver submits the checklist in the driver app.')" />
                @endforelse
            </div>
        </div>
    @endif

    @if ($canManage)
        <form method="POST" action="{{ route('operator.vehicles.destroy', $vehicle) }}" class="mt-4 text-end" data-confirm="{{ __('Remove this vehicle from your fleet? History is kept.') }}">@csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger"><x-icon name="trash" />{{ __('Remove vehicle') }}</button>
        </form>
    @endif
</x-layouts.panel>
