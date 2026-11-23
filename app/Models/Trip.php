<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trip extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'booking_id', 'vehicle_id', 'driver_id', 'start_at', 'end_at', 'status',
        // Fleet trips (no booking)
        'trip_number', 'purpose', 'department_id', 'requested_by', 'requested_phone', 'pickup_point', 'drop_point', 'passengers', 'notes',
    ];

    protected $hidden = ['start_otp'];

    protected function casts(): array
    {
        return ['start_at' => 'datetime', 'end_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'last_location_at' => 'datetime', 'last_lat' => 'float', 'last_lng' => 'float'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Fleet module trip (company/institution use), not a customer booking. */
    public function isFleet(): bool
    {
        return $this->booking_id === null;
    }

    public function number(): string
    {
        return $this->isFleet() ? (string) $this->trip_number : (string) $this->booking?->booking_number;
    }

    public function pickupLabel(): string
    {
        return $this->isFleet() ? (string) $this->pickup_point : (string) $this->booking?->pickup_address;
    }

    public function dropLabel(): ?string
    {
        return $this->isFleet() ? $this->drop_point : $this->booking?->drop_address;
    }

    public function km(): ?int
    {
        return ($this->end_km !== null && $this->start_km !== null) ? $this->end_km - $this->start_km : null;
    }

    public function hours(): ?float
    {
        return ($this->started_at && $this->completed_at) ? round($this->started_at->diffInMinutes($this->completed_at) / 60, 2) : null;
    }
}
