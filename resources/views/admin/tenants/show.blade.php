<x-layouts.panel :title="$tenant->name" panel="admin">
    <x-page-header :title="$tenant->name" :subtitle="__('Registered :date', ['date' => $tenant->created_at->format('d M Y, h:i A')])">
        <a href="{{ route('admin.tenants.index') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}</a>
    </x-page-header>

    @php $canManage = auth()->user()->hasPermission('admin.tenants.manage'); @endphp

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">{{ __('Business details') }}</span>
                    <x-status-badge :status="$tenant->status" />
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4">{{ __('Slug / booking page') }}</dt><dd class="col-sm-8"><code>/o/{{ $tenant->slug }}</code></dd>
                        <dt class="col-sm-4">{{ __('Email') }}</dt><dd class="col-sm-8">{{ $tenant->email }}</dd>
                        <dt class="col-sm-4">{{ __('Phone') }}</dt><dd class="col-sm-8">{{ $tenant->phone }}</dd>
                        <dt class="col-sm-4">{{ __('Location') }}</dt><dd class="col-sm-8">{{ collect([$tenant->address_line, $tenant->city, $tenant->state, $tenant->pincode])->filter()->join(', ') ?: '—' }}</dd>
                        <dt class="col-sm-4">{{ __('GSTIN / PAN') }}</dt><dd class="col-sm-8">{{ $tenant->gstin ?: '—' }} / {{ $tenant->pan ?: '—' }}</dd>
                        <dt class="col-sm-4">{{ __('KYC') }}</dt><dd class="col-sm-8">{{ $tenant->kyc_status->label() }} @if($tenant->kyc_notes)<div class="text-body-secondary">{{ $tenant->kyc_notes }}</div>@endif</dd>
                        @if ($tenant->approved_at)<dt class="col-sm-4">{{ __('Approved') }}</dt><dd class="col-sm-8">{{ $tenant->approved_at->format('d M Y, h:i A') }}</dd>@endif
                        @if ($tenant->suspension_reason)<dt class="col-sm-4">{{ __('Reason') }}</dt><dd class="col-sm-8 text-danger">{{ $tenant->suspension_reason }}</dd>@endif
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-transparent fw-semibold">{{ __('Users') }}</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead class="table-light"><tr><th>{{ __('Name') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Type / roles') }}</th><th>{{ __('Last login') }}</th></tr></thead>
                        <tbody>
                            @foreach ($tenant->users as $u)
                                <tr>
                                    <td class="fw-semibold">{{ $u->name }}</td>
                                    <td>{{ $u->email }}<br>{{ $u->phone }}</td>
                                    <td>{{ $u->type->label() }}<br><span class="text-body-secondary">{{ $u->roles->pluck('name')->join(', ') }}</span></td>
                                    <td>{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-transparent fw-semibold">{{ __('Subscriptions') }}</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 small">
                        <thead class="table-light"><tr><th>{{ __('Plan') }}</th><th>{{ __('Cycle') }}</th><th>{{ __('Period') }}</th><th>{{ __('Price') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($tenant->subscriptions as $s)
                                <tr>
                                    <td>{{ $s->plan->name }}</td>
                                    <td class="text-capitalize">{{ $s->billing_cycle }}</td>
                                    <td>{{ $s->starts_at->format('d M Y') }} – {{ $s->ends_at->format('d M Y') }}</td>
                                    <td>{{ \App\Support\Money::format($s->price) }}</td>
                                    <td><x-status-badge :status="$s->status" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-empty-state icon="credit-card" :title="__('No subscriptions')" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            @if ($canManage)
                <div class="card mb-3">
                    <div class="card-header bg-transparent fw-semibold">{{ __('Actions') }}</div>
                    <div class="card-body d-grid gap-3">
                        @if (in_array($tenant->status->value, ['pending', 'rejected']))
                            <form method="POST" action="{{ route('admin.tenants.approve', $tenant) }}" data-confirm="{{ __('Approve and activate this operator?') }}">@csrf
                                <button class="btn btn-success w-100"><i class="bi bi-check2-circle me-1"></i>{{ __('Approve & activate') }}</button>
                            </form>
                        @endif
                        @if ($tenant->status->value === 'pending')
                            <form method="POST" action="{{ route('admin.tenants.reject', $tenant) }}" data-confirm="{{ __('Reject this operator?') }}">@csrf
                                <textarea name="reason" class="form-control form-control-sm mb-2 @error('reason') is-invalid @enderror" rows="2" placeholder="{{ __('Rejection reason') }}" required maxlength="500"></textarea>
                                <button class="btn btn-outline-danger w-100">{{ __('Reject') }}</button>
                            </form>
                        @endif
                        @if ($tenant->status->value === 'active')
                            <form method="POST" action="{{ route('admin.tenants.suspend', $tenant) }}" data-confirm="{{ __('Suspend this operator? Their users will lose access.') }}">@csrf
                                <textarea name="reason" class="form-control form-control-sm mb-2 @error('reason') is-invalid @enderror" rows="2" placeholder="{{ __('Suspension reason') }}" required maxlength="500"></textarea>
                                <button class="btn btn-outline-danger w-100"><i class="bi bi-pause-circle me-1"></i>{{ __('Suspend') }}</button>
                            </form>
                        @endif
                        @if ($tenant->status->value === 'suspended')
                            <form method="POST" action="{{ route('admin.tenants.reactivate', $tenant) }}" data-confirm="{{ __('Reactivate this operator?') }}">@csrf
                                <button class="btn btn-success w-100"><i class="bi bi-play-circle me-1"></i>{{ __('Reactivate') }}</button>
                            </form>
                        @endif
                        @error('status')<div class="alert alert-danger small mb-0">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header bg-transparent fw-semibold">{{ __('KYC review') }}</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.tenants.kyc', $tenant) }}">@csrf
                            <select name="kyc_status" class="form-select form-select-sm mb-2" aria-label="{{ __('KYC status') }}">
                                @foreach ($kycStatuses as $k)
                                    <option value="{{ $k->value }}" @selected($tenant->kyc_status === $k)>{{ $k->label() }}</option>
                                @endforeach
                            </select>
                            <textarea name="kyc_notes" class="form-control form-control-sm mb-2" rows="2" maxlength="1000" placeholder="{{ __('Notes') }}">{{ $tenant->kyc_notes }}</textarea>
                            <button class="btn btn-dark btn-sm w-100">{{ __('Save KYC status') }}</button>
                        </form>
                    </div>
                </div>
            @endif
            <div class="card">
                <div class="card-header bg-transparent fw-semibold">{{ __('Activity') }}</div>
                <div class="list-group list-group-flush small">
                    @forelse ($activity as $log)
                        <div class="list-group-item">
                            <code>{{ $log->event }}</code>
                            <div class="text-body-secondary">{{ $log->user?->name ?? __('System') }} · {{ $log->created_at->format('d M Y, h:i A') }}</div>
                        </div>
                    @empty
                        <x-empty-state icon="activity" :title="__('No activity yet')" />
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.panel>
