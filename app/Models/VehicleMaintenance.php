<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMaintenance extends Model
{
    use BelongsToTenant;

    protected $table = 'vehicle_maintenance';

    protected $fillable = ['vehicle_id', 'service_date', 'type', 'description', 'odometer_km', 'cost', 'vendor', 'next_service_date', 'next_service_km'];

    protected function casts(): array
    {
        return ['service_date' => 'date', 'next_service_date' => 'date', 'cost' => 'decimal:2'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
