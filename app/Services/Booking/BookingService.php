<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingQuotation;
use App\Models\BookingStatusLog;
use App\Models\BookingStop;
use App\Models\Driver;
use App\Models\FareRule;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\DriverTripAssigned;
use App\Services\AuditLogger;
use App\Services\SequenceService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private FareCalculator $calculator,
        private FareRuleService $rules,
        private SequenceService $sequences,
        private AuditLogger $audit,
        private CustomerNotifier $notifier,
    ) {}

    /** Fare preview without saving. */
    public function preview(int $categoryId, BookingType $type, array $fareInput): array
    {
        $rule = $this->rules->find($categoryId, $type)
            ?? throw ValidationException::withMessages(['vehicle_category_id' => __('No active fare rule for this vehicle type and trip. Set it up under Fare rules.')]);

        return $this->snapshot($rule, $fareInput);
    }

    /**
     * @param  array  $data  booking attributes (validated)
     * @param  list<string>  $stops
     * @param  array  $fareInput  tolls/parking/state_tax/discount/waiting_min
     */
    public function create(Tenant $tenant, array $data, array $stops, array $fareInput, ?User $user, ?string $idempotencyKey, bool $phoneVerified = false): Booking
    {
        if ($idempotencyKey && ($existing = Booking::query()->where('idempotency_key', $idempotencyKey)->first())) {
            return $existing; // double-submit / retry → same booking
        }

        $booking = DB::transaction(function () use ($tenant, $data, $stops, $fareInput, $user, $idempotencyKey, $phoneVerified) {
            $booking = new Booking($data);
            $booking->booking_number = $this->nextNumber($tenant, $booking->pickup_at);
            $booking->idempotency_key = $idempotencyKey;
            $booking->status = BookingStatus::Enquiry;
            $booking->payment_status = PaymentStatus::Unpaid;
            $booking->created_by = $user?->id;
            $booking->public_token = Str::random(40);
            $booking->phone_verified = $phoneVerified;
            $booking->customer_user_id = User::query()->where('phone', $booking->customer_phone)->where('type', 'customer')->value('id');
            $booking->save();

            foreach (array_values(array_filter(array_map('trim', $stops))) as $i => $address) {
                BookingStop::create(['booking_id' => $booking->id, 'sequence' => $i + 1, 'address' => mb_substr($address, 0, 255)]);
            }
            $this->log($booking, null, BookingStatus::Enquiry, $user, __('Booking created'));

            if ($this->canPrice($booking)) {
                $this->quote($booking, $fareInput, $user);
            }
            $this->audit->log('booking.created', $booking, [], ['number' => $booking->booking_number, 'type' => $booking->type->value]);

            return $booking->refresh();
        });

        if ($booking->status === BookingStatus::Quoted) {
            $this->notifier->notify($booking, 'quoted');
        }

        return $booking;
    }

    /** Creates a new quotation version from the current fare rule. */
    public function quote(Booking $booking, array $fareInput, ?User $user): BookingQuotation
    {
        $this->assertTransition($booking, BookingStatus::Quoted);
        $snapshot = $this->preview($booking->vehicle_category_id, $booking->type, $this->fareInputFor($booking, $fareInput));

        return DB::transaction(function () use ($booking, $snapshot, $user) {
            $version = (int) $booking->quotations()->max('version') + 1;
            $q = new BookingQuotation(['booking_id' => $booking->id, 'version' => $version, 'breakdown' => $snapshot, 'total' => $snapshot['total'], 'valid_until' => now()->addDays(3)]);
            $q->created_by = $user?->id;
            $q->save();

            $from = $booking->status;
            $booking->forceFill(['fare_snapshot' => $snapshot, 'quoted_total' => $snapshot['total']]);
            if (in_array($from, [BookingStatus::Enquiry, BookingStatus::Quoted, BookingStatus::Confirmed], true)) {
                $booking->status = BookingStatus::Quoted;
            }
            $booking->save();
            $this->refreshPaymentStatus($booking);
            $this->log($booking, $from, $booking->status, $user, __('Quotation v:v: :amt', ['v' => $version, 'amt' => Money::format($snapshot['total'])]));

            return $q;
        });
    }

    public function confirm(Booking $booking, ?User $user): void
    {
        if (! $booking->quoted_total) {
            throw ValidationException::withMessages(['status' => __('Create a quotation before confirming.')]);
        }
        $this->transition($booking, BookingStatus::Confirmed, $user, $user ? __('Customer confirmed') : __('Confirmed by online advance payment'));
        $booking->quotations()->first()?->forceFill(['accepted_at' => now()])->save();
        $this->notifier->notify($booking, 'confirmed');
    }

    public function recordPayment(Booking $booking, string $kind, string|float $amount, string $method, ?string $reference, ?User $user, ?string $notes = null): Payment
    {
        $amount = number_format((float) $amount, 2, '.', '');
        if ((float) $amount <= 0) {
            throw ValidationException::withMessages(['amount' => __('Amount must be greater than zero.')]);
        }
        if ($kind === 'refund' && (float) $amount > (float) $booking->amount_paid) {
            throw ValidationException::withMessages(['amount' => __('Refund cannot exceed the amount received (:a).', ['a' => Money::format($booking->amount_paid)])]);
        }

        return DB::transaction(function () use ($booking, $kind, $amount, $method, $reference, $user, $notes) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $p = new Payment(['booking_id' => $locked->id, 'kind' => $kind, 'amount' => $kind === 'refund' ? '-'.$amount : $amount, 'method' => $method, 'reference' => $reference, 'paid_at' => now(), 'notes' => $notes]);
            $p->recorded_by = $user?->id;
            $p->save();

            $locked->amount_paid = Money::add((string) $locked->amount_paid, (string) $p->amount);
            $locked->save();
            $this->refreshPaymentStatus($locked);
            $booking->setRawAttributes($locked->getAttributes(), true);
            $this->audit->log('booking.payment_'.$kind, $locked, [], ['amount' => $p->amount, 'method' => $method]);

            return $p;
        });
    }

    /**
     * Assign vehicle + driver with row locks and overlap checks (prevents double booking).
     *
     * @return array{trip: Trip, otp: string}
     */
    public function assign(Booking $booking, int $vehicleId, int $driverId, User $user): array
    {
        $this->assertTransition($booking, BookingStatus::Assigned);
        [$start, $end] = $this->window($booking);
        $otp = (string) random_int(1000, 9999);

        $trip = DB::transaction(function () use ($booking, $vehicleId, $driverId, $user, $start, $end, $otp) {
            // Lock in a fixed order (vehicle → driver) to avoid deadlocks between concurrent assignments.
            $vehicle = Vehicle::query()->whereKey($vehicleId)->lockForUpdate()->first();
            $driver = Driver::query()->whereKey($driverId)->lockForUpdate()->first();
            Booking::query()->whereKey($booking->id)->lockForUpdate()->first();

            if (! $vehicle || ! $vehicle->isAssignable()) {
                throw ValidationException::withMessages(['vehicle_id' => __('This vehicle is not available (inactive, in maintenance, unavailable or documents expired).')]);
            }
            if (! $driver || ! $driver->isAssignable()) {
                throw ValidationException::withMessages(['driver_id' => __('This driver is not available (inactive, off duty or licence expired).')]);
            }
            if ($vehicle->seats < $booking->passengers) {
                throw ValidationException::withMessages(['vehicle_id' => __('This vehicle has only :n seats.', ['n' => $vehicle->seats])]);
            }

            $clash = fn (string $column, int $id) => Trip::query()->where($column, $id)->whereIn('status', ['assigned', 'started'])
                ->where('booking_id', '!=', $booking->id)->where('start_at', '<', $end)->where('end_at', '>', $start)
                ->with('booking:id,booking_number')->first();

            if ($c = $clash('vehicle_id', $vehicle->id)) {
                throw ValidationException::withMessages(['vehicle_id' => __('Vehicle is already booked :from–:to (:no).', ['from' => $c->start_at->format('d M H:i'), 'to' => $c->end_at->format('d M H:i'), 'no' => $c->booking->booking_number])]);
            }
            if ($c = $clash('driver_id', $driver->id)) {
                throw ValidationException::withMessages(['driver_id' => __('Driver is already on duty :from–:to (:no).', ['from' => $c->start_at->format('d M H:i'), 'to' => $c->end_at->format('d M H:i'), 'no' => $c->booking->booking_number])]);
            }

            $trip = Trip::query()->firstOrNew(['booking_id' => $booking->id]);
            $trip->fill(['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'start_at' => $start, 'end_at' => $end, 'status' => 'assigned']);
            $trip->start_otp = Crypt::encryptString($otp); // encrypted (not hashed) so the customer can see it on the tracking page
            $trip->assigned_by = $user->id;
            $trip->save();

            $this->transition($booking, BookingStatus::Assigned, $user, __(':reg · :driver', ['reg' => $vehicle->displayRegistration(), 'driver' => $driver->name]));

            return $trip;
        });

        try {
            DriverTripAssigned::forDriver($trip->driver, $booking->setRelation('trip', $trip));
        } catch (\Throwable $e) {
            report($e); // never block assignment because a push failed
        }
        $this->notifier->notify($booking, 'assigned', [
            'driver' => $trip->driver->name, 'driver_phone' => $trip->driver->phone, 'vehicle' => $trip->vehicle->displayRegistration(), 'otp' => $otp,
        ]);

        return ['trip' => $trip, 'otp' => $otp];
    }

    public function start(Booking $booking, string $otp, ?int $startKm, User $user): void
    {
        $this->assertTransition($booking, BookingStatus::Started);
        $trip = $booking->trip ?? throw ValidationException::withMessages(['otp' => __('Assign a vehicle and driver first.')]);
        if (! $trip->start_otp || ! hash_equals($this->tripOtp($trip) ?? '', $otp)) {
            throw ValidationException::withMessages(['otp' => __('Incorrect OTP. Ask the customer for the 4-digit code.')]);
        }

        DB::transaction(function () use ($booking, $trip, $startKm, $user) {
            $trip->forceFill(['status' => 'started', 'started_at' => now(), 'start_km' => $startKm, 'start_otp' => null])->save();
            $this->transition($booking, BookingStatus::Started, $user, __('Trip started (OTP verified)'));
        });
    }

    /** Completes the trip and computes the final fare from the quote-time rates. */
    public function complete(Booking $booking, ?int $endKm, array $extras, User $user): void
    {
        $this->assertTransition($booking, BookingStatus::Completed);
        $trip = $booking->trip;
        if ($endKm !== null && $trip->start_km !== null && $endKm < $trip->start_km) {
            throw ValidationException::withMessages(['end_km' => __('End km cannot be less than start km (:n).', ['n' => $trip->start_km])]);
        }

        $actualKm = ($endKm !== null && $trip->start_km !== null) ? $endKm - $trip->start_km : (float) $booking->distance_km;
        $snapshot = $booking->fare_snapshot ?? throw ValidationException::withMessages(['status' => __('This booking has no quotation.')]);
        $rule = $this->ruleFromSnapshot($snapshot);

        $final = $this->snapshot($rule, [
            'distance_km' => $actualKm, 'pickup_at' => $booking->pickup_at, 'return_at' => $booking->return_at,
            'rental_hours' => $extras['rental_hours'] ?? $booking->rental_hours, 'waiting_min' => $extras['waiting_min'] ?? 0,
            'tolls' => $extras['tolls'] ?? 0, 'parking' => $extras['parking'] ?? 0, 'state_tax' => $extras['state_tax'] ?? 0,
            'discount' => $snapshot['discount'] ?? 0,
        ]);

        DB::transaction(function () use ($booking, $trip, $endKm, $final, $user) {
            $trip->forceFill(['status' => 'completed', 'completed_at' => now(), 'end_km' => $endKm])->save();
            if ($endKm && $endKm > (int) $trip->vehicle->odometer_km) {
                $trip->vehicle->forceFill(['odometer_km' => $endKm])->save();
            }
            $booking->forceFill(['final_snapshot' => $final, 'final_total' => $final['total']])->save();
            $this->transition($booking, BookingStatus::Completed, $user, __('Final fare :amt', ['amt' => Money::format($final['total'])]));
            $this->refreshPaymentStatus($booking);
        });
        $this->notifier->notify($booking, 'completed');
    }

    public function cancel(Booking $booking, string $reason, ?User $user): void
    {
        $this->assertTransition($booking, BookingStatus::Cancelled);
        DB::transaction(function () use ($booking, $reason, $user) {
            $booking->trip?->forceFill(['status' => 'released', 'start_otp' => null])->save();
            $booking->forceFill(['cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            $this->transition($booking, BookingStatus::Cancelled, $user, $reason);
        });
        $this->notifier->notify($booking, 'cancelled');
    }

    /** Plain trip OTP for the customer's tracking page (null once the trip has started). */
    public function tripOtp(?Trip $trip): ?string
    {
        if (! $trip?->start_otp) {
            return null;
        }
        try {
            return Crypt::decryptString($trip->start_otp);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Vehicles and drivers free for this booking's window (for the assign dialog). */
    public function availability(Booking $booking): array
    {
        [$start, $end] = $this->window($booking);
        $busy = fn (string $col) => Trip::query()->whereIn('status', ['assigned', 'started'])->where('booking_id', '!=', $booking->id)
            ->where('start_at', '<', $end)->where('end_at', '>', $start)->pluck($col)->all();
        $busyVehicles = $busy('vehicle_id');
        $busyDrivers = $busy('driver_id');

        $vehicles = Vehicle::query()->with('category:id,name')->where('status', 'active')->where('is_available', true)->where('seats', '>=', $booking->passengers)
            ->orderByRaw('case when vehicle_category_id = ? then 0 else 1 end', [$booking->vehicle_category_id])->orderBy('registration_number')->get()
            ->filter(fn (Vehicle $v) => $v->complianceState() !== 'expired');
        $drivers = Driver::query()->where('status', 'active')->where('is_available', true)->orderBy('name')->get()
            ->filter(fn (Driver $d) => $d->licence()['state'] !== 'expired');

        return [
            'window' => [$start, $end],
            'vehicles' => $vehicles->map(fn ($v) => ['id' => $v->id, 'label' => $v->displayRegistration().' · '.$v->make.' '.$v->model, 'category' => $v->category->name, 'busy' => in_array($v->id, $busyVehicles, true), 'match' => $v->vehicle_category_id === $booking->vehicle_category_id])->values()->all(),
            'drivers' => $drivers->map(fn ($d) => ['id' => $d->id, 'label' => $d->name.' · '.$d->phone, 'busy' => in_array($d->id, $busyDrivers, true)])->values()->all(),
        ];
    }

    /** @return array{0: CarbonInterface, 1: CarbonInterface} */
    public function window(Booking $booking): array
    {
        $start = $booking->pickup_at->copy();
        $travel = $booking->duration_min ?: (int) ceil(((float) ($booking->distance_km ?: 20)) / 40 * 60);

        $end = match ($booking->type) {
            BookingType::OutstationRound => ($booking->return_at ?? $start)->copy()->addHours(2),
            BookingType::OutstationOneway => $start->copy()->addMinutes($travel * 2 + 60),        // drive back to base
            BookingType::Hourly => $start->copy()->addHours(max(1, (int) $booking->rental_hours ?: 4))->addMinutes(30),
            BookingType::FullDay => $start->copy()->addHours(max(12, (int) $booking->rental_hours)),
            default => $start->copy()->addMinutes(max(60, $travel) + 60),
        };

        return [$start, $end];
    }

    public function canPrice(Booking $booking): bool
    {
        return $booking->type->isRental() || (float) $booking->distance_km > 0;
    }

    private function fareInputFor(Booking $booking, array $fareInput): array
    {
        return [
            'distance_km' => $booking->distance_km, 'pickup_at' => $booking->pickup_at, 'return_at' => $booking->return_at,
            'rental_hours' => $booking->rental_hours,
        ] + array_intersect_key($fareInput, array_flip(['tolls', 'parking', 'state_tax', 'discount', 'waiting_min']));
    }

    private function snapshot(FareRule $rule, array $input): array
    {
        $result = $this->calculator->calculate($rule, $input);
        $result['rule'] = $rule->only($rule->getFillable()) + ['booking_type' => $rule->booking_type->value];
        $result['inputs'] = array_intersect_key(array_map(fn ($v) => $v instanceof CarbonInterface ? $v->toIso8601String() : $v, $input), array_flip(['tolls', 'parking', 'state_tax', 'discount', 'waiting_min', 'rental_hours']));

        return $result;
    }

    private function ruleFromSnapshot(array $snapshot): FareRule
    {
        $rule = new FareRule($snapshot['rule']);
        $rule->id = $snapshot['rule_id'] ?? null;

        return $rule;
    }

    private function nextNumber(Tenant $tenant, ?CarbonInterface $at): string
    {
        $ym = ($at ?? now())->format('ym');

        return sprintf('BK%s-%04d', $ym, $this->sequences->next("booking:{$tenant->id}:{$ym}"));
    }

    private function refreshPaymentStatus(Booking $booking): void
    {
        $paid = (float) $booking->amount_paid;
        $payable = (float) $booking->payable();
        $status = match (true) {
            $paid <= 0 && Payment::query()->where('booking_id', $booking->id)->where('kind', 'refund')->exists() => PaymentStatus::Refunded,
            $paid <= 0 => PaymentStatus::Unpaid,
            $payable > 0 && $paid >= $payable => PaymentStatus::Paid,
            default => PaymentStatus::AdvancePaid,
        };
        if ($booking->payment_status !== $status) {
            $booking->payment_status = $status;
            $booking->save();
        }
    }

    private function assertTransition(Booking $booking, BookingStatus $to): void
    {
        if (! $booking->status->canMoveTo($to)) {
            throw ValidationException::withMessages(['status' => __('A :from booking cannot move to :to.', ['from' => mb_strtolower($booking->status->label()), 'to' => mb_strtolower($to->label())])]);
        }
    }

    private function transition(Booking $booking, BookingStatus $to, ?User $user, ?string $note): void
    {
        $this->assertTransition($booking, $to);
        $from = $booking->status;
        $booking->status = $to;
        $booking->save();
        $this->log($booking, $from, $to, $user, $note);
    }

    private function log(Booking $booking, ?BookingStatus $from, BookingStatus $to, ?User $user, ?string $note): void
    {
        BookingStatusLog::create(['booking_id' => $booking->id, 'from_status' => $from?->value, 'to_status' => $to->value, 'user_id' => $user?->id, 'note' => $note ? mb_substr($note, 0, 255) : null]);
    }
}
