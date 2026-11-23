<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Overlap rules shared by customer bookings (Taxi) and fleet trips (Fleet): a vehicle or driver can hold only one
 * assigned/started trip in any time window. Callers must hold row locks on the vehicle and driver (lock order
 * vehicle → driver) inside their transaction so concurrent assignments cannot both pass.
 */
class TripScheduler
{
    public const ACTIVE = ['assigned', 'started'];

    /** First active trip of $column = $id overlapping [$start, $end), ignoring $exceptTripId. */
    public function clash(string $column, int $id, CarbonInterface $start, CarbonInterface $end, ?int $exceptTripId = null): ?Trip
    {
        return Trip::query()->with('booking:id,booking_number')->where($column, $id)->whereIn('status', self::ACTIVE)
            ->when($exceptTripId, fn ($q) => $q->whereKeyNot($exceptTripId))
            ->where('start_at', '<', $end)->where('end_at', '>', $start)->orderBy('start_at')->first();
    }

    /** @return list<int> vehicle_id or driver_id values busy in the window */
    public function busy(string $column, CarbonInterface $start, CarbonInterface $end, ?int $exceptTripId = null): array
    {
        return Trip::query()->whereIn('status', self::ACTIVE)->when($exceptTripId, fn ($q) => $q->whereKeyNot($exceptTripId))
            ->where('start_at', '<', $end)->where('end_at', '>', $start)->pluck($column)->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    /**
     * Lock vehicle then driver, check they are assignable and free. Must run inside a DB transaction.
     *
     * @return array{0: Vehicle, 1: Driver}
     */
    public function lockAndCheck(int $vehicleId, int $driverId, CarbonInterface $start, CarbonInterface $end, ?int $exceptTripId = null, int $passengers = 0): array
    {
        $vehicle = Vehicle::query()->whereKey($vehicleId)->lockForUpdate()->first();
        $driver = Driver::query()->whereKey($driverId)->lockForUpdate()->first();

        if (! $vehicle || ! $vehicle->isAssignable()) {
            throw ValidationException::withMessages(['vehicle_id' => __('This vehicle is not available (inactive, in maintenance, unavailable or documents expired).')]);
        }
        if (! $driver || ! $driver->isAssignable()) {
            throw ValidationException::withMessages(['driver_id' => __('This driver is not available (inactive, off duty or licence expired).')]);
        }
        if ($passengers > 0 && $vehicle->seats < $passengers) {
            throw ValidationException::withMessages(['vehicle_id' => __('This vehicle has only :n seats.', ['n' => $vehicle->seats])]);
        }
        if ($c = $this->clash('vehicle_id', $vehicle->id, $start, $end, $exceptTripId)) {
            throw ValidationException::withMessages(['vehicle_id' => __('Vehicle is already booked :from–:to (:no).', ['from' => $c->start_at->format('d M H:i'), 'to' => $c->end_at->format('d M H:i'), 'no' => $c->number()])]);
        }
        if ($c = $this->clash('driver_id', $driver->id, $start, $end, $exceptTripId)) {
            throw ValidationException::withMessages(['driver_id' => __('Driver is already on duty :from–:to (:no).', ['from' => $c->start_at->format('d M H:i'), 'to' => $c->end_at->format('d M H:i'), 'no' => $c->number()])]);
        }

        return [$vehicle, $driver];
    }
}
