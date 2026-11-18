<?php

namespace App\Http\Controllers\Public;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingReview;
use App\Models\PaymentOrder;
use App\Models\Tenant;
use App\Notifications\OperatorBookingAlert;
use App\Services\Booking\BookingService;
use App\Services\Payments\BookingCheckoutService;
use App\Services\Payments\RazorpayGateway;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/** Customer-facing booking page, authorised by the unguessable public token. */
class BookingTrackController extends Controller
{
    public function __construct(private TenantContext $context, private BookingService $bookings, private BookingCheckoutService $checkout, private RazorpayGateway $gateway) {}

    public function show(string $token): View
    {
        [$booking, $tenant] = $this->resolve($token);
        $booking->load(['category', 'stops', 'trip.vehicle', 'trip.driver', 'review', 'payments']);

        return view('public.booking-track', [
            'booking' => $booking, 'tenant' => $tenant,
            'otp' => $booking->status === BookingStatus::Assigned ? $this->bookings->tripOtp($booking->trip) : null,
            'canCancel' => in_array($booking->status, [BookingStatus::Enquiry, BookingStatus::Quoted, BookingStatus::Confirmed, BookingStatus::Assigned], true),
            'advance' => $this->checkout->available($booking, $tenant) ? $this->checkout->advanceAmount($booking, $tenant) : null,
        ]);
    }

    public function cancel(Request $request, string $token): RedirectResponse
    {
        [$booking, $tenant] = $this->resolve($token);
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        if ($booking->status === BookingStatus::Started || ! $booking->status->isOpen()) {
            return back()->withErrors(['reason' => __('This booking can no longer be cancelled online. Please call the operator.')]);
        }
        $this->bookings->cancel($booking, __('Cancelled by customer: :r', ['r' => $data['reason']]), null);
        $tenant->ownerUser()?->notify(new OperatorBookingAlert('cancelled_by_customer', $booking));

        return back()->with('success', __('Your booking has been cancelled. Any refund is processed as per the cancellation policy.'));
    }

    public function review(Request $request, string $token): RedirectResponse
    {
        [$booking] = $this->resolve($token);
        abort_unless($booking->status === BookingStatus::Completed, 403);
        if ($booking->review()->exists()) {
            return back()->withErrors(['rating' => __('You have already rated this trip.')]);
        }
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:1000']]);
        $review = new BookingReview(['booking_id' => $booking->id, 'rating' => $data['rating'], 'comment' => $data['comment'] ?? null]);
        $review->customer_user_id = $booking->customer_user_id;
        $review->save();

        return back()->with('success', __('Thank you for your feedback!'));
    }

    public function checkout(string $token): JsonResponse
    {
        [$booking, $tenant] = $this->resolve($token);
        try {
            $order = $this->checkout->start($booking, $tenant);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => __('Could not start payment. Please try again.')], 502);
        }

        return response()->json([
            'key' => $this->gateway->keyId(), 'order_id' => $order->gateway_order_id, 'amount' => $order->amountInPaise(), 'currency' => 'INR',
            'name' => $tenant->name, 'description' => __('Advance for :no', ['no' => $booking->booking_number]),
            'prefill' => ['name' => $booking->customer_name, 'email' => $booking->customer_email, 'contact' => '+91'.$booking->customer_phone],
            'theme' => ['color' => $tenant->primary_color ?: '#ffb703'], 'verify_url' => route('booking.track.verify', $token),
        ]);
    }

    public function verify(Request $request, string $token): RedirectResponse
    {
        [$booking, $tenant] = $this->resolve($token);
        $data = $request->validate(['razorpay_order_id' => ['required', 'string', 'max:100'], 'razorpay_payment_id' => ['required', 'string', 'max:100'], 'razorpay_signature' => ['required', 'string', 'max:200']]);
        $order = PaymentOrder::query()->where('gateway_order_id', $data['razorpay_order_id'])->where('booking_id', $booking->id)->firstOrFail();

        if (! $this->gateway->verifyPaymentSignature($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            $order->forceFill(['status' => 'failed', 'failure_reason' => 'Invalid checkout signature'])->save();

            return back()->with('error', __('Payment could not be verified. If money was deducted, it will be reconciled automatically.'));
        }
        if ($this->checkout->fulfil($data['razorpay_order_id'], $data['razorpay_payment_id'], 'checkout')) {
            $tenant->ownerUser()?->notify(new OperatorBookingAlert('paid_online', $booking->refresh()));
        }

        return back()->with('success', __('Payment received. Your booking is confirmed!'));
    }

    /** @return array{0: Booking, 1: Tenant} */
    private function resolve(string $token): array
    {
        abort_unless(strlen($token) === 40, 404);
        $booking = Booking::withoutGlobalScopes()->where('public_token', $token)->firstOrFail();
        $tenant = Tenant::findOrFail($booking->tenant_id);
        $this->context->set($tenant);

        return [Booking::query()->findOrFail($booking->id), $tenant];
    }
}
