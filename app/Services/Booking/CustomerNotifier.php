<?php

namespace App\Services\Booking;

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
                Mail::raw($text, fn ($m) => $m->to($booking->customer_email)->subject($tenant->name.' · '.$booking->booking_number));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
