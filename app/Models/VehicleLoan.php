<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class VehicleLoan extends Model
{
    use BelongsToTenant;

    protected $fillable = ['vehicle_id', 'lender', 'account_number', 'principal', 'interest_rate', 'tenure_months', 'emi_amount', 'first_emi_date', 'notes'];

    protected function casts(): array
    {
        return ['first_emi_date' => 'date', 'closed_on' => 'date', 'principal' => 'decimal:2', 'interest_rate' => 'decimal:2', 'emi_amount' => 'decimal:2'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /** Paid EMIs (vehicle expenses of category emi linked to this loan). */
    public function payments(): HasMany
    {
        return $this->hasMany(VehicleExpense::class, 'loan_id')->orderBy('installment_no');
    }

    public function dueDate(int $installment): Carbon
    {
        return $this->first_emi_date->copy()->addMonthsNoOverflow($installment - 1);
    }
}
