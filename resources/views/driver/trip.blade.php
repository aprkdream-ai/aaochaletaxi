@php
    use App\Enums\BookingStatus;
    use App\Support\Money;
    $s = $booking->status;
    $phone = preg_replace('/\D/', '', (string) $booking->customer_phone);
    $phone = strlen($phone) === 10 ? '91'.$phone : $phone;
@endphp
<x-layouts.driver :title="$booking->booking_number" :back="route('driver.home')">
    <div class="card panel-card mb-3">
        <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-2">
                <x-status-badge :status="$s" />
                <span class="small text-muted-2">{{ $booking->type->label() }} · {{ $booking->category?->name }}</span>
            </div>
            <div class="fs-5 fw-bold">{{ $trip->start_at->format('D, d M · h:i A') }}</div>
            @if ($booking->return_at)<div class="small text-muted-2">{{ __('Return: :d', ['d' => $booking->return_at->format('d M, h:i A')]) }}</div>@endif
            <div class="route-line mt-3">
                <div><x-icon name="circle-fill" class="text-success" /><span>{{ $booking->pickup_address }}</span></div>
                @foreach ($booking->stops as $stop)<div><x-icon name="record-circle" class="text-muted-2" /><span>{{ $stop->address }}</span></div>@endforeach
                @if ($booking->drop_address)<div><x-icon name="geo-alt-fill" class="text-danger" /><span>{{ $booking->drop_address }}</span></div>@endif
            </div>
            @if ($booking->notes)<div class="alert alert-light border small mt-3 mb-0"><x-icon name="info-circle" /> {{ $booking->notes }}</div>@endif
        </div>
        @if ($s !== BookingStatus::Completed)
            <div class="trip-actions p-3 pt-0">
                <a href="tel:+{{ $phone }}" class="btn btn-light border"><x-icon name="telephone-fill" /><span class="d-block small">{{ __('Call') }}</span></a>
                <a href="https://wa.me/{{ $phone }}" target="_blank" rel="noopener" class="btn btn-light border"><x-icon name="whatsapp" class="text-success" /><span class="d-block small">WhatsApp</span></a>
                <a href="{{ $mapsUrl }}" target="_blank" rel="noopener" class="btn btn-primary"><x-icon name="sign-turn-right-fill" /><span class="d-block small">{{ __('Navigate') }}</span></a>
            </div>
        @endif
    </div>

    <div class="card panel-card mb-3">
        <div class="list-group list-group-flush list-modern small">
            <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Customer') }}</span><span class="fw-semibold">{{ $booking->customer_name }}</span></div>
            <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Passengers') }}</span><span>{{ $booking->passengers ?: '—' }}</span></div>
            <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Vehicle') }}</span><span>{{ $trip->vehicle?->displayRegistration() }} · {{ $trip->vehicle?->make }} {{ $trip->vehicle?->model }}</span></div>
            @if ($booking->distance_km)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Estimated distance') }}</span><span>{{ $booking->distance_km }} km</span></div>@endif
            @if ($trip->started_at)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Started') }}</span><span>{{ $trip->started_at->format('d M, h:i A') }} · {{ number_format($trip->start_km) }} km</span></div>@endif
            @if ($trip->completed_at)<div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Completed') }}</span><span>{{ $trip->completed_at->format('d M, h:i A') }} · {{ number_format($trip->end_km) }} km</span></div>@endif
            @if ($s === BookingStatus::Completed)
                <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Final fare') }}</span><span class="fw-bold">{{ Money::format($booking->final_total, 0) }}</span></div>
                <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('To collect') }}</span><span class="fw-bold text-success">{{ Money::format($booking->balance(), 0) }}</span></div>
            @endif
        </div>
    </div>

    @if ($s === BookingStatus::Assigned)
        <form method="POST" action="{{ route('driver.trips.start', $trip) }}" class="card panel-card mb-3">@csrf
            <div class="card-body">
                <div class="fw-bold mb-1">{{ __('Start trip') }}</div>
                <div class="small text-muted-2 mb-3">{{ __('Ask the customer for the 4-digit OTP sent to their phone.') }}</div>
                <label class="form-label small" for="otp">{{ __('Customer OTP') }}</label>
                <input id="otp" name="otp" class="form-control form-control-lg otp-input text-center mb-3 @error('otp') is-invalid @enderror" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="one-time-code" required value="{{ old('otp') }}">
                @error('otp')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                <label class="form-label small" for="start_km">{{ __('Odometer reading (km)') }}</label>
                <input id="start_km" name="start_km" type="number" min="0" inputmode="numeric" class="form-control form-control-lg @error('start_km') is-invalid @enderror" required value="{{ old('start_km') }}">
                @error('start_km')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <button class="btn btn-success btn-lg w-100 mt-3"><x-icon name="play-fill" />{{ __('Start trip') }}</button>
            </div>
        </form>
    @elseif ($s === BookingStatus::Started)
        <div class="small text-muted-2 mb-2 d-flex align-items-center gap-2" x-data="tripTracker(@js(route('driver.trips.location', $trip)))" x-init="start()">
            <x-icon name="broadcast" /><span x-text="status">{{ __('Sharing live location with your operator during this trip.') }}</span>
        </div>
        <form method="POST" action="{{ route('driver.trips.complete', $trip) }}" class="card panel-card mb-3" x-data="{ more: {{ $errors->hasAny(['tolls', 'parking', 'state_tax', 'waiting_min']) ? 'true' : 'false' }} }">@csrf
            <div class="card-body">
                <div class="fw-bold mb-3">{{ __('Complete trip') }}</div>
                <label class="form-label small" for="end_km">{{ __('Closing odometer (km)') }}</label>
                <input id="end_km" name="end_km" type="number" min="{{ $trip->start_km }}" inputmode="numeric" class="form-control form-control-lg @error('end_km') is-invalid @enderror" required value="{{ old('end_km') }}">
                <div class="form-text">{{ __('Started at :n km', ['n' => number_format($trip->start_km)]) }}</div>
                @error('end_km')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <button type="button" class="btn btn-link px-0 small" @click="more = !more"><x-icon name="plus-circle" />{{ __('Tolls, parking, waiting') }}</button>
                <template x-if="more">
                    <div class="row g-2">
                        <div class="col-6"><x-input name="tolls" type="number" step="0.01" min="0" :label="__('Tolls (₹)')" inputmode="decimal" /></div>
                        <div class="col-6"><x-input name="parking" type="number" step="0.01" min="0" :label="__('Parking (₹)')" inputmode="decimal" /></div>
                        <div class="col-6"><x-input name="state_tax" type="number" step="0.01" min="0" :label="__('State tax (₹)')" inputmode="decimal" /></div>
                        <div class="col-6"><x-input name="waiting_min" type="number" min="0" :label="__('Waiting (min)')" inputmode="numeric" /></div>
                    </div>
                </template>
                <button class="btn btn-ink btn-lg w-100 mt-3" onclick="return confirm(@js(__('Complete this trip?')))"><x-icon name="flag-fill" />{{ __('Complete trip') }}</button>
            </div>
        </form>
    @endif
</x-layouts.driver>
