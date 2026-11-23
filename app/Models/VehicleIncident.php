<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Accident, challan, theft or damage, with insurance-claim tracking. Payment is a linked vehicle expense. */
class VehicleIncident extends Model
{
    use BelongsToTenant;

    protected $fillable = ['vehicle_id', 'driver_id', 'type', 'occurred_on', 'place', 'reference', 'description', 'amount', 'claim_status', 'insurer', 'claim_number', 'settled_amount'];

    protected function casts(): array
    {
        return ['occurred_on' => 'date', 'amount' => 'decimal:2', 'settled_amount' => 'decimal:2'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(VehicleExpense::class, 'expense_id');
    }
}
