<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleIncident;
use App\Services\VehicleIncidentService;
use App\Support\FleetOptions;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Accidents, challans, theft and damage with insurance claim tracking. */
class IncidentController extends Controller
{
    public function __construct(private VehicleIncidentService $incidents) {}

    public function index(Request $request): View
    {
        $f = $request->validate(['type' => ['nullable', Rule::in(array_keys(FleetOptions::incidentTypes()))], 'status' => ['nullable', 'in:open,closed'], 'vehicle' => ['nullable', 'integer']]);

        return view('operator.incidents.index', [
            'incidents' => VehicleIncident::query()->with(['vehicle:id,registration_number', 'driver:id,name', 'expense:id,amount,expense_date'])
                ->when($f['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
                ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($f['vehicle'] ?? null, fn ($q, $v) => $q->where('vehicle_id', $v))
                ->orderByRaw("case when status = 'open' then 0 else 1 end")->orderByDesc('occurred_on')->orderByDesc('id')->paginate(25)->withQueryString(),
            'filters' => $f,
            'vehicles' => Vehicle::query()->orderBy('registration_number')->get(['id', 'registration_number']),
            'drivers' => Driver::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where('tenant_id', $context->id())],
            'driver_id' => ['nullable', 'integer', Rule::exists('drivers', 'id')->where('tenant_id', $context->id())],
            'type' => ['required', Rule::in(array_keys(FleetOptions::incidentTypes()))],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'], 'place' => ['nullable', 'string', 'max:200'],
            'reference' => ['nullable', 'string', 'max:60'], 'description' => ['required', 'string', 'max:1000'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        $photo = $request->file('photo');
        unset($data['photo']);
        $data['amount'] ??= 0;
        $this->incidents->create($data, $photo, $request->user());

        return back()->with('success', __('Incident recorded.'));
    }

    public function claim(Request $request, VehicleIncident $incident): RedirectResponse
    {
        $data = $request->validate([
            'claim_status' => ['required', Rule::in(array_keys(FleetOptions::claimStatuses()))],
            'insurer' => ['nullable', 'string', 'max:120'], 'claim_number' => ['nullable', 'string', 'max:60'],
            'settled_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);
        $this->incidents->updateClaim($incident, $data);

        return back()->with('success', __('Claim updated.'));
    }

    public function pay(Request $request, VehicleIncident $incident): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'], 'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['nullable', 'in:cash,upi,card,bank_transfer,fastag,other'],
        ]);
        $this->incidents->pay($incident, (string) $data['amount'], $data['paid_on'], $data['payment_method'] ?? null, $request->user());

        return back()->with('success', __('Payment recorded as a vehicle expense.'));
    }

    public function close(VehicleIncident $incident): RedirectResponse
    {
        $this->incidents->close($incident);

        return back()->with('success', __('Incident closed.'));
    }

    public function photo(VehicleIncident $incident): StreamedResponse
    {
        abort_unless($this->incidents->photoExists($incident), 404);

        return Storage::disk(VehicleIncidentService::DISK)->response($incident->photo_path, null, ['Cache-Control' => 'private, max-age=600']);
    }
}
