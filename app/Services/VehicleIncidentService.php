<?php

namespace App\Services;

use App\Models\User;
use App\Models\VehicleExpense;
use App\Models\VehicleIncident;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Accidents / challans / damage. Paying records one vehicle expense (append-only money); claims are tracked on the incident. */
class VehicleIncidentService
{
    public const DISK = 'local';

    public function __construct(private AuditLogger $audit) {}

    public function create(array $data, ?UploadedFile $photo, User $user): VehicleIncident
    {
        $incident = new VehicleIncident($data);
        $incident->recorded_by = $user->id;
        if ($photo) {
            $incident->photo_path = $photo->storeAs('incidents/'.$user->tenant_id, Str::random(32).'.'.$photo->guessExtension(), self::DISK);
        }
        $incident->save();
        $this->audit->log('incident.created', $incident, [], ['type' => $incident->type, 'amount' => $incident->amount]);

        return $incident;
    }

    public function updateClaim(VehicleIncident $incident, array $data): void
    {
        if (($data['claim_status'] ?? null) === 'settled' && ! isset($data['settled_amount'])) {
            throw ValidationException::withMessages(['settled_amount' => __('Enter the settled amount.')]);
        }
        $old = $incident->only(['claim_status', 'settled_amount']);
        $incident->fill($data)->save();
        $this->audit->log('incident.claim_updated', $incident, $old, $incident->only(['claim_status', 'settled_amount']));
    }

    /** Record the payment (fine / repair) once, as a vehicle expense. */
    public function pay(VehicleIncident $incident, string $amount, string $date, ?string $method, User $user): VehicleExpense
    {
        return DB::transaction(function () use ($incident, $amount, $date, $method, $user) {
            $locked = VehicleIncident::query()->whereKey($incident->id)->lockForUpdate()->firstOrFail();
            if ($locked->expense_id) {
                throw ValidationException::withMessages(['amount' => __('Payment for this incident is already recorded.')]);
            }
            $expense = new VehicleExpense([
                'vehicle_id' => $locked->vehicle_id, 'expense_date' => $date, 'category' => $locked->type === 'challan' ? 'fine' : 'other',
                'amount' => $amount, 'payment_method' => $method, 'reference' => $locked->reference,
                'notes' => Str::limit(__(':type on :date', ['type' => __(ucfirst($locked->type)), 'date' => $locked->occurred_on->format('d M Y')]).' — '.$locked->description, 490),
            ]);
            $expense->recorded_by = $user->id;
            $expense->save();
            $locked->forceFill(['expense_id' => $expense->id])->save();
            $this->audit->log('incident.paid', $locked, [], ['amount' => $amount, 'expense_id' => $expense->id]);
            $incident->setRawAttributes($locked->getAttributes(), true);

            return $expense;
        });
    }

    public function close(VehicleIncident $incident): void
    {
        $incident->forceFill(['status' => 'closed'])->save();
        $this->audit->log('incident.closed', $incident, [], []);
    }

    public function photoExists(VehicleIncident $incident): bool
    {
        return $incident->photo_path && Storage::disk(self::DISK)->exists($incident->photo_path);
    }
}
