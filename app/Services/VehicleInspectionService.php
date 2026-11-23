<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Notifications\VehicleInspectionIssue;
use App\Support\FleetOptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Driver daily checklist: one per driver + vehicle + day; problems alert the owner. */
class VehicleInspectionService
{
    public const DISK = 'local';

    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, string> $items key => ok|issue (every checklist item required) */
    public function submit(Driver $driver, Vehicle $vehicle, array $items, ?int $odometerKm, ?string $notes, ?UploadedFile $photo, User $user): VehicleInspection
    {
        $items = array_intersect_key($items, FleetOptions::inspectionItems());
        $issues = count(array_filter($items, fn ($v) => $v === 'issue'));
        if ($issues > 0 && blank($notes) && ! $photo) {
            throw ValidationException::withMessages(['notes' => __('Describe the problem or add a photo.')]);
        }
        if ($odometerKm !== null && $vehicle->odometer_km !== null && $odometerKm < $vehicle->odometer_km - 50) {
            throw ValidationException::withMessages(['odometer_km' => __('Odometer is lower than the last reading (:km km).', ['km' => number_format($vehicle->odometer_km)])]);
        }

        $path = $photo?->storeAs('inspections/'.$vehicle->tenant_id, Str::random(32).'.'.$photo->guessExtension(), self::DISK);

        try {
            $inspection = DB::transaction(function () use ($driver, $vehicle, $items, $issues, $odometerKm, $notes, $path, $user) {
                $locked = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);
                if (VehicleInspection::query()->where('vehicle_id', $locked->id)->where('driver_id', $driver->id)->whereDate('inspected_on', today())->exists()) {
                    throw ValidationException::withMessages(['vehicle_id' => __('You already submitted today’s check for this vehicle.')]);
                }
                $inspection = new VehicleInspection([
                    'vehicle_id' => $locked->id, 'driver_id' => $driver->id, 'inspected_on' => today(), 'odometer_km' => $odometerKm,
                    'items' => $items, 'issues_count' => $issues, 'notes' => $notes,
                ]);
                $inspection->photo_path = $path;
                $inspection->recorded_by = $user->id;
                $inspection->save();
                if ($odometerKm !== null && $odometerKm > (int) $locked->odometer_km) {
                    $locked->forceFill(['odometer_km' => $odometerKm])->save();
                }

                return $inspection;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk(self::DISK)->delete($path);
            }
            throw $e;
        }

        $this->audit->log('vehicle.inspection_submitted', $inspection, [], ['vehicle' => $vehicle->registration_number, 'issues' => $issues]);
        if ($issues > 0) {
            $vehicle->tenant()->first()?->ownerUser()?->notify(new VehicleInspectionIssue(
                $vehicle->displayRegistration(), $driver->name, $inspection->issueLabels(), $notes, route('operator.vehicles.show', [$vehicle, 'tab' => 'inspections'])
            ));
        }

        return $inspection;
    }

    public function resolve(VehicleInspection $inspection, User $user): void
    {
        if (! $inspection->hasOpenIssues()) {
            throw ValidationException::withMessages(['inspection' => __('Nothing to resolve.')]);
        }
        $inspection->forceFill(['resolved_at' => now(), 'resolved_by' => $user->id])->save();
        $this->audit->log('vehicle.inspection_resolved', $inspection, [], ['issues' => $inspection->issues_count]);
    }
}
