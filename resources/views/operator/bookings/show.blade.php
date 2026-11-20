@php
    use App\Enums\BookingStatus;
    use App\Support\Money;
    $u = auth()->user();
    $s = $booking->status;
    $snap = $booking->final_snapshot ?? $booking->fare_snapshot;
    $trip = $booking->trip;
    $trackUrl = $booking->public_token ? route('booking.track', $booking->public_token) : null;
    $waText = rawurlencode(__("Hello :name,\nYour AaoChaleTaxi quote :no\n:from → :to\n:date\nVehicle: :cat\nTotal: :amt (incl. GST)\nReply YES to confirm.", [
        'name' => $booking->customer_name, 'no' => $booking->booking_number, 'from' => $booking->pickup_address, 'to' => $booking->drop_address ?? $booking->type->label(),
        'date' => $booking->pickup_at->format('d M Y, h:i A'), 'cat' => $booking->category->name, 'amt' => Money::format($booking->quoted_total ?? 0, 0),
    ]).($trackUrl ? "\n\n".__('View, track or pay online: :url', ['url' => $trackUrl]) : ''));
@endphp
<x-layouts.panel :title="$booking->booking_number" panel="operator">
    <x-page-header :title="$booking->booking_number" :subtitle="$booking->type->label().' · '.$booking->pickup_at->format('D, d M Y · h:i A')">
        <x-status-badge :status="$s" /><x-status-badge :status="$booking->payment_status" />
        <a href="{{ route('operator.bookings.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
    </x-page-header>
    @if ($errors->any())<div class="alert alert-danger small">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    {{-- Progress --}}
    @php $steps = [BookingStatus::Quoted, BookingStatus::Confirmed, BookingStatus::Assigned, BookingStatus::Started, BookingStatus::Completed]; $order = array_map(fn ($x) => $x->value, $steps); $idx = array_search($s->value, $order, true); @endphp
    @if ($s !== BookingStatus::Cancelled)
        <div class="card panel-card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
            @foreach ($steps as $i => $step)
                <span @class(['badge-soft', 'ok' => $idx !== false && $i <= $idx, 'muted' => $idx === false || $i > $idx])><x-icon :name="$idx !== false && $i <= $idx ? 'check-circle-fill' : 'circle'" /> {{ $step->label() }}</span>
                @if (! $loop->last)<x-icon name="chevron-right" class="text-muted-2" />@endif
            @endforeach
        </div></div>
    @else
        <div class="alert alert-danger"><b>{{ __('Cancelled') }}</b> · {{ $booking->cancelled_at?->format('d M Y, h:i A') }} — {{ $booking->cancel_reason }}</div>
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            {{-- Next action --}}
            @if ($s === BookingStatus::Enquiry && $u->hasPermission('bookings.manage'))
                <div class="card panel-card mb-3 border-warning"><div class="card-body">
                    <div class="fw-bold mb-2">{{ __('Add distance to create a quote') }}</div>
                    <form method="POST" action="{{ route('operator.bookings.quote', $booking) }}" class="d-flex flex-wrap gap-2">@csrf
                        <input type="number" step="0.1" min="1" name="distance_km" class="form-control" style="max-width:180px" placeholder="{{ __('Distance km') }}" required aria-label="{{ __('Distance km') }}">
                        <button class="btn btn-primary">{{ __('Create quote') }}</button>
                    </form>
                </div></div>
            @elseif ($s === BookingStatus::Quoted && $u->hasPermission('bookings.manage'))
                <div class="card panel-card mb-3"><div class="card-body d-flex flex-wrap align-items-center gap-2">
                    <div class="me-auto"><div class="fw-bold">{{ __('Share the quote and confirm when the customer agrees') }}</div><div class="small text-muted-2">{{ __('Quote total :amt', ['amt' => Money::format($booking->quoted_total)]) }}</div></div>
                    <a href="https://wa.me/91{{ $booking->customer_phone }}?text={{ $waText }}" target="_blank" rel="noopener" class="btn btn-light border"><x-icon name="whatsapp" />{{ __('Send on WhatsApp') }}</a>
                    <form method="POST" action="{{ route('operator.bookings.confirm', $booking) }}">@csrf<button class="btn btn-primary"><x-icon name="check2-circle" />{{ __('Mark confirmed') }}</button></form>
                </div></div>
            @endif

            @if ($availability)
                <div class="card panel-card mb-3" id="assign">
                    <div class="card-header">{{ $trip ? __('Reassign vehicle & driver') : __('Assign vehicle & driver') }} <span class="small fw-normal text-muted-2">{{ __('Blocked :a → :b', ['a' => $window[0]->format('d M H:i'), 'b' => $window[1]->format('d M H:i')]) }}</span></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('operator.bookings.assign', $booking) }}" class="row g-2 align-items-end" data-loading>@csrf
                            <div class="col-md-5"><label class="form-label small" for="a_v">{{ __('Vehicle') }}</label>
                                <select name="vehicle_id" id="a_v" class="form-select" required>
                                    <option value="">{{ __('Select vehicle…') }}</option>
                                    @foreach ($availability['vehicles'] as $v)<option value="{{ $v['id'] }}" @disabled($v['busy']) @selected($trip?->vehicle_id === $v['id'])>{{ $v['label'] }}{{ $v['match'] ? '' : ' ('.$v['category'].')' }}{{ $v['busy'] ? ' — '.__('busy') : '' }}</option>@endforeach
                                </select></div>
                            <div class="col-md-5"><label class="form-label small" for="a_d">{{ __('Driver') }}</label>
                                <select name="driver_id" id="a_d" class="form-select" required>
                                    <option value="">{{ __('Select driver…') }}</option>
                                    @foreach ($availability['drivers'] as $d)<option value="{{ $d['id'] }}" @disabled($d['busy']) @selected($trip?->driver_id === $d['id'])>{{ $d['label'] }}{{ $d['busy'] ? ' — '.__('busy') : '' }}</option>@endforeach
                                </select></div>
                            <div class="col-md-2"><button class="btn btn-primary w-100">{{ __('Assign') }}</button></div>
                        </form>
                        @if (! count($availability['vehicles']) || ! count($availability['drivers']))<div class="small text-muted-2 mt-2">{{ __('No free vehicles or drivers? Check availability toggles and document expiry under Fleet.') }}</div>@endif
                    </div>
                </div>
            @endif

            @if ($s === BookingStatus::Assigned && $u->hasPermission('bookings.assign'))
                <div class="card panel-card mb-3"><div class="card-body">
                    <div class="fw-bold mb-2">{{ __('Start trip') }}</div>
                    <form method="POST" action="{{ route('operator.bookings.start', $booking) }}" class="d-flex flex-wrap gap-2">@csrf
                        <input type="text" name="otp" inputmode="numeric" maxlength="4" class="form-control" style="max-width:150px" placeholder="{{ __('Customer OTP') }}" required aria-label="{{ __('Customer OTP') }}">
                        <input type="number" name="start_km" min="0" class="form-control" style="max-width:180px" placeholder="{{ __('Odometer km') }}" aria-label="{{ __('Odometer km') }}">
                        <button class="btn btn-primary"><x-icon name="play-circle" />{{ __('Start') }}</button>
                    </form>
                </div></div>
            @elseif ($s === BookingStatus::Started && $u->hasPermission('bookings.assign'))
                <div class="card panel-card mb-3"><div class="card-body">
                    <div class="fw-bold mb-2">{{ __('Complete trip') }} <span class="small fw-normal text-muted-2">{{ __('Final fare uses the quoted rates with actual km and charges.') }}</span></div>
                    <form method="POST" action="{{ route('operator.bookings.complete', $booking) }}" class="row g-2">@csrf
                        <div class="col-6 col-md-2"><input type="number" name="end_km" min="0" class="form-control" placeholder="{{ __('End km') }}" aria-label="{{ __('End km') }}"></div>
                        <div class="col-6 col-md-2"><input type="number" name="waiting_min" min="0" class="form-control" placeholder="{{ __('Waiting min') }}" aria-label="{{ __('Waiting min') }}"></div>
                        <div class="col-4 col-md-2"><input type="number" name="tolls" min="0" class="form-control" placeholder="{{ __('Tolls ₹') }}" aria-label="{{ __('Tolls') }}" value="{{ $booking->fare_snapshot['inputs']['tolls'] ?? '' }}"></div>
                        <div class="col-4 col-md-2"><input type="number" name="parking" min="0" class="form-control" placeholder="{{ __('Parking ₹') }}" aria-label="{{ __('Parking') }}"></div>
                        <div class="col-4 col-md-2"><input type="number" name="state_tax" min="0" class="form-control" placeholder="{{ __('State tax ₹') }}" aria-label="{{ __('State tax') }}"></div>
                        <div class="col-12 col-md-2"><button class="btn btn-primary w-100"><x-icon name="flag" />{{ __('Complete') }}</button></div>
                    </form>
                    @if ($trip?->start_km)<div class="small text-muted-2 mt-1">{{ __('Start km: :n', ['n' => number_format($trip->start_km)]) }}</div>@endif
                </div></div>
            @endif

            {{-- Fare --}}
            <div class="card panel-card mb-3">
                <div class="card-header">{{ $booking->final_snapshot ? __('Final fare') : __('Quotation') }} @if (! $booking->final_snapshot && $booking->quotations->count())<span class="badge-soft muted">v{{ $booking->quotations->first()->version }}</span>@endif</div>
                <div class="card-body">
                    @if ($snap)
                        <div class="small">
                            @foreach ($snap['lines'] as $l)<div class="d-flex justify-content-between py-1"><span>{{ $l['label'] }} @if ($l['detail'])<span class="text-muted-2">· {{ $l['detail'] }}</span>@endif</span><span class="fw-semibold">{{ Money::format($l['amount']) }}</span></div>@endforeach
                            @if ((float) $snap['discount'] > 0)<div class="d-flex justify-content-between py-1 text-success"><span>{{ __('Discount') }}</span><span>− {{ Money::format($snap['discount']) }}</span></div>@endif
                            <div class="d-flex justify-content-between py-1 border-top"><span>{{ __('GST :p%', ['p' => rtrim(rtrim($snap['gst_percent'], '0'), '.')]) }}</span><span>{{ Money::format($snap['gst']) }}</span></div>
                            @foreach ($snap['passthrough'] as $l)<div class="d-flex justify-content-between py-1"><span>{{ $l['label'] }} <span class="text-muted-2">· {{ $l['detail'] }}</span></span><span>{{ Money::format($l['amount']) }}</span></div>@endforeach
                            @if ((float) $snap['round_off'] != 0)<div class="d-flex justify-content-between py-1 text-muted-2"><span>{{ __('Round off') }}</span><span>{{ Money::format($snap['round_off']) }}</span></div>@endif
                            <div class="d-flex justify-content-between pt-2 border-top fs-5 fw-800"><span>{{ __('Total') }}</span><span>{{ Money::format($snap['total']) }}</span></div>
                        </div>
                    @else
                        <x-empty-state icon="calculator" :title="__('No quotation yet')" />
                    @endif
                    @if ($booking->quoted_total && $booking->status->canMoveTo(BookingStatus::Quoted) && $u->hasPermission('bookings.manage'))
                        <details class="mt-3"><summary class="small text-muted-2">{{ __('Revise quote') }}</summary>
                            <form method="POST" action="{{ route('operator.bookings.quote', $booking) }}" class="row g-2 mt-1">@csrf
                                <div class="col-6 col-md-3"><input type="number" step="0.1" name="distance_km" class="form-control form-control-sm" value="{{ $booking->distance_km }}" placeholder="km" aria-label="km"></div>
                                <div class="col-6 col-md-2"><input type="number" name="tolls" class="form-control form-control-sm" value="{{ $snap['inputs']['tolls'] ?? '' }}" placeholder="{{ __('Tolls') }}" aria-label="{{ __('Tolls') }}"></div>
                                <div class="col-6 col-md-2"><input type="number" name="parking" class="form-control form-control-sm" value="{{ $snap['inputs']['parking'] ?? '' }}" placeholder="{{ __('Parking') }}" aria-label="{{ __('Parking') }}"></div>
                                <div class="col-6 col-md-2"><input type="number" name="discount" class="form-control form-control-sm" value="{{ $snap['inputs']['discount'] ?? '' }}" placeholder="{{ __('Discount') }}" aria-label="{{ __('Discount') }}"></div>
                                <div class="col-12 col-md-3"><button class="btn btn-sm btn-ink w-100">{{ __('Update quote') }}</button></div>
                            </form>
                        </details>
                    @endif
                </div>
            </div>

            {{-- Invoice --}}
            @if ($s === BookingStatus::Completed && ($u->hasPermission('invoices.view') || $u->hasPermission('invoices.manage')))
                @php $inv = app(\App\Services\Finance\InvoiceService::class)->activeInvoice($booking); $docs = \App\Models\Invoice::query()->where('booking_id', $booking->id)->latest('id')->get(); @endphp
                <div class="card panel-card mb-3">
                    <div class="card-header">{{ __('Invoice') }} @if ($inv)<span class="badge-soft ok">{{ $inv->number }}</span>@endif</div>
                    <div class="card-body">
                        @if ($inv)
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('operator.invoices.show', $inv) }}" class="btn btn-sm btn-light border"><x-icon name="eye" />{{ __('View') }}</a>
                                <a href="{{ route('operator.invoices.pdf', $inv) }}" class="btn btn-sm btn-light border"><x-icon name="download" />PDF</a>
                                <a href="https://wa.me/91{{ $booking->customer_phone }}?text={{ rawurlencode(__('Your invoice :no: :url', ['no' => $inv->number, 'url' => route('booking.track.invoice', $booking->public_token)])) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><x-icon name="whatsapp" />{{ __('Send') }}</a>
                                @if ($u->hasPermission('invoices.manage'))
                                    <details class="ms-auto"><summary class="small text-danger">{{ __('Correct (credit note)') }}</summary>
                                        <form method="POST" action="{{ route('operator.invoices.credit-note', $inv) }}" class="d-flex gap-2 mt-2" data-confirm="{{ __('Cancel this invoice with a credit note?') }}">@csrf<input type="text" name="reason" class="form-control form-control-sm" placeholder="{{ __('Reason') }}" required maxlength="200" aria-label="{{ __('Reason') }}"><button class="btn btn-sm btn-outline-danger">{{ __('Issue') }}</button></form>
                                    </details>
                                @endif
                            </div>
                        @elseif ($u->hasPermission('invoices.manage'))
                            <form method="POST" action="{{ route('operator.invoices.store', $booking) }}" class="row g-2 align-items-end" data-loading>@csrf
                                <div class="col-md-4"><label class="form-label small" for="i_name">{{ __('Bill to (name / company)') }}</label><input type="text" name="name" id="i_name" value="{{ $booking->customer_name }}" class="form-control form-control-sm" maxlength="150"></div>
                                <div class="col-md-3"><label class="form-label small" for="i_gstin">{{ __('Customer GSTIN (B2B)') }}</label><input type="text" name="gstin" id="i_gstin" class="form-control form-control-sm" maxlength="15" style="text-transform:uppercase"></div>
                                <div class="col-md-3"><label class="form-label small" for="i_state">{{ __('State') }}</label><input type="text" name="state" id="i_state" class="form-control form-control-sm" maxlength="60"></div>
                                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">{{ __('Issue invoice') }}</button></div>
                                <div class="col-12"><div class="form-check small"><input class="form-check-input" type="checkbox" name="inter_state" value="1" id="i_inter"><label class="form-check-label" for="i_inter">{{ __('Inter-state supply (IGST instead of CGST + SGST)') }}</label></div></div>
                            </form>
                            @error('gstin')<div class="text-danger small">{{ $message }}</div>@enderror
                            @error('invoice')<div class="text-danger small">{{ $message }}</div>@enderror
                        @endif
                        @if ($docs->count() > ($inv ? 1 : 0))
                            <div class="small text-muted-2 mt-2">{{ __('History') }}: @foreach ($docs as $d)<a href="{{ route('operator.invoices.show', $d) }}">{{ $d->number }}</a>@if ($d->isCreditNote()) (CN)@elseif ($d->status === 'credited') ({{ __('credited') }})@endif{{ $loop->last ? '' : ', ' }}@endforeach</div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Payments --}}
            <div class="card panel-card mb-3">
                <div class="card-header">{{ __('Payments') }} <span class="small fw-normal">{{ __('Received :p · Balance :b', ['p' => Money::format($booking->amount_paid), 'b' => Money::format($booking->balance())]) }}</span></div>
                @if ($u->hasPermission('payments.manage') && $s !== BookingStatus::Enquiry)
                    <div class="card-body border-bottom">
                        <form method="POST" action="{{ route('operator.bookings.payments.store', $booking) }}" class="row g-2" data-loading>@csrf
                            <div class="col-6 col-md-2"><select name="kind" class="form-select form-select-sm" aria-label="{{ __('Type') }}"><option value="advance">{{ __('Advance') }}</option><option value="balance" @selected($s === BookingStatus::Completed)>{{ __('Balance') }}</option><option value="refund">{{ __('Refund') }}</option></select></div>
                            <div class="col-6 col-md-2"><input type="number" step="0.01" min="1" name="amount" class="form-control form-control-sm" placeholder="₹" required aria-label="{{ __('Amount') }}" value="{{ (float) $booking->balance() > 0 ? (float) $booking->balance() : '' }}"></div>
                            <div class="col-6 col-md-2"><select name="method" class="form-select form-select-sm" aria-label="{{ __('Method') }}"><option value="upi">UPI</option><option value="cash">{{ __('Cash') }}</option><option value="card">{{ __('Card') }}</option><option value="bank_transfer">{{ __('Bank') }}</option><option value="other">{{ __('Other') }}</option></select></div>
                            <div class="col-6 col-md-4"><input type="text" name="reference" class="form-control form-control-sm" placeholder="{{ __('Reference / UTR') }}" maxlength="100" aria-label="{{ __('Reference') }}"></div>
                            <div class="col-12 col-md-2"><button class="btn btn-sm btn-primary w-100">{{ __('Record') }}</button></div>
                        </form>
                    </div>
                @endif
                <div class="table-responsive"><table class="table table-modern align-middle small">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Method') }}</th><th class="d-none d-md-table-cell">{{ __('By') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                        @forelse ($booking->payments as $p)
                            <tr><td class="text-nowrap">{{ $p->paid_at->format('d M') }}<div class="text-muted-2">{{ $p->paid_at->format('h:i A') }}</div></td><td class="text-capitalize">{{ $p->kind }}</td><td class="text-uppercase">{{ $p->method }}@if ($p->reference)<div class="text-muted-2 text-none text-break" style="max-width:9rem">{{ $p->reference }}</div>@endif</td><td class="d-none d-md-table-cell">{{ $p->recorder?->name }}</td><td class="text-end fw-semibold {{ (float) $p->amount < 0 ? 'text-danger' : '' }}">{{ Money::format($p->amount) }}</td></tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state icon="wallet2" :title="__('No payments yet')" /></td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card panel-card mb-3"><div class="card-header">{{ __('Trip') }}</div>
                <div class="list-group list-group-flush list-modern small">
                    <div class="list-group-item"><span class="text-muted-2 d-block">{{ __('Pickup') }}</span><b>{{ $booking->pickup_address }}</b><div>{{ $booking->pickup_at->format('d M Y, h:i A') }}</div></div>
                    @foreach ($booking->stops as $stop)<div class="list-group-item"><span class="text-muted-2 d-block">{{ __('Stop :n', ['n' => $stop->sequence]) }}</span>{{ $stop->address }}</div>@endforeach
                    @if ($booking->drop_address)<div class="list-group-item"><span class="text-muted-2 d-block">{{ __('Drop') }}</span><b>{{ $booking->drop_address }}</b>@if ($booking->return_at)<div>{{ __('Return: :d', ['d' => $booking->return_at->format('d M Y, h:i A')]) }}</div>@endif</div>@endif
                    <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Vehicle type') }}</span><span>{{ $booking->category->name }} · {{ trans_choice('{1} 1 passenger|[2,*] :n passengers', $booking->passengers, ['n' => $booking->passengers]) }}</span></div>
                    @if ($booking->distance_km)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Distance') }}</span><span>{{ rtrim(rtrim((string) $booking->distance_km, '0'), '.') }} km</span></div>@endif
                    @if ($booking->rental_hours)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Hours') }}</span><span>{{ $booking->rental_hours }}</span></div>@endif
                    @if ($trip)
                        <div class="list-group-item"><span class="text-muted-2 d-block">{{ __('Assigned') }}</span><a href="{{ route('operator.vehicles.show', $trip->vehicle) }}" class="fw-semibold">{{ $trip->vehicle->displayRegistration() }}</a> · {{ $trip->vehicle->make }} {{ $trip->vehicle->model }}<br><a href="{{ route('operator.drivers.show', $trip->driver) }}">{{ $trip->driver->name }}</a> · <a href="tel:+91{{ $trip->driver->phone }}">{{ $trip->driver->phone }}</a></div>
                        @if ($trip->status === 'started' && $trip->last_location_at)<div class="list-group-item d-flex justify-content-between align-items-center"><span class="text-muted-2">{{ __('Last location') }}</span><a href="https://www.google.com/maps/search/?api=1&query={{ $trip->last_lat }},{{ $trip->last_lng }}" target="_blank" rel="noopener"><x-icon name="geo-alt-fill" /> {{ $trip->last_location_at->diffForHumans() }}</a></div>@endif
                        @if ($trip->started_at)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Started') }}</span><span>{{ $trip->started_at->format('d M, h:i A') }}@if ($trip->start_km) · {{ number_format($trip->start_km) }} km @endif</span></div>@endif
                        @if ($trip->completed_at)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Completed') }}</span><span>{{ $trip->completed_at->format('d M, h:i A') }}@if ($trip->end_km) · {{ number_format($trip->end_km) }} km @endif</span></div>@endif
                    @endif
                    @if ($booking->notes)<div class="list-group-item"><span class="text-muted-2 d-block">{{ __('Notes') }}</span>{{ $booking->notes }}</div>@endif
                </div>
            </div>
            <div class="card panel-card mb-3"><div class="card-header">{{ __('Customer') }}</div><div class="card-body">
                <div class="fw-bold">{{ $booking->customer_name }}</div>
                <div class="small text-muted-2 mb-2">{{ $booking->customer_phone }}@if ($booking->customer_email) · {{ $booking->customer_email }}@endif</div>
                <div class="d-flex gap-2"><a href="tel:+91{{ $booking->customer_phone }}" class="btn btn-sm btn-light border"><x-icon name="telephone" />{{ __('Call') }}</a><a href="https://wa.me/91{{ $booking->customer_phone }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><x-icon name="whatsapp" />WhatsApp</a></div>
                @if ($trackUrl)
                    <div class="mt-3 pt-3 border-top" x-data="{ copied: false }">
                        <div class="small fw-semibold mb-1">{{ __('Customer link') }} <span class="text-muted-2 fw-normal">· {{ __('track, OTP, pay advance, invoice') }}</span></div>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" readonly value="{{ $trackUrl }}" x-ref="link" aria-label="{{ __('Customer link') }}">
                            <button class="btn btn-ink" type="button" @click="navigator.clipboard?.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 2000)" aria-label="{{ __('Copy link') }}"><span x-show="!copied"><x-icon name="clipboard" /></span><span x-show="copied" x-cloak><x-icon name="check2" /></span></button>
                            <a href="{{ $trackUrl }}" target="_blank" rel="noopener" class="btn btn-light border" aria-label="{{ __('Open') }}"><x-icon name="box-arrow-up-right" /></a>
                        </div>
                    </div>
                @endif
            </div></div>
            <div class="card panel-card mb-3"><div class="card-header">{{ __('Timeline') }}</div>
                <div class="list-group list-group-flush list-modern small">
                    @foreach ($booking->statusLogs as $log)
                        <div class="list-group-item"><div class="d-flex justify-content-between"><span class="fw-semibold">{{ \App\Enums\BookingStatus::from($log->to_status)->label() }}</span><span class="text-muted-2">{{ $log->created_at->format('d M, h:i A') }}</span></div><div class="text-muted-2">{{ $log->note }} · {{ $log->user?->name }}</div></div>
                    @endforeach
                </div>
            </div>
            @if ($s->canMoveTo(BookingStatus::Cancelled) && $u->hasPermission('bookings.manage'))
                <details class="small"><summary class="text-danger">{{ __('Cancel booking') }}</summary>
                    <form method="POST" action="{{ route('operator.bookings.cancel', $booking) }}" class="mt-2" data-confirm="{{ __('Cancel this booking? The vehicle and driver will be released.') }}">@csrf
                        <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="{{ __('Reason (required)') }}" required maxlength="255" aria-label="{{ __('Reason') }}">
                        <button class="btn btn-sm btn-outline-danger w-100">{{ __('Cancel booking') }}</button>
                    </form>
                </details>
            @endif
        </div>
    </div>
</x-layouts.panel>
