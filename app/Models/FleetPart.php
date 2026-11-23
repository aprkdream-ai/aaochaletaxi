<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tyre, battery or other part with its own life (km run across fittings) and warranty. */
class FleetPart extends Model
{
    use BelongsToTenant;

    protected $fillable = ['type', 'brand', 'model', 'serial_number', 'purchase_date', 'cost', 'vendor', 'warranty_until', 'notes'];

    protected function casts(): array
    {
        return ['purchase_date' => 'date', 'warranty_until' => 'date', 'fitted_on' => 'date', 'cost' => 'decimal:2'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FleetPartMovement::class)->latest('moved_on')->latest('id');
    }

    /** Total km: previous fittings + current fitting (vehicle odometer − odometer when fitted). */
    public function totalKm(): int
    {
        $current = ($this->status === 'fitted' && $this->fitted_at_km !== null && $this->vehicle?->odometer_km !== null)
            ? max(0, $this->vehicle->odometer_km - $this->fitted_at_km) : 0;

        return (int) $this->km_run + $current;
    }

    public function label(): string
    {
        return trim($this->brand.' '.$this->model).($this->serial_number ? ' #'.$this->serial_number : '');
    }
}
