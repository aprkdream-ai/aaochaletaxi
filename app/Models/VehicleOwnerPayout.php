<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only payment to the owner of an attached / vendor vehicle. */
class VehicleOwnerPayout extends Model
{
    use BelongsToTenant;

    protected $fillable = ['vehicle_id', 'period_from', 'period_to', 'earned', 'deductions', 'amount', 'method', 'reference', 'paid_on', 'notes', 'breakdown'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'paid_on' => 'date', 'earned' => 'decimal:2', 'deductions' => 'decimal:2', 'amount' => 'decimal:2', 'breakdown' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Owner payouts are append-only.'));
        static::deleting(fn () => throw new \LogicException('Owner payouts are append-only.'));
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
