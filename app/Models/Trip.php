<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trip extends Model
{
    use BelongsToTenant;

    protected $fillable = ['booking_id', 'vehicle_id', 'driver_id', 'start_at', 'end_at', 'status'];

    protected $hidden = ['start_otp'];

    protected function casts(): array
    {
        return ['start_at' => 'datetime', 'end_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
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
}
