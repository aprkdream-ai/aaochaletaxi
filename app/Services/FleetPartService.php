<?php

namespace App\Services;

use App\Models\FleetPart;
use App\Models\FleetPartMovement;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Fit / remove / scrap tyres, batteries and parts; km run is accumulated per fitting from the vehicle odometer. */
class FleetPartService
{
    public function __construct(private AuditLogger $audit) {}

    public function create(array $data, User $user): FleetPart
    {
        $part = FleetPart::create($data);
        $this->audit->log('part.created', $part, [], ['type' => $part->type, 'label' => $part->label(), 'cost' => $part->cost]);

        return $part;
    }

    public function fit(FleetPart $part, Vehicle $vehicle, ?string $position, ?int $odometerKm, string $date, User $user): void
    {
        DB::transaction(function () use ($part, $vehicle, $position, $odometerKm, $date, $user) {
            $v = Vehicle::query()->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
            $p = FleetPart::query()->whereKey($part->id)->lockForUpdate()->firstOrFail();
            if ($p->status !== 'in_stock') {
                throw ValidationException::withMessages(['part' => __('Only parts in stock can be fitted.')]);
            }
            if ($position && $position !== 'spare' && FleetPart::query()->where('vehicle_id', $v->id)->where('status', 'fitted')->where('type', $p->type)->where('position', $position)->exists()) {
                throw ValidationException::withMessages(['position' => __('Another :type is already fitted at this position. Remove it first.', ['type' => __($p->type)])]);
            }
            $km = $odometerKm ?? $v->odometer_km;
            $p->forceFill(['status' => 'fitted', 'vehicle_id' => $v->id, 'position' => $position, 'fitted_on' => $date, 'fitted_at_km' => $km])->save();
            if ($km !== null && $km > (int) $v->odometer_km) {
                $v->forceFill(['odometer_km' => $km])->save();
            }
            $this->move($p, 'fit', $v->id, $position, $km, null, $date, $user);
        });
    }

    /** @param 'in_stock'|'scrapped' $to */
    public function remove(FleetPart $part, ?int $odometerKm, string $date, string $to, User $user, ?string $notes = null): void
    {
        DB::transaction(function () use ($part, $odometerKm, $date, $to, $user, $notes) {
            $p = FleetPart::query()->whereKey($part->id)->lockForUpdate()->firstOrFail();
            if ($p->status === 'scrapped') {
                throw ValidationException::withMessages(['part' => __('This part is already scrapped.')]);
            }
            $run = null;
            $vehicleId = $p->vehicle_id;
            if ($p->status === 'fitted') {
                $km = $odometerKm ?? (int) Vehicle::withTrashed()->whereKey($vehicleId)->value('odometer_km');
                if ($p->fitted_at_km !== null && $km < $p->fitted_at_km) {
                    throw ValidationException::withMessages(['odometer_km' => __('Odometer cannot be lower than when it was fitted (:km km).', ['km' => number_format($p->fitted_at_km)])]);
                }
                $run = $p->fitted_at_km !== null ? $km - $p->fitted_at_km : 0;
                $p->km_run += $run;
                $this->move($p, 'remove', $vehicleId, $p->position, $km, $run, $date, $user, $notes);
            }
            $p->forceFill(['status' => $to, 'vehicle_id' => null, 'position' => null, 'fitted_on' => null, 'fitted_at_km' => null])->save();
            if ($to === 'scrapped') {
                $this->move($p, 'scrap', $vehicleId, null, null, null, $date, $user, $notes);
            }
        });
    }

    private function move(FleetPart $p, string $action, ?int $vehicleId, ?string $position, ?int $km, ?int $run, string $date, User $user, ?string $notes = null): void
    {
        $m = new FleetPartMovement(['fleet_part_id' => $p->id, 'action' => $action, 'vehicle_id' => $vehicleId, 'position' => $position, 'odometer_km' => $km, 'km_run' => $run, 'moved_on' => $date, 'notes' => $notes]);
        $m->recorded_by = $user->id;
        $m->save();
        $this->audit->log('part.'.$action, $p, [], ['vehicle_id' => $vehicleId, 'km' => $km, 'km_run' => $run]);
    }
}
