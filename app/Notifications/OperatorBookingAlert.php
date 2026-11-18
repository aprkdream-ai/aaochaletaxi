<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\UsesConfiguredChannels;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Alerts the operator owner about customer actions: new online booking, online payment, cancellation. */
class OperatorBookingAlert extends Notification implements ShouldQueue
{
    use Queueable, UsesConfiguredChannels;

    /** @param 'new_online'|'paid_online'|'cancelled_by_customer' $kind */
    public function __construct(public string $kind, public Booking $booking) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->toText($notifiable))
            ->line($this->booking->customer_name.' · '.$this->booking->customer_phone)
            ->line($this->booking->pickup_address.($this->booking->drop_address ? ' → '.$this->booking->drop_address : ''))
            ->line($this->booking->pickup_at->format('d M Y, h:i A'))
            ->action(__('Open booking'), route('operator.bookings.show', $this->booking));
    }

    public function toText(object $notifiable): string
    {
        $b = $this->booking;

        return match ($this->kind) {
            'new_online' => __('New online booking :no for :at (:amt)', ['no' => $b->booking_number, 'at' => $b->pickup_at->format('d M h:i A'), 'amt' => $b->quoted_total ? Money::format($b->quoted_total, 0) : __('quote needed')]),
            'paid_online' => __('Advance received online for :no', ['no' => $b->booking_number]),
            default => __('Customer cancelled booking :no', ['no' => $b->booking_number]),
        };
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'booking' => $this->booking->booking_number];
    }
}
