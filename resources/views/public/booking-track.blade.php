@php
    use App\Enums\BookingStatus;
    use App\Support\Money;
    $s = $booking->status;
    $snap = $booking->final_snapshot ?? $booking->fare_snapshot;
    $steps = [__('Requested'), __('Confirmed'), __('Driver assigned'), __('On trip'), __('Completed')];
    $order = [BookingStatus::Enquiry->value => 0, BookingStatus::Quoted->value => 0, BookingStatus::Confirmed->value => 1, BookingStatus::Assigned->value => 2, BookingStatus::Started->value => 3, BookingStatus::Completed->value => 4];
    $at = $order[$s->value] ?? -1;
@endphp
<x-layouts.public :title="__('Booking :no', ['no' => $booking->booking_number])" noindex :mobile-cta="false">
    <section class="hero hero-sm">
        <div class="container" style="max-width: 900px">
            <div class="small text-white-50 mb-1">{{ $tenant->name }}</div>
            <h1 class="mb-2" style="font-size: clamp(1.7rem, 4vw, 2.4rem)">{{ $booking->booking_number }}</h1>
            <div class="d-flex flex-wrap gap-2"><x-status-badge :status="$s" /><x-status-badge :status="$booking->payment_status" /></div>
        </div>
    </section>
    <section class="section pt-4">
        <div class="container" style="max-width: 900px">
            @if ($s !== BookingStatus::Cancelled)
                <div class="card panel-card mb-3"><div class="card-body">
                    <div class="row g-2 text-center small">
                        @foreach ($steps as $i => $label)
                            <div class="col"><div class="mx-auto mb-1 rounded-circle d-flex align-items-center justify-content-center {{ $i <= $at ? 'bg-brand' : 'bg-surface-2' }}" style="width:2.2rem;height:2.2rem"><x-icon :name="$i <= $at ? 'check-lg' : 'circle'" class="{{ $i <= $at ? '' : 'text-muted-2' }}" /></div><div class="{{ $i <= $at ? 'fw-semibold' : 'text-muted-2' }}">{{ $label }}</div></div>
                        @endforeach
                    </div>
                </div></div>
            @else
                <div class="alert alert-danger">{{ __('This booking was cancelled.') }} {{ $booking->cancel_reason }}</div>
            @endif

            @if ($otp)
                <div class="card panel-card mb-3 border-warning"><div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <span class="icon-tile"><x-icon name="shield-lock" /></span>
                    <div class="me-auto"><div class="small text-muted-2">{{ __('Your trip OTP — share only with your driver at pickup') }}</div><div class="display-6 fw-800 ls-tight">{{ $otp }}</div></div>
                </div></div>
            @endif

            <div class="row g-3">
                <div class="col-md-7">
                    @if ($booking->trip && in_array($s, [BookingStatus::Assigned, BookingStatus::Started], true))
                        <div class="card panel-card mb-3"><div class="card-header">{{ __('Your driver') }}</div><div class="card-body d-flex align-items-center gap-3">
                            <span class="avatar" style="width:3rem;height:3rem">{{ mb_strtoupper(mb_substr($booking->trip->driver->name, 0, 1)) }}</span>
                            <div class="me-auto"><div class="fw-bold">{{ $booking->trip->driver->name }}</div><div class="small text-muted-2">{{ $booking->trip->vehicle->displayRegistration() }} · {{ $booking->trip->vehicle->make }} {{ $booking->trip->vehicle->model }}</div></div>
                            <a href="tel:+91{{ $booking->trip->driver->phone }}" class="btn btn-primary"><x-icon name="telephone-fill" />{{ __('Call') }}</a>
                        </div></div>
                    @endif
                    <div class="card panel-card mb-3"><div class="card-header">{{ __('Trip details') }}</div>
                        <div class="list-group list-group-flush list-modern small">
                            <div class="list-group-item"><span class="text-muted-2 d-block">{{ __('Pickup') }}</span><b>{{ $booking->pickup_address }}</b><div>{{ $booking->pickup_at->format('D, d M Y · h:i A') }}</div></div>
                            @foreach ($booking->stops as $stop)<div class="list-group-item"><span class="text-muted-2 d-block">{{ __('Stop') }}</span>{{ $stop->address }}</div>@endforeach
                            @if ($booking->drop_address)<div class="list-group-item"><span class="text-muted-2 d-block">{{ __('Drop') }}</span><b>{{ $booking->drop_address }}</b>@if ($booking->return_at)<div>{{ __('Return: :d', ['d' => $booking->return_at->format('d M Y, h:i A')]) }}</div>@endif</div>@endif
                            <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Trip') }}</span><span>{{ $booking->type->label() }} · {{ $booking->category->name }}</span></div>
                        </div>
                    </div>
                    @if ($s === BookingStatus::Completed)
                        <div class="card panel-card mb-3"><div class="card-header">{{ __('Rate your trip') }}</div><div class="card-body">
                            @if ($booking->review)
                                <div class="text-brand fs-5">@for ($i = 1; $i <= 5; $i++)<x-icon :name="$i <= $booking->review->rating ? 'star-fill' : 'star'" />@endfor</div>
                                <div class="small text-muted-2">{{ __('Thanks for your feedback!') }}</div>
                            @else
                                <form method="POST" action="{{ route('booking.track.review', $booking->public_token) }}" x-data="{ r: 5 }">@csrf
                                    <input type="hidden" name="rating" :value="r">
                                    <div class="d-flex gap-1 fs-3 text-brand mb-2" role="radiogroup" aria-label="{{ __('Rating') }}">
                                        @for ($i = 1; $i <= 5; $i++)<button type="button" class="btn p-0 border-0 text-brand" @click="r = {{ $i }}" :aria-pressed="r >= {{ $i }}" aria-label="{{ $i }} {{ __('stars') }}"><span x-show="r >= {{ $i }}"><x-icon name="star-fill" /></span><span x-show="r < {{ $i }}" x-cloak><x-icon name="star" /></span></button>@endfor
                                    </div>
                                    <textarea name="comment" rows="2" maxlength="1000" class="form-control mb-2" placeholder="{{ __('Tell others about your trip (optional)') }}"></textarea>
                                    <button class="btn btn-primary">{{ __('Submit review') }}</button>
                                </form>
                            @endif
                        </div></div>
                    @endif
                </div>
                <div class="col-md-5">
                    <div class="card panel-card mb-3"><div class="card-header">{{ $booking->final_snapshot ? __('Final fare') : __('Fare estimate') }}</div><div class="card-body small">
                        @if ($snap)
                            @foreach ($snap['lines'] as $l)<div class="d-flex justify-content-between py-1"><span>{{ $l['label'] }}</span><span>{{ Money::format($l['amount']) }}</span></div>@endforeach
                            @if ((float) $snap['discount'] > 0)<div class="d-flex justify-content-between py-1 text-success"><span>{{ __('Discount') }}</span><span>− {{ Money::format($snap['discount']) }}</span></div>@endif
                            <div class="d-flex justify-content-between py-1"><span>GST</span><span>{{ Money::format($snap['gst']) }}</span></div>
                            @foreach ($snap['passthrough'] as $l)<div class="d-flex justify-content-between py-1"><span>{{ $l['label'] }}</span><span>{{ Money::format($l['amount']) }}</span></div>@endforeach
                            <div class="d-flex justify-content-between pt-2 mt-1 border-top fs-5 fw-800"><span>{{ __('Total') }}</span><span>{{ Money::format($snap['total'], 0) }}</span></div>
                            @if ((float) $booking->amount_paid > 0)<div class="d-flex justify-content-between pt-2"><span>{{ __('Paid') }}</span><span class="text-success fw-semibold">{{ Money::format($booking->amount_paid) }}</span></div>@endif
                        @else
                            <div class="text-muted-2">{{ __(':op will call you shortly with the fare.', ['op' => $tenant->name]) }}</div>
                        @endif
                    </div>
                    @if ($advance)
                        <div class="card-body border-top" x-data="payNow(@js(route('booking.track.pay', $booking->public_token)))">
                            <button class="btn btn-primary w-100" @click="pay()" :disabled="busy"><x-icon name="lock-fill" />{{ __('Pay advance :amt securely', ['amt' => Money::format($advance, 0)]) }}</button>
                            <div class="small text-danger mt-1" x-show="error" x-text="error" x-cloak></div>
                            <div class="small text-muted-2 mt-1 text-center">UPI · {{ __('Cards') }} · {{ __('Net banking') }}</div>
                            <form method="POST" x-ref="verify" class="d-none">@csrf<input type="hidden" name="razorpay_order_id"><input type="hidden" name="razorpay_payment_id"><input type="hidden" name="razorpay_signature"></form>
                        </div>
                    @endif
                    </div>
                    <div class="card panel-card mb-3"><div class="card-body d-grid gap-2">
                        <div class="fw-semibold">{{ $tenant->name }}</div>
                        <a href="tel:+91{{ $tenant->phone }}" class="btn btn-light border"><x-icon name="telephone" />{{ __('Call operator') }}</a>
                        <a href="https://wa.me/91{{ $tenant->phone }}?text={{ rawurlencode(__('Hi, about booking :no', ['no' => $booking->booking_number])) }}" target="_blank" rel="noopener" class="btn btn-light border"><x-icon name="whatsapp" />WhatsApp</a>
                    </div></div>
                    @if ($canCancel)
                        <details class="small"><summary class="text-danger">{{ __('Cancel booking') }}</summary>
                            <form method="POST" action="{{ route('booking.track.cancel', $booking->public_token) }}" class="mt-2" data-confirm="{{ __('Cancel this booking?') }}">@csrf
                                <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="{{ __('Reason') }}" required maxlength="200" aria-label="{{ __('Reason') }}">
                                <button class="btn btn-sm btn-outline-danger w-100">{{ __('Cancel booking') }}</button>
                                <div class="text-muted-2 mt-1">{{ __('Refunds follow the') }} <a href="{{ route('legal.refund') }}">{{ __('cancellation policy') }}</a>.</div>
                            </form>
                        </details>
                    @endif
                </div>
            </div>
            <p class="small text-muted-2 mt-3 text-center"><x-icon name="bookmark" /> {{ __('Bookmark this page to track your booking.') }} @guest <a href="{{ route('login') }}">{{ __('Login with OTP') }}</a> {{ __('to see all your trips.') }} @endguest</p>
        </div>
    </section>
</x-layouts.public>
