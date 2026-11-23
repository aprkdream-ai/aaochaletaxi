<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleOwnerPayout;
use App\Services\Finance\DriverEarningsService;
use App\Services\Finance\VehicleOwnerService;
use App\Support\FleetOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Statements and payouts for owners of attached / vendor vehicles. */
class OwnerPayoutController extends Controller
{
    public function __construct(private VehicleOwnerService $owners) {}

    public function index(Request $request): View
    {
        [$from, $to] = $this->range($request);
        $vehicles = Vehicle::query()->whereIn('ownership', ['attached', 'vendor'])->orderBy('registration_number')->get();
        $calc = $this->owners->computeMany($vehicles, $from, $to);

        return view('operator.owner-payouts.index', [
            'from' => $from, 'to' => $to,
            'rows' => $vehicles->map(fn (Vehicle $v) => (object) (['vehicle' => $v] + $calc[$v->id])),
            'recent' => VehicleOwnerPayout::query()->with(['vehicle:id,registration_number,owner_name', 'recorder:id,name'])->latest('id')->limit(15)->get(),
        ]);
    }

    public function show(Request $request, Vehicle $vehicle): View
    {
        abort_unless($vehicle->isAttached(), 404);
        [$from, $to] = $this->range($request);

        return view('operator.owner-payouts.show', [
            'vehicle' => $vehicle, 'from' => $from, 'to' => $to, 'calc' => $this->owners->compute($vehicle, $from, $to),
            'history' => VehicleOwnerPayout::query()->with('recorder:id,name')->where('vehicle_id', $vehicle->id)->latest('id')->get(),
        ]);
    }

    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'], 'deductions' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'method' => ['required', Rule::in(array_keys(FleetOptions::payoutMethods()))], 'reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
        [$from, $to] = DriverEarningsService::parseRange($data['from'], $data['to']);
        $this->owners->settle($vehicle, $from, $to, (string) $data['amount'], (string) ($data['deductions'] ?? 0), $data['method'], $data['reference'] ?? null, $data['notes'] ?? null, $request->user());

        return back()->with('success', __('Payout of ₹:a recorded for :name.', ['a' => number_format((float) $data['amount'], 2), 'name' => $vehicle->owner_name ?: $vehicle->displayRegistration()]));
    }

    private function range(Request $request): array
    {
        $r = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);

        return DriverEarningsService::parseRange($r['from'] ?? null, $r['to'] ?? null);
    }
}
