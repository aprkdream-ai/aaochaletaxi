<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Driver;
use App\Notifications\Channels\FcmChannel;
use App\Services\Push\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Sent synchronously (database row is what the driver app polls for); push when Firebase is configured. */
class DriverTripAssigned extends Notification
{
    use Queueable;

    public function __construct(public Booking $booking, public ?string $fcmToken = null) {}

    public function via(object $notifiable): array
    {
        return array_values(array_filter(['database', $this->fcmToken && app(FcmService::class)->isEnabled() ? FcmChannel::class : null]));
    }

    public function fcmToken(object $notifiable): ?string
    {
        return $this->fcmToken;
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title' => __('New trip :no', ['no' => $this->booking->booking_number]),
            'body' => __(':at · :from', ['at' => $this->booking->pickup_at->format('d M h:i A'), 'from' => $this->booking->pickup_address]),
            'data' => ['link' => route('driver.trips.show', $this->booking->trip), 'booking' => $this->booking->booking_number],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'trip_assigned', 'booking' => $this->booking->booking_number, 'pickup_at' => $this->booking->pickup_at->toIso8601String(),
            'pickup' => $this->booking->pickup_address, 'trip_id' => $this->booking->trip?->id];
    }

    public static function forDriver(Driver $driver, Booking $booking): void
    {
        $driver->user?->notify(new self($booking, $driver->fcm_token));
    }
}
