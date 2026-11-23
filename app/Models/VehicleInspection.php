<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\FleetOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Daily pre-trip checklist filled by a driver in the PWA. */
class VehicleInspection extends Model
{
    use BelongsToTenant;

    protected $fillable = ['vehicle_id', 'driver_id', 'inspected_on', 'odometer_km', 'items', 'issues_count', 'notes'];

    protected function casts(): array
    {
        return ['inspected_on' => 'date', 'items' => 'array', 'resolved_at' => 'datetime'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return list<string> labels of items marked as an issue */
    public function issueLabels(): array
    {
        $labels = FleetOptions::inspectionItems();

        return collect($this->items ?? [])->filter(fn ($v) => $v === 'issue')->keys()->map(fn ($k) => $labels[$k] ?? $k)->values()->all();
    }

    public function hasOpenIssues(): bool
    {
        return $this->issues_count > 0 && $this->resolved_at === null;
    }
}
