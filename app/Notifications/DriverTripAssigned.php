<?php

namespace App\Notifications;

use App\Models\Driver;
use App\Models\Trip;
use App\Notifications\Channels\FcmChannel;
use App\Services\Push\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent synchronously (database row is what the driver app polls for); push when Firebase is configured.
 * Works for customer bookings (Taxi) and fleet trips (Fleet); carries plain values only.
 */
class DriverTripAssigned extends Notification
{
    use Queueable;

    public function __construct(public int $tripId, public string $number, public string $at, public string $from, public ?string $fcmToken = null) {}

    public static function forDriver(Driver $driver, Trip $trip): void
    {
        $driver->user?->notify(new self($trip->id, $trip->number(), $trip->start_at->toIso8601String(), $trip->pickupLabel(), $driver->fcm_token));
    }

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
            'title' => __('New trip :no', ['no' => $this->number]),
            'body' => __(':at · :from', ['at' => \Illuminate\Support\Carbon::parse($this->at)->timezone(config('app.timezone'))->format('d M h:i A'), 'from' => $this->from]),
            'data' => ['link' => route('driver.trips.show', $this->tripId), 'booking' => $this->number],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => 'trip_assigned', 'booking' => $this->number, 'pickup_at' => $this->at, 'pickup' => $this->from, 'trip_id' => $this->tripId];
    }
}
