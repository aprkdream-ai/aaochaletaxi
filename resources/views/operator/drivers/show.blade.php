@php use App\Support\FleetOptions; $canManage = auth()->user()->hasPermission('drivers.manage'); $lic = $driver->licence(); @endphp
<x-layouts.panel :title="$driver->name" panel="operator">
    <x-page-header :title="$driver->name" :subtitle="$driver->phone.($driver->branch ? ' · '.$driver->branch->name : '')">
        <a href="{{ route('operator.drivers.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
        @if ($canManage)
            <form method="POST" action="{{ route('operator.drivers.availability', $driver) }}">@csrf<button class="btn btn-light border"><x-icon :name="$driver->is_available ? 'pause-circle' : 'play-circle'" />{{ $driver->is_available ? __('Mark off duty') : __('Mark available') }}</button></form>
            <a href="{{ route('operator.drivers.edit', $driver) }}" class="btn btn-ink"><x-icon name="pencil" />{{ __('Edit') }}</a>
        @endif
    </x-page-header>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card panel-card mb-3"><div class="card-body text-center">
                <span class="avatar overflow-hidden mx-auto mb-2" style="width:84px;height:84px;font-size:1.8rem">@if ($driver->photoUrl())<img src="{{ $driver->photoUrl() }}" alt="" class="w-100 h-100" style="object-fit:cover">@else{{ mb_strtoupper(mb_substr($driver->name, 0, 1)) }}@endif</span>
                <div class="fw-800 fs-5">{{ $driver->name }}</div>
                <div class="d-flex justify-content-center flex-wrap gap-2 my-2"><x-status-badge :status="$driver->status" /><span class="badge-soft {{ $driver->is_available ? 'ok' : 'muted' }}">{{ $driver->is_available ? __('Available') : __('Off duty') }}</span></div>
                <div class="d-flex justify-content-center gap-2">
                    <a href="tel:+91{{ $driver->phone }}" class="btn btn-sm btn-light border"><x-icon name="telephone" />{{ __('Call') }}</a>
                    <a href="https://wa.me/91{{ $driver->phone }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><x-icon name="whatsapp" />WhatsApp</a>
                </div>
            </div>
            <div class="list-group list-group-flush list-modern small">
                <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Licence') }}</span><span class="text-end"><b>{{ $driver->licence_number }}</b> {{ $driver->licence_type }}<br><x-expiry-badge :expiry="$lic" /></span></div>
                @if ($driver->aadhaar_last4)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">Aadhaar</span><span>XXXX XXXX {{ $driver->aadhaar_last4 }}</span></div>@endif
                @if ($driver->date_of_birth)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Age') }}</span><span>{{ $driver->date_of_birth->age }}</span></div>@endif
                @if ($driver->joined_on)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Joined') }}</span><span>{{ $driver->joined_on->format('d M Y') }}</span></div>@endif
                <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Pay') }}</span><span>{{ FleetOptions::payTypes()[$driver->pay_type] ?? '' }}@if ($driver->pay_amount) · {{ $driver->pay_type === 'commission' ? rtrim(rtrim((string) $driver->pay_amount, '0'), '.').'%' : \App\Support\Money::format($driver->pay_amount, 0) }}@endif</span></div>
                @if ($driver->alt_phone)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Alternate') }}</span><span>{{ $driver->alt_phone }}</span></div>@endif
                @if ($driver->address)<div class="list-group-item"><span class="text-muted-2 d-block">{{ __('Address') }}</span>{{ $driver->address }}</div>@endif
            </div></div>
        </div>
        <div class="col-lg-8">
            <div class="card panel-card mb-3">
                <div class="card-header"><span class="d-flex align-items-center gap-2"><x-icon name="phone" />{{ __('Driver app access') }}</span>
                    @if ($driver->user)<span class="badge-soft {{ $driver->user->status->value === 'active' ? 'ok' : 'muted' }}">{{ $driver->user->status->value === 'active' ? __('Enabled') : __('Disabled') }}</span>@endif
                </div>
                <div class="card-body">
                    @if ($driver->user && $driver->user->status->value === 'active')
                        <p class="small mb-3">{{ __('Login: :phone · last login :t', ['phone' => $driver->phone, 't' => $driver->user->last_login_at?->diffForHumans() ?? __('never')]) }}</p>
                    @else
                        <p class="small text-muted-2 mb-3">{{ __('Give the driver a login for the AaoChaleTaxi driver app (trips, OTP start, earnings).') }}</p>
                    @endif
                    @if ($canManage)
                        <form method="POST" action="{{ route('operator.drivers.app-access', $driver) }}" class="row g-2 align-items-end" data-loading>@csrf
                            <div class="col-md-5"><label class="form-label small" for="ap">{{ $driver->user ? __('New password') : __('Password') }}</label><input type="password" name="app_password" id="ap" class="form-control form-control-sm @error('app_password') is-invalid @enderror" autocomplete="new-password" required>@error('app_password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-md-4"><label class="form-label small" for="apc">{{ __('Confirm') }}</label><input type="password" name="app_password_confirmation" id="apc" class="form-control form-control-sm" autocomplete="new-password" required></div>
                            <div class="col-md-3"><button class="btn btn-sm btn-primary w-100">{{ $driver->user && $driver->user->status->value === 'active' ? __('Reset password') : __('Enable access') }}</button></div>
                        </form>
                        @error('phone')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        @if ($driver->user && $driver->user->status->value === 'active')
                            <form method="POST" action="{{ route('operator.drivers.app-access', $driver) }}" class="mt-2" data-confirm="{{ __('Disable app login for this driver?') }}">@csrf<input type="hidden" name="disable" value="1"><button class="btn btn-sm btn-link text-danger p-0">{{ __('Disable access') }}</button></form>
                        @endif
                    @endif
                </div>
            </div>
            <div class="card panel-card">
                <div class="card-header">{{ __('Documents') }}</div>
                @if ($canManage)<div class="card-body border-bottom"><x-doc-uploader :action="route('operator.drivers.documents.store', $driver)" :types="FleetOptions::driverDocuments()" /><div class="form-text">{{ __('A licence document with a validity date also updates the licence expiry.') }}</div></div>@endif
                <div class="list-group list-group-flush list-modern">
                    @forelse ($driver->documents as $doc)
                        <div class="list-group-item d-flex flex-wrap align-items-center gap-3">
                            <span class="stat-ico tone-secondary"><x-icon :name="$doc->isImage() ? 'file-earmark-image' : 'file-earmark-pdf'" /></span>
                            <div class="me-auto min-w-0"><div class="fw-semibold">{{ FleetOptions::driverDocuments()[$doc->type] ?? $doc->type }} @if ($doc->number)<span class="text-muted-2 fw-normal">· {{ $doc->number }}</span>@endif</div><div class="small text-muted-2 text-truncate">{{ $doc->original_name }} · {{ $doc->created_at->format('d M Y') }}</div></div>
                            @if ($doc->expires_on)<x-expiry-badge :expiry="$doc->expiry()" />@endif
                            <a href="{{ route('operator.fleet-documents.show', $doc) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border" aria-label="{{ __('View') }}"><x-icon name="eye" /></a>
                            @if ($canManage)<form method="POST" action="{{ route('operator.fleet-documents.destroy', $doc) }}" data-confirm="{{ __('Delete this document?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" aria-label="{{ __('Delete') }}"><x-icon name="trash" /></button></form>@endif
                        </div>
                    @empty
                        <x-empty-state icon="folder2-open" :title="__('No documents yet')" :text="__('Upload licence, Aadhaar, PAN and police verification.')" />
                    @endforelse
                </div>
            </div>
            @if ($canManage)
                <form method="POST" action="{{ route('operator.drivers.destroy', $driver) }}" class="mt-4 text-end" data-confirm="{{ __('Remove this driver? Their app login will be disabled.') }}">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><x-icon name="trash" />{{ __('Remove driver') }}</button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.panel>
