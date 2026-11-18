@php
    use App\Support\Money;
    $canManage = auth()->user()->hasPermission('admin.tenants.manage');
    $canBill = auth()->user()->hasPermission('admin.subscriptions.manage');
    $status = $tenant->status->value;
    $methods = \App\Enums\PaymentMethod::manual();
@endphp
<x-layouts.panel :title="$tenant->name" panel="admin">
    <x-page-header :title="$tenant->name" :subtitle="__('Registered :date', ['date' => $tenant->created_at->format('d M Y, h:i A')]).' · /o/'.$tenant->slug">
        <a href="{{ route('admin.tenants.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
    </x-page-header>

    {{-- Status bar --}}
    <div class="card panel-card mb-3">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="avatar" style="width:3rem;height:3rem">@if ($tenant->logoUrl())<img src="{{ $tenant->logoUrl() }}" alt="" class="rounded-circle w-100 h-100" style="object-fit:cover">@else{{ mb_strtoupper(mb_substr($tenant->name, 0, 2)) }}@endif</span>
            <div class="me-auto">
                <div class="d-flex flex-wrap gap-2 align-items-center"><x-status-badge :status="$tenant->status" /><span class="badge-soft {{ ['verified' => 'ok', 'submitted' => 'warn', 'rejected' => 'danger'][$tenant->kyc_status->value] ?? 'muted' }}">KYC: {{ $tenant->kyc_status->label() }}</span>
                    @if ($current)<span class="badge-soft info">{{ $current->plan->name }} · {{ __('till :d', ['d' => $current->ends_at->format('d M Y')]) }}</span>@else<span class="badge-soft danger">{{ __('No active subscription') }}</span>@endif
                </div>
                @if ($tenant->suspension_reason)<div class="small text-danger mt-1">{{ $tenant->suspension_reason }}</div>@endif
            </div>
            @if ($canManage)
                <div class="d-flex flex-wrap gap-2">
                    @if (in_array($status, ['pending', 'rejected']))
                        <form method="POST" action="{{ route('admin.tenants.approve', $tenant) }}" data-confirm="{{ __('Approve and activate this operator?') }}">@csrf<button class="btn btn-success"><x-icon name="check2-circle" />{{ __('Approve & activate') }}</button></form>
                    @endif
                    @if ($status === 'suspended')
                        <form method="POST" action="{{ route('admin.tenants.reactivate', $tenant) }}" data-confirm="{{ __('Reactivate this operator?') }}">@csrf<button class="btn btn-success"><x-icon name="play-circle" />{{ __('Reactivate') }}</button></form>
                    @endif
                    @if ($status === 'pending')
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">{{ __('Reject') }}</button>
                    @endif
                    @if ($status === 'active')
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#suspendModal"><x-icon name="pause-circle" />{{ __('Suspend') }}</button>
                    @endif
                </div>
            @endif
        </div>
        @error('status')<div class="card-body border-top py-2"><div class="text-danger small">{{ $message }}</div></div>@enderror
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            {{-- Subscription --}}
            <div class="card panel-card mb-3" id="subscription">
                <div class="card-header"><span class="d-flex align-items-center gap-2"><x-icon name="credit-card-2-front" />{{ __('Subscription') }}</span>
                    @if ($canBill)<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#activateModal"><x-icon name="lightning-charge" />{{ $current && $current->billing_cycle !== 'trial' ? __('Renew / activate') : __('Activate paid plan') }}</button>@endif
                </div>
                @if ($current)
                    <div class="card-body border-bottom">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-5"><div class="small text-muted-2">{{ __('Current plan') }}</div><div class="fs-5 fw-800">{{ $current->plan->name }} <span class="small fw-normal text-muted-2 text-capitalize">· {{ $current->billing_cycle }}</span></div></div>
                            <div class="col-6 col-md-3"><div class="small text-muted-2">{{ __('Ends') }}</div><div class="fw-bold">{{ $current->ends_at->format('d M Y') }}</div></div>
                            <div class="col-6 col-md-4"><div class="small text-muted-2">{{ __('Status') }}</div><x-status-badge :status="$current->status" /> <span class="small text-muted-2">{{ trans_choice('{0} ends today|{1} :n day left|[2,*] :n days left', $current->daysLeft(), ['n' => $current->daysLeft()]) }}</span></div>
                        </div>
                        @if ($canBill)
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#paymentModal"><x-icon name="cash-coin" />{{ __('Record payment') }}</button>
                                <button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#extendModal"><x-icon name="calendar-plus" />{{ __('Extend') }}</button>
                                <button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#planModal"><x-icon name="arrow-left-right" />{{ __('Change plan') }}</button>
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal"><x-icon name="x-circle" />{{ __('Cancel') }}</button>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="card-body border-bottom"><div class="alert alert-warning mb-0 small">{{ __('This operator has no usable subscription — their panel is restricted to subscription, profile, KYC and support pages.') }}</div></div>
                @endif
                @foreach (['plan_id', 'billing_cycle', 'amount', 'method', 'days', 'reason', 'paid_at'] as $f)@error($f)<div class="card-body py-2 border-bottom"><div class="text-danger small">{{ $message }}</div></div>@enderror @endforeach
                <div class="table-responsive">
                    <table class="table table-modern align-middle small">
                        <thead><tr><th>{{ __('Plan') }}</th><th>{{ __('Period') }}</th><th>{{ __('Price') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($tenant->subscriptions as $s)
                                <tr @class(['opacity-50' => $s->superseded_by])>
                                    <td class="fw-semibold">{{ $s->plan->name }} <span class="text-muted-2 text-capitalize fw-normal">· {{ $s->billing_cycle }}</span></td>
                                    <td class="text-nowrap">{{ $s->starts_at->format('d M Y') }} – {{ $s->ends_at->format('d M Y') }}</td>
                                    <td>{{ Money::format($s->price, 0) }}</td>
                                    <td><x-status-badge :status="$s->status" />@if ($s->superseded_by)<span class="text-muted-2"> · {{ __('superseded') }}</span>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><x-empty-state icon="credit-card" :title="__('No subscriptions')" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Payments --}}
            <div class="card panel-card mb-3">
                <div class="card-header"><span class="d-flex align-items-center gap-2"><x-icon name="receipt" />{{ __('Payments & invoices') }}</span></div>
                <div class="table-responsive">
                    <table class="table table-modern align-middle small">
                        <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Total') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($tenant->subscriptionPayments as $p)
                                <tr>
                                    <td class="fw-semibold">{{ $p->invoice_number }}</td>
                                    <td>{{ $p->paid_at?->format('d M Y') }}</td>
                                    <td>{{ $p->method->label() }}@if ($p->reference)<div class="text-muted-2">{{ $p->reference }}</div>@endif</td>
                                    <td class="text-end fw-bold">{{ Money::format($p->total) }}</td>
                                    <td class="text-end"><a href="{{ route('admin.invoices.show', $p) }}" target="_blank" class="btn btn-sm btn-light"><x-icon name="printer" /></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-empty-state icon="receipt" :title="__('No payments recorded')" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- KYC --}}
            <div class="card panel-card mb-3" id="kyc">
                <div class="card-header"><span class="d-flex align-items-center gap-2"><x-icon name="patch-check" />{{ __('KYC documents') }}</span>
                    <span class="small fw-normal d-flex gap-2">@foreach ($kycRequired as $type => $state)<span class="badge-soft {{ ['approved' => 'ok', 'pending' => 'warn', 'rejected' => 'danger'][$state] ?? 'muted' }}" title="{{ \App\Enums\DocumentType::from($type)->label() }}">{{ \Illuminate\Support\Str::of(\App\Enums\DocumentType::from($type)->label())->before(' (') }}</span>@endforeach</span>
                </div>
                <div class="list-group list-group-flush list-modern">
                    @forelse ($tenant->documents as $doc)
                        <div class="list-group-item">
                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <span class="stat-ico tone-secondary"><x-icon :name="$doc->isImage() ? 'file-earmark-image' : 'file-earmark-pdf'" /></span>
                                <div class="me-auto min-w-0">
                                    <div class="fw-semibold">{{ $doc->type->label() }} @if ($doc->number)<span class="text-muted-2 fw-normal">· {{ $doc->number }}</span>@endif</div>
                                    <div class="small text-muted-2 text-truncate">{{ $doc->original_name }} · {{ $doc->humanSize() }} · {{ $doc->created_at->format('d M Y') }}</div>
                                    @if ($doc->review_notes)<div class="small {{ $doc->status->value === 'rejected' ? 'text-danger' : 'text-muted-2' }}">{{ $doc->review_notes }}</div>@endif
                                </div>
                                <x-status-badge :status="$doc->status" />
                                <a href="{{ route('admin.kyc.download', $doc) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><x-icon name="eye" />{{ __('View') }}</a>
                            </div>
                            @if ($canManage && $doc->status->value === 'pending')
                                <form method="POST" action="{{ route('admin.kyc.review', $doc) }}" class="d-flex flex-wrap gap-2 mt-2">@csrf
                                    <input type="text" name="review_notes" class="form-control form-control-sm flex-grow-1" style="max-width: 360px" placeholder="{{ __('Note (required for rejection)') }}" maxlength="500" aria-label="{{ __('Review note') }}">
                                    <button name="status" value="approved" class="btn btn-sm btn-success"><x-icon name="check2" />{{ __('Approve') }}</button>
                                    <button name="status" value="rejected" class="btn btn-sm btn-outline-danger"><x-icon name="x" />{{ __('Reject') }}</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <x-empty-state icon="folder2-open" :title="__('No documents uploaded yet')" />
                    @endforelse
                </div>
                @error('review_notes')<div class="card-body py-2 border-top"><div class="text-danger small">{{ $message }}</div></div>@enderror
                @if ($canManage)
                    <div class="card-body border-top">
                        <form method="POST" action="{{ route('admin.tenants.kyc', $tenant) }}" class="row g-2 align-items-end">@csrf
                            <div class="col-md-4"><label class="form-label small" for="kyc_status">{{ __('Overall KYC status') }}</label>
                                <select name="kyc_status" id="kyc_status" class="form-select form-select-sm">@foreach ($kycStatuses as $k)<option value="{{ $k->value }}" @selected($tenant->kyc_status === $k)>{{ $k->label() }}</option>@endforeach</select></div>
                            <div class="col-md-6"><label class="form-label small" for="kyc_notes">{{ __('Notes') }}</label><input type="text" name="kyc_notes" id="kyc_notes" value="{{ $tenant->kyc_notes }}" class="form-control form-control-sm" maxlength="1000"></div>
                            <div class="col-md-2"><button class="btn btn-sm btn-ink w-100">{{ __('Save') }}</button></div>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card panel-card mb-3"><div class="card-header">{{ __('Business details') }}</div><div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted-2 fw-semibold">{{ __('Legal name') }}</dt><dd class="col-7">{{ $tenant->legal_name ?: '—' }}</dd>
                    <dt class="col-5 text-muted-2 fw-semibold">{{ __('Email') }}</dt><dd class="col-7 text-break">{{ $tenant->email }}</dd>
                    <dt class="col-5 text-muted-2 fw-semibold">{{ __('Phone') }}</dt><dd class="col-7">{{ $tenant->phone }}</dd>
                    <dt class="col-5 text-muted-2 fw-semibold">{{ __('Address') }}</dt><dd class="col-7">{{ collect([$tenant->address_line, $tenant->city, $tenant->state, $tenant->pincode])->filter()->join(', ') ?: '—' }}</dd>
                    <dt class="col-5 text-muted-2 fw-semibold">GSTIN</dt><dd class="col-7">{{ $tenant->gstin ?: '—' }}</dd>
                    <dt class="col-5 text-muted-2 fw-semibold">PAN</dt><dd class="col-7">{{ $tenant->pan ?: '—' }}</dd>
                    <dt class="col-5 text-muted-2 fw-semibold">{{ __('Fleet') }}</dt><dd class="col-7">{{ __(':v vehicles · :d drivers', ['v' => $usage['vehicles'], 'd' => $usage['drivers']]) }}</dd>
                    <dt class="col-5 text-muted-2 fw-semibold">{{ __('Team') }}</dt><dd class="col-7">{{ __(':u staff · :b branches', ['u' => $usage['users'], 'b' => $usage['branches']]) }}</dd>
                    @if ($tenant->approved_at)<dt class="col-5 text-muted-2 fw-semibold">{{ __('Approved') }}</dt><dd class="col-7">{{ $tenant->approved_at->format('d M Y') }}</dd>@endif
                </dl>
            </div></div>
            <div class="card panel-card mb-3"><div class="card-header">{{ __('Users') }}</div>
                <div class="list-group list-group-flush list-modern small">
                    @foreach ($tenant->users as $u)
                        <div class="list-group-item d-flex gap-2 align-items-center">
                            <span class="avatar" style="width:2rem;height:2rem">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                            <div class="min-w-0"><div class="fw-semibold text-truncate">{{ $u->name }}</div><div class="text-muted-2 text-truncate">{{ $u->tenantRoles()->pluck('name')->join(', ') ?: $u->type->label() }} · {{ $u->last_login_at?->diffForHumans() ?? __('never logged in') }}</div></div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card panel-card"><div class="card-header">{{ __('Activity') }}</div>
                <div class="list-group list-group-flush list-modern small">
                    @forelse ($activity as $log)
                        <div class="list-group-item d-flex gap-2"><x-icon :name="\App\Support\AuditPresenter::icon($log->event)" class="text-muted-2 mt-1" /><div><span class="fw-semibold">{{ \App\Support\AuditPresenter::label($log->event) }}</span><div class="text-muted-2">{{ $log->user?->name ?? __('System') }} · {{ $log->created_at->format('d M Y, h:i A') }}</div></div></div>
                    @empty
                        <x-empty-state icon="activity" :title="__('No activity yet')" />
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Modals ===== --}}
    @if ($canManage)
        <x-modal id="rejectModal" :title="__('Reject operator')" :action="route('admin.tenants.reject', $tenant)" :submit="__('Reject')" danger>
            <label class="form-label" for="reject_reason">{{ __('Reason (shown to the operator)') }}</label>
            <textarea name="reason" id="reject_reason" rows="3" class="form-control" required maxlength="500"></textarea>
        </x-modal>
        <x-modal id="suspendModal" :title="__('Suspend operator')" :action="route('admin.tenants.suspend', $tenant)" :submit="__('Suspend')" danger>
            <p class="small text-muted-2">{{ __('All users of this operator will lose access until reactivated. Data is kept.') }}</p>
            <label class="form-label" for="suspend_reason">{{ __('Reason') }}</label>
            <textarea name="reason" id="suspend_reason" rows="3" class="form-control" required maxlength="500"></textarea>
        </x-modal>
    @endif
    @if ($canBill)
        <x-modal id="activateModal" :title="__('Activate / renew plan')" :action="route('admin.subscriptions.activate', $tenant)" :submit="__('Activate')" size="lg">
            <div x-data="{ pay: true, plan: '{{ $plans->first()?->id }}', cycle: 'monthly', prices: @js($plans->mapWithKeys(fn ($p) => [$p->id => ['monthly' => (int) $p->price_monthly, 'yearly' => (int) $p->price_yearly]])) }">
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label" for="a_plan">{{ __('Plan') }}</label><select name="plan_id" id="a_plan" class="form-select" x-model="plan">@foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->name }} — {{ Money::format($p->price_monthly, 0) }}/{{ __('mo') }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label" for="a_cycle">{{ __('Billing cycle') }}</label><select name="billing_cycle" id="a_cycle" class="form-select" x-model="cycle"><option value="monthly">{{ __('Monthly') }}</option><option value="yearly">{{ __('Yearly') }}</option></select></div>
                    <div class="col-12 mt-3"><label class="form-label">{{ __('Start') }}</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check"><input class="form-check-input" type="radio" name="start" value="after_current" id="s_after" @checked($current && $current->billing_cycle !== 'trial')><label class="form-check-label" for="s_after">{{ __('After current period ends (renewal)') }}</label></div>
                            <div class="form-check"><input class="form-check-input" type="radio" name="start" value="now" id="s_now" @checked(! $current || $current->billing_cycle === 'trial')><label class="form-check-label" for="s_now">{{ __('Immediately (replaces current)') }}</label></div>
                        </div>
                    </div>
                    <div class="col-12 mt-3"><div class="form-check form-switch"><input type="hidden" name="record_payment" value="0"><input class="form-check-input" type="checkbox" role="switch" name="record_payment" value="1" id="a_pay" x-model="pay"><label class="form-check-label" for="a_pay">{{ __('Record payment & issue invoice') }}</label></div></div>
                    <template x-if="pay"><div class="row g-2 m-0 p-0 w-100">
                        <div class="col-md-4"><label class="form-label" for="a_amount">{{ __('Amount (excl. GST)') }}</label><input type="number" step="0.01" min="1" name="amount" id="a_amount" class="form-control" :value="prices[plan]?.[cycle]"></div>
                        <div class="col-md-4"><label class="form-label" for="a_method">{{ __('Method') }}</label><select name="method" id="a_method" class="form-select">@foreach ($methods as $m)<option value="{{ $m->value }}">{{ $m->label() }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label" for="a_paid">{{ __('Paid on') }}</label><input type="date" name="paid_at" id="a_paid" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}"></div>
                        <div class="col-12"><label class="form-label" for="a_ref">{{ __('Reference / UTR') }}</label><input type="text" name="reference" id="a_ref" class="form-control" maxlength="100"></div>
                    </div></template>
                    <div class="col-12 mt-2"><label class="form-label" for="a_notes">{{ __('Internal notes') }}</label><input type="text" name="notes" id="a_notes" class="form-control" maxlength="300"></div>
                </div>
            </div>
        </x-modal>
        @if ($current)
            <x-modal id="paymentModal" :title="__('Record payment')" :action="route('admin.subscriptions.payments.store', $current)" :submit="__('Record & issue invoice')">
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label" for="p_amount">{{ __('Amount (excl. GST)') }}</label><input type="number" step="0.01" min="1" name="amount" id="p_amount" class="form-control" value="{{ (int) $current->price ?: '' }}" required></div>
                    <div class="col-md-6"><label class="form-label" for="p_method">{{ __('Method') }}</label><select name="method" id="p_method" class="form-select">@foreach ($methods as $m)<option value="{{ $m->value }}">{{ $m->label() }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label" for="p_paid">{{ __('Paid on') }}</label><input type="date" name="paid_at" id="p_paid" class="form-control" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}"></div>
                    <div class="col-md-6"><label class="form-label" for="p_ref">{{ __('Reference / UTR') }}</label><input type="text" name="reference" id="p_ref" class="form-control" maxlength="100"></div>
                    <div class="col-12"><div class="form-text">{{ __('GST at :p% is added automatically.', ['p' => \App\Support\PlatformSettings::decimal('subscription_gst_percent', '18')]) }}</div></div>
                </div>
            </x-modal>
            <x-modal id="extendModal" :title="__('Extend subscription')" :action="route('admin.subscriptions.extend', $current)" :submit="__('Extend')">
                <label class="form-label" for="e_days">{{ __('Days to add') }}</label>
                <input type="number" name="days" id="e_days" min="1" max="366" value="7" class="form-control mb-3" required>
                <label class="form-label" for="e_reason">{{ __('Reason') }}</label>
                <input type="text" name="reason" id="e_reason" class="form-control" maxlength="200" placeholder="{{ __('e.g. goodwill, payment delay') }}">
            </x-modal>
            <x-modal id="planModal" :title="__('Change plan')" :action="route('admin.subscriptions.change-plan', $current)" :submit="__('Change plan')">
                <p class="small text-muted-2">{{ __('Switches immediately for the rest of the current period (ends :d). No proration is applied.', ['d' => $current->ends_at->format('d M Y')]) }}</p>
                <select name="plan_id" class="form-select" aria-label="{{ __('Plan') }}">@foreach ($plans as $p)<option value="{{ $p->id }}" @disabled($p->id === $current->plan_id)>{{ $p->name }} ({{ __('max :n users', ['n' => $p->max_users]) }})</option>@endforeach</select>
            </x-modal>
            <x-modal id="cancelModal" :title="__('Cancel subscription')" :action="route('admin.subscriptions.cancel', $current)" :submit="__('Cancel subscription')" danger>
                <p class="small text-muted-2">{{ __('The operator loses access to the dashboard immediately. Data is kept.') }}</p>
                <label class="form-label" for="c_reason">{{ __('Reason') }}</label>
                <input type="text" name="reason" id="c_reason" class="form-control" required maxlength="200">
            </x-modal>
        @endif
    @endif
</x-layouts.panel>
