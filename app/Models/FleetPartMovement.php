<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only history of fitting, removing and scrapping a part. */
class FleetPartMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = ['fleet_part_id', 'action', 'vehicle_id', 'position', 'odometer_km', 'km_run', 'moved_on', 'notes'];

    protected function casts(): array
    {
        return ['moved_on' => 'date'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(FleetPart::class, 'fleet_part_id');
    }
}
