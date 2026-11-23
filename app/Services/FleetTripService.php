<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\DriverTripAssigned;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fleet module: company/institution vehicle trips without a customer booking or fare.
 * assigned → started → completed, or assigned → released (cancelled). Uses the same overlap rules as bookings.
 */
class FleetTripService
{
    public function __construct(
        private TripScheduler $scheduler,
        private SequenceService $sequences,
        private AuditLogger $audit,
        private TenantContext $context,
    ) {}

    /** @param array{vehicle_id:int, driver_id:int, start_at:mixed, end_at:mixed, purpose:string, department_id?:?int, requested_by?:?string, requested_phone?:?string, pickup_point?:?string, drop_point?:?string, passengers?:?int, notes?:?string} $data */
    public function create(array $data, User $user): Trip
    {
        [$start, $end] = $this->window($data);

        $trip = DB::transaction(function () use ($data, $start, $end, $user) {
            [$vehicle, $driver] = $this->scheduler->lockAndCheck((int) $data['vehicle_id'], (int) $data['driver_id'], $start, $end, null, (int) ($data['passengers'] ?? 0));

            $trip = new Trip($this->details($data) + ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'start_at' => $start, 'end_at' => $end, 'status' => 'assigned']);
            $trip->trip_number = $this->nextNumber($start);
            $trip->assigned_by = $user->id;
            $trip->save();
            $this->audit->log('fleet_trip.created', $trip, [], ['number' => $trip->trip_number, 'vehicle' => $vehicle->registration_number, 'driver' => $driver->name]);

            return $trip;
        });

        $this->notifyDriver($trip);

        return $trip;
    }

    /** Edit an assigned (not yet started) trip; re-checks availability excluding itself. */
    public function update(Trip $trip, array $data, User $user): Trip
    {
        [$start, $end] = $this->window($data);
        $oldDriver = $trip->driver_id;

        $trip = DB::transaction(function () use ($trip, $data, $start, $end) {
            [$vehicle, $driver] = $this->scheduler->lockAndCheck((int) $data['vehicle_id'], (int) $data['driver_id'], $start, $end, $trip->id, (int) ($data['passengers'] ?? 0));
            $locked = $this->lock($trip, ['assigned']);
            $old = $locked->only(['vehicle_id', 'driver_id', 'start_at', 'end_at']);
            $locked->fill($this->details($data) + ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'start_at' => $start, 'end_at' => $end])->save();
            $this->audit->log('fleet_trip.updated', $locked, array_map(fn ($v) => $v instanceof CarbonInterface ? $v->toDateTimeString() : $v, $old), $locked->only(['vehicle_id', 'driver_id']));

            return $locked;
        });

        if ($trip->driver_id !== $oldDriver) {
            $this->notifyDriver($trip);
        }

        return $trip;
    }

    public function start(Trip $trip, int $startKm, User $user): void
    {
        DB::transaction(function () use ($trip, $startKm) {
            $locked = $this->lock($trip, ['assigned']);
            $odometer = (int) $locked->vehicle()->value('odometer_km');
            if ($odometer && $startKm < $odometer - 50) {
                throw ValidationException::withMessages(['start_km' => __('Odometer is lower than the vehicle’s last reading (:km km).', ['km' => number_format($odometer)])]);
            }
            $locked->forceFill(['status' => 'started', 'started_at' => now(), 'start_km' => $startKm])->save();
            $this->audit->log('fleet_trip.started', $locked, [], ['start_km' => $startKm]);
            $trip->setRawAttributes($locked->getAttributes(), true);
        });
    }

    public function complete(Trip $trip, int $endKm, User $user): void
    {
        DB::transaction(function () use ($trip, $endKm) {
            // Same lock order as assignment (vehicle → … → trip); a started trip's vehicle cannot change.
            $vehicle = Vehicle::withTrashed()->whereKey($trip->vehicle_id)->lockForUpdate()->first();
            $locked = $this->lock($trip, ['started']);
            if ($locked->start_km !== null && $endKm < $locked->start_km) {
                throw ValidationException::withMessages(['end_km' => __('End km cannot be less than start km (:n).', ['n' => $locked->start_km])]);
            }
            $locked->forceFill(['status' => 'completed', 'completed_at' => now(), 'end_km' => $endKm])->save();
            if ($vehicle && $endKm > (int) $vehicle->odometer_km) {
                $vehicle->forceFill(['odometer_km' => $endKm])->save();
            }
            $this->audit->log('fleet_trip.completed', $locked, [], ['end_km' => $endKm, 'km' => $locked->km()]);
            $trip->setRawAttributes($locked->getAttributes(), true);
        });
    }

    public function cancel(Trip $trip, string $reason, User $user): void
    {
        DB::transaction(function () use ($trip, $reason) {
            $locked = $this->lock($trip, ['assigned']);
            $locked->forceFill(['status' => 'released', 'cancel_reason' => $reason])->save();
            $this->audit->log('fleet_trip.cancelled', $locked, [], ['reason' => $reason]);
            $trip->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function window(array $data): array
    {
        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);
        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['end_at' => __('Return time must be after the start time.')]);
        }
        if ($start->diffInDays($end) > 31) {
            throw ValidationException::withMessages(['end_at' => __('A trip can be at most 31 days long.')]);
        }

        return [$start, $end];
    }

    private function details(array $data): array
    {
        return collect($data)->only(['purpose', 'department_id', 'requested_by', 'requested_phone', 'pickup_point', 'drop_point', 'passengers', 'notes'])
            ->map(fn ($v) => $v === '' ? null : $v)->all();
    }

    /** @param list<string> $from allowed current statuses */
    private function lock(Trip $trip, array $from): Trip
    {
        $locked = Trip::query()->whereKey($trip->id)->whereNull('booking_id')->lockForUpdate()->firstOrFail();
        if (! in_array($locked->status, $from, true)) {
            throw ValidationException::withMessages(['status' => __('This trip is :status and cannot be changed this way.', ['status' => __($locked->status)])]);
        }

        return $locked;
    }

    private function nextNumber(CarbonInterface $at): string
    {
        $ym = $at->format('ym');

        return sprintf('FT%s-%04d', $ym, $this->sequences->next("fleet_trip:{$this->context->id()}:{$ym}"));
    }

    private function notifyDriver(Trip $trip): void
    {
        try {
            DriverTripAssigned::forDriver($trip->driver()->first(), $trip);
        } catch (\Throwable $e) {
            report($e); // never block the trip because a push failed
        }
    }
}
