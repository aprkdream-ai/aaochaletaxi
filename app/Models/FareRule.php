<?php

namespace App\Models;

use App\Enums\BookingType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FareRule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'vehicle_category_id', 'booking_type', 'base_fare', 'per_km', 'min_km', 'included_km', 'included_hours', 'extra_km_rate',
        'extra_hour_rate', 'driver_allowance', 'night_charge', 'night_start', 'night_end', 'waiting_free_min', 'waiting_per_min', 'gst_percent', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'booking_type' => BookingType::class, 'is_active' => 'boolean',
            'base_fare' => 'decimal:2', 'per_km' => 'decimal:2', 'extra_km_rate' => 'decimal:2', 'extra_hour_rate' => 'decimal:2',
            'driver_allowance' => 'decimal:2', 'night_charge' => 'decimal:2', 'waiting_per_min' => 'decimal:2', 'gst_percent' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }
}
