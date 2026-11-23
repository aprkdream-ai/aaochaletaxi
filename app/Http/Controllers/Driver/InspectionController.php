<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleInspection;
use App\Services\VehicleInspectionService;
use App\Support\FleetOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Daily pre-trip vehicle checklist in the driver app. */
class InspectionController extends Controller
{
    public function create(Request $request): View
    {
        $driver = $this->driver($request);
        $vehicles = Vehicle::query()->where('status', '!=', 'inactive')->orderBy('registration_number')->get(['id', 'registration_number', 'make', 'model', 'odometer_km']);
        $suggested = Trip::query()->where('driver_id', $driver->id)->whereIn('status', ['started', 'assigned'])->whereDate('start_at', '<=', today())
            ->orderByRaw("case when status = 'started' then 0 else 1 end")->orderBy('start_at')->value('vehicle_id')
            ?? VehicleInspection::query()->where('driver_id', $driver->id)->latest('id')->value('vehicle_id');

        return view('driver.check', [
            'driver' => $driver,
            'vehicles' => $vehicles,
            'selected' => (int) old('vehicle_id', $suggested),
            'items' => FleetOptions::inspectionItems(),
            'todayChecks' => VehicleInspection::query()->with('vehicle:id,registration_number')->where('driver_id', $driver->id)->whereDate('inspected_on', today())->latest('id')->get(),
        ]);
    }

    public function store(Request $request, VehicleInspectionService $service): RedirectResponse
    {
        $driver = $this->driver($request);
        $rules = [
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where('tenant_id', $driver->tenant_id)->whereNull('deleted_at')->whereNot('status', 'inactive')],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
        foreach (array_keys(FleetOptions::inspectionItems()) as $key) {
            $rules["items.$key"] = ['required', 'in:ok,issue'];
        }
        $data = $request->validate($rules, ['items.*.required' => __('Mark every item as OK or Problem.')]);

        $inspection = $service->submit($driver, Vehicle::query()->findOrFail($data['vehicle_id']), $data['items'],
            isset($data['odometer_km']) ? (int) $data['odometer_km'] : null, $data['notes'] ?? null, $request->file('photo'), $request->user());

        return redirect()->route('driver.check')->with('success', $inspection->issues_count
            ? __('Check saved. Your operator has been alerted about the problem.')
            : __('Check saved. All OK — drive safe!'));
    }

    private function driver(Request $request): Driver
    {
        return Driver::query()->where('user_id', $request->user()->id)->firstOrFail();
    }
}
