<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\FleetDocument;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleMaintenance;
use App\Services\AuditLogger;
use App\Services\FleetDocumentService;
use App\Support\FleetOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Documents, maintenance and expenses attached to vehicles / drivers. */
class FleetRecordController extends Controller
{
    public function __construct(private FleetDocumentService $documents, private AuditLogger $audit) {}

    public function storeVehicleDocument(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $this->documents->upload($vehicle, 'vehicle', ...$this->documentInput($request, FleetOptions::vehicleDocuments()));

        return back()->with('success', __('Document uploaded.'));
    }

    public function storeDriverDocument(Request $request, Driver $driver): RedirectResponse
    {
        $this->documents->upload($driver, 'driver', ...$this->documentInput($request, FleetOptions::driverDocuments()));

        return back()->with('success', __('Document uploaded.'));
    }

    public function downloadDocument(FleetDocument $document): StreamedResponse
    {
        $this->authorizeDocument($document, 'view');

        return $this->documents->stream($document);
    }

    public function destroyDocument(FleetDocument $document): RedirectResponse
    {
        $this->authorizeDocument($document, 'manage');
        $this->documents->delete($document);

        return back()->with('success', __('Document deleted.'));
    }

    public function storeMaintenance(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'service_date' => ['required', 'date', 'before_or_equal:today'],
            'type' => ['required', Rule::in(array_keys(FleetOptions::maintenanceTypes()))],
            'description' => ['required', 'string', 'max:500'],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'cost' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'next_service_date' => ['nullable', 'date', 'after:service_date'],
            'next_service_km' => ['nullable', 'integer', 'min:0'],
        ]);
        $record = new VehicleMaintenance($data + ['vehicle_id' => $vehicle->id]);
        $record->recorded_by = $request->user()->id;
        $record->save();
        if (($data['odometer_km'] ?? 0) > (int) $vehicle->odometer_km) {
            $vehicle->forceFill(['odometer_km' => $data['odometer_km']])->save();
        }
        $this->audit->log('vehicle.maintenance_added', $vehicle, [], ['type' => $data['type'], 'cost' => $data['cost']]);

        return back()->with('success', __('Maintenance recorded.'));
    }

    public function destroyMaintenance(VehicleMaintenance $maintenance): RedirectResponse
    {
        $maintenance->delete();
        $this->audit->log('vehicle.maintenance_deleted', $maintenance, ['cost' => $maintenance->cost], []);

        return back()->with('success', __('Maintenance entry deleted.'));
    }

    public function storeExpense(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::in(array_keys(FleetOptions::expenseCategories()))],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'litres' => ['nullable', 'required_if:category,fuel', 'numeric', 'min:0.1', 'max:2000'],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'full_tank' => ['nullable', 'boolean'],
            'payment_method' => ['nullable', 'in:cash,upi,card,bank_transfer,fastag,other'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['litres.required_if' => __('Enter litres (or kWh) for fuel so mileage can be calculated.')]);
        if ($data['category'] === 'fuel') {
            $data['full_tank'] = (bool) ($data['full_tank'] ?? false);
        } else {
            unset($data['litres'], $data['odometer_km'], $data['full_tank']);
        }
        $expense = new VehicleExpense($data + ['vehicle_id' => $vehicle->id]);
        $expense->recorded_by = $request->user()->id;
        $expense->save();
        if (($data['odometer_km'] ?? 0) > (int) $vehicle->odometer_km) {
            $vehicle->forceFill(['odometer_km' => $data['odometer_km']])->save();
        }
        $this->audit->log('vehicle.expense_added', $vehicle, [], ['category' => $data['category'], 'amount' => $data['amount']]);

        return back()->with('success', __('Expense recorded.'));
    }

    public function destroyExpense(VehicleExpense $expense): RedirectResponse
    {
        $expense->delete();
        $this->audit->log('vehicle.expense_deleted', $expense, ['amount' => $expense->amount], []);

        return back()->with('success', __('Expense deleted.'));
    }

    /** @return array{0: UploadedFile, 1: string, 2: ?string, 3: ?string, 4: User} */
    private function documentInput(Request $request, array $types): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys($types))],
            'number' => ['nullable', 'string', 'max:60'],
            'expires_on' => ['nullable', 'date'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ], ['file.max' => __('The file must not be larger than 5 MB.')]);

        return [$request->file('file'), $data['type'], $data['number'] ?? null, $data['expires_on'] ?? null, $request->user()];
    }

    private function authorizeDocument(FleetDocument $document, string $ability): void
    {
        $prefix = $document->documentable_type === (new Driver)->getMorphClass() ? 'drivers' : 'vehicles';
        abort_unless(request()->user()->hasPermission("$prefix.$ability") || ($ability === 'view' && request()->user()->hasPermission("$prefix.manage")), 403);
    }
}
