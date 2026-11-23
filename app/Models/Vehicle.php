<?php

namespace App\Models;

use App\Enums\VehicleStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Depreciation;
use App\Support\Expiry;
use App\Support\FleetOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'branch_id', 'vehicle_category_id', 'vehicle_type', 'registration_number', 'make', 'model', 'year', 'color', 'fuel_type', 'seats', 'is_ac',
        'ownership', 'status', 'is_available', 'insurance_expiry', 'permit_expiry', 'fitness_expiry', 'puc_expiry', 'road_tax_expiry', 'fastag_id', 'odometer_km', 'notes',
        'purchase_date', 'purchase_cost', 'depreciation_method', 'depreciation_rate',
        'owner_name', 'owner_phone', 'owner_pan', 'owner_bank', 'owner_rate_type', 'owner_rate',
    ];

    protected function casts(): array
    {
        return [
            'status' => VehicleStatus::class, 'is_ac' => 'boolean', 'is_available' => 'boolean',
            'insurance_expiry' => 'date', 'permit_expiry' => 'date', 'fitness_expiry' => 'date', 'puc_expiry' => 'date', 'road_tax_expiry' => 'date',
            'purchase_date' => 'date', 'purchase_cost' => 'decimal:2', 'depreciation_rate' => 'decimal:2', 'owner_rate' => 'decimal:2',
        ];
    }

    /** MH 12 AB 1234 / mh12ab1234 → MH12AB1234 */
    public static function normalizeRegistration(?string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
    }

    /** Pretty print: MH12AB1234 → MH 12 AB 1234 (BH series kept as-is). */
    public function displayRegistration(): string
    {
        return preg_match('/^([A-Z]{2})(\d{1,2})([A-Z]{0,3})(\d{1,4})$/', $this->registration_number, $m)
            ? trim("$m[1] $m[2] $m[3] $m[4]")
            : $this->registration_number;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'vehicle_category_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(FleetDocument::class, 'documentable')->latest('id');
    }

    public function maintenance(): HasMany
    {
        return $this->hasMany(VehicleMaintenance::class)->orderByDesc('service_date')->orderByDesc('id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(VehicleExpense::class)->orderByDesc('expense_date')->orderByDesc('id');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(FleetPart::class)->where('status', 'fitted')->orderBy('type')->orderBy('position');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(VehicleIncident::class)->orderByDesc('occurred_on')->orderByDesc('id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(VehicleLoan::class)->orderByRaw("case when status = 'active' then 0 else 1 end")->orderByDesc('id');
    }

    public function ownerPayouts(): HasMany
    {
        return $this->hasMany(VehicleOwnerPayout::class)->latest('id');
    }

    public function isAttached(): bool
    {
        return in_array($this->ownership, ['attached', 'vendor'], true);
    }

    /** Book value today (null when purchase cost/date are not set). */
    public function bookValue(?\Carbon\CarbonInterface $asOf = null): ?string
    {
        if ($this->purchase_cost === null || ! $this->purchase_date) {
            return null;
        }

        return Depreciation::bookValue((string) $this->purchase_cost, (string) $this->depreciation_method, (string) ($this->depreciation_rate ?? '0'), $this->purchase_date, $asOf ?? now());
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(VehicleInspection::class)->orderByDesc('inspected_on')->orderByDesc('id');
    }

    /** Required documents always; optional ones (road tax) only when a date is set. */
    public function expiries(): array
    {
        $out = [];
        foreach (FleetOptions::allVehicleExpiryFields() as $field => $label) {
            if ($this->{$field} === null && array_key_exists($field, FleetOptions::optionalVehicleExpiryFields())) {
                continue;
            }
            $out[$field] = ['name' => $label, 'date' => $this->{$field}] + Expiry::of($this->{$field});
        }

        return $out;
    }

    /** Vehicles with any document expiring on/before $date (or already expired). */
    public function scopeNeedsAttention(Builder $query, string $date): Builder
    {
        return $query->where(function (Builder $w) use ($date) {
            foreach (array_keys(FleetOptions::allVehicleExpiryFields()) as $field) {
                $w->orWhere($field, '<=', $date);
            }
        });
    }

    /** Worst state across documents: expired > due > missing > ok. */
    public function complianceState(): string
    {
        $states = array_column($this->expiries(), 'state');

        return collect(['expired', 'due', 'missing'])->first(fn ($s) => in_array($s, $states, true)) ?? 'ok';
    }

    public function isAssignable(): bool
    {
        return $this->status === VehicleStatus::Active && $this->is_available && $this->complianceState() !== 'expired';
    }
}
