<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleExpense extends Model
{
    use BelongsToTenant;

    protected $table = 'vehicle_expenses';

    protected $fillable = ['vehicle_id', 'expense_date', 'category', 'amount', 'litres', 'odometer_km', 'full_tank', 'payment_method', 'reference', 'notes'];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2', 'litres' => 'decimal:2', 'full_tank' => 'boolean'];
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
