<?php

namespace App\Services\Booking;

use App\Mail\BrandedMail;
use App\Models\Booking;
use App\Models\Tenant;
use App\Services\Messaging\SmsService;
use App\Services\Messaging\WhatsAppService;
use App\Support\Money;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Booking updates to the customer (who may not have an account): SMS / WhatsApp / e-mail when configured. */
class CustomerNotifier
{
    public function __construct(private SmsService $sms, private WhatsAppService $whatsapp) {}

    /** Never throws: a failed message must not break the booking workflow. */
    public function notify(Booking $booking, string $event, array $extra = []): void
    {
        try {
            $this->send($booking, $event, $extra);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function send(Booking $booking, string $event, array $extra): void
    {
        $tenant = Tenant::find($booking->tenant_id);
        if (($tenant?->settings['notify_customers'] ?? true) === false) {
            return;
        }

        $link = $booking->public_token ? route('booking.track', $booking->public_token) : null;
        $text = match ($event) {
            'quoted' => __(':op: booking :no received. Fare :amt. Track: :link', ['op' => $tenant->name, 'no' => $booking->booking_number, 'amt' => Money::format($booking->quoted_total, 0), 'link' => $link]),
            'confirmed' => __(':op: booking :no is confirmed for :at. Track: :link', ['op' => $tenant->name, 'no' => $booking->booking_number, 'at' => $booking->pickup_at->format('d M h:i A'), 'link' => $link]),
            'assigned' => __(':op :no: :driver (:phone) will pick you up on :at in :reg. Share OTP :otp only when the trip starts.', [
                'op' => $tenant->name, 'no' => $booking->booking_number, 'driver' => $extra['driver'], 'phone' => $extra['driver_phone'],
                'at' => $booking->pickup_at->format('d M h:i A'), 'reg' => $extra['vehicle'], 'otp' => $extra['otp'],
            ]),
            'completed' => __('Thank you for riding with :op. Final fare :amt. Rate your trip: :link', ['op' => $tenant->name, 'amt' => Money::format($booking->final_total, 0), 'link' => $link]),
            'cancelled' => __(':op: booking :no has been cancelled.', ['op' => $tenant->name, 'no' => $booking->booking_number]),
            default => null,
        };
        if (! $text) {
            return;
        }

        $context = 'booking.'.$event;
        if ($this->sms->isEnabled()) {
            $this->sms->send($booking->customer_phone, $text, $booking->tenant_id, $context);
        }
        if ($this->whatsapp->canNotify()) {
            $this->whatsapp->notify($booking->customer_phone, $text, $booking->tenant_id, $context);
        }
        if ($booking->customer_email) {
            try {
                Mail::to($booking->customer_email)->queue($this->email($booking, $tenant, $event, $extra, $link));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /** Branded email version of the update (details table + tracking button). */
    private function email(Booking $booking, Tenant $tenant, string $event, array $extra, ?string $link): BrandedMail
    {
        [$heading, $intro] = match ($event) {
            'quoted' => [__('We have received your booking'), __(':op has received your booking. Here is your fare estimate — they will confirm shortly.', ['op' => $tenant->name])],
            'confirmed' => [__('Your booking is confirmed'), __(':op has confirmed your booking.', ['op' => $tenant->name])],
            'assigned' => [__('Your driver is assigned'), __('Your driver and vehicle for this trip are ready. Share the trip OTP with the driver only when the trip starts.')],
            'completed' => [__('Thank you for riding with us'), __('Your trip with :op is complete. We would love to hear how it went.', ['op' => $tenant->name])],
            default => [__('Your booking has been cancelled'), __(':op has cancelled booking :no. If this is unexpected, please call them.', ['op' => $tenant->name, 'no' => $booking->booking_number])],
        };
        $details = array_filter([
            __('Booking') => $booking->booking_number,
            __('Pickup') => $booking->pickup_at->format('D, d M Y · h:i A'),
            __('From') => $booking->pickup_address,
            __('To') => $booking->drop_address,
        ]);
        if ($event === 'assigned') {
            $details += [__('Driver') => $extra['driver'].' · '.$extra['driver_phone'], __('Vehicle') => $extra['vehicle'], __('Trip OTP') => $extra['otp']];
        } elseif ($event === 'completed' && $booking->final_total !== null) {
            $details[__('Final fare')] = Money::format($booking->final_total);
        } elseif (in_array($event, ['quoted', 'confirmed'], true) && $booking->quoted_total !== null) {
            $details[__('Estimated fare')] = Money::format($booking->quoted_total);
        }

        return new BrandedMail(
            subjectLine: $tenant->name.' · '.$booking->booking_number.' · '.$heading,
            heading: $heading,
            lines: [$intro],
            details: $details,
            actionText: $link ? ($event === 'completed' ? __('Rate your trip') : __('Track your booking')) : null,
            actionUrl: $link,
            note: $tenant->phone ? __('Questions? Call :op on :phone.', ['op' => $tenant->name, 'phone' => $tenant->phone]) : null,
            signOff: $tenant->name,
        );
    }
}
