<?php

namespace App\Http\Controllers\Operator;

use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Driver;
use App\Models\FleetPart;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\AuditLogger;
use App\Services\FuelEconomyService;
use App\Services\PlanLimitService;
use App\Services\VehicleLoanService;
use App\Support\FleetOptions;
use App\Support\Money;
use App\Support\Phone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::enum(VehicleStatus::class)],
            'category' => ['nullable', 'integer'],
            'branch' => ['nullable', 'integer'],
            'compliance' => ['nullable', 'in:attention'],
        ]);
        $soon = now()->addDays(30)->toDateString();

        $vehicles = Vehicle::query()->with(['category:id,name,icon', 'branch:id,name'])
            ->when($filters['q'] ?? null, function ($q, $t) {
                $norm = Vehicle::normalizeRegistration($t);
                $like = '%'.addcslashes($t, '%_\\').'%';
                $q->where(fn ($w) => $w->where('registration_number', 'like', '%'.$norm.'%')->orWhere('make', 'like', $like)->orWhere('model', 'like', $like));
            })
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('vehicle_category_id', $c))
            ->when($filters['branch'] ?? null, fn ($q, $b) => $q->where('branch_id', $b))
            ->when($filters['compliance'] ?? null, fn ($q) => $q->needsAttention($soon))
            ->orderBy('registration_number')->paginate(24)->withQueryString();

        return view('operator.vehicles.index', [
            'vehicles' => $vehicles, 'filters' => $filters,
            'categories' => VehicleCategory::query()->availableTo($context->id())->get(),
            'branches' => Branch::query()->active()->get(['id', 'name']),
            'counts' => [
                'total' => Vehicle::query()->count(),
                'available' => Vehicle::query()->where('status', 'active')->where('is_available', true)->count(),
                'maintenance' => Vehicle::query()->where('status', 'maintenance')->count(),
                'attention' => Vehicle::query()->needsAttention($soon)->count(),
            ],
            'usage' => app(PlanLimitService::class)->usage($context->get())['vehicles'],
            'limit' => $context->get()->usableSubscription()?->plan->max_vehicles,
        ]);
    }

    public function create(TenantContext $context): View
    {
        return view('operator.vehicles.form', $this->formData($context, new Vehicle(['status' => VehicleStatus::Active, 'is_available' => true, 'is_ac' => true, 'fuel_type' => 'diesel', 'ownership' => 'owned', 'vehicle_type' => 'car'])));
    }

    public function store(Request $request, TenantContext $context, PlanLimitService $limits, AuditLogger $audit): RedirectResponse
    {
        $limits->assertCanAdd($context->get(), 'vehicles');
        $data = $this->validated($request, $context);
        $vehicle = Vehicle::create($data);
        $this->saveImage($request, $vehicle);
        $audit->log('vehicle.created', $vehicle, [], ['registration' => $vehicle->registration_number]);

        return redirect()->route('operator.vehicles.show', $vehicle)->with('success', __('Vehicle :reg added. Upload its documents below.', ['reg' => $vehicle->displayRegistration()]));
    }

    public function show(Vehicle $vehicle, FuelEconomyService $fuel): View
    {
        $vehicle->load(['category', 'branch', 'documents', 'maintenance.recorder:id,name', 'expenses.recorder:id,name',
            'inspections' => fn ($q) => $q->with(['driver:id,name', 'resolver:id,name'])->limit(60),
            'parts', 'incidents' => fn ($q) => $q->with(['driver:id,name', 'expense:id,amount'])->limit(50), 'loans.payments']);
        $fills = $vehicle->expenses->where('category', 'fuel')->filter(fn ($e) => (float) $e->litres > 0)
            ->sortBy([['expense_date', 'asc'], ['id', 'asc']])->values();

        return view('operator.vehicles.show', [
            'vehicle' => $vehicle,
            'fuel' => $fuel->compute($fills),
            'loanSchedules' => $vehicle->loans->mapWithKeys(fn ($l) => [$l->id => app(VehicleLoanService::class)->schedule($l)]),
            'drivers' => Driver::query()->orderBy('name')->get(['id', 'name']),
            'stockParts' => FleetPart::query()->where('status', 'in_stock')->orderBy('type')->get(),
            'totals' => [
                'maintenance' => Money::add(...$vehicle->maintenance->pluck('cost')->map(fn ($c) => (string) $c)->all() ?: ['0']),
                'expenses_month' => Money::add(...$vehicle->expenses->filter(fn ($e) => $e->expense_date->isSameMonth(now()))->pluck('amount')->map(fn ($c) => (string) $c)->all() ?: ['0']),
                'expenses_all' => Money::add(...$vehicle->expenses->pluck('amount')->map(fn ($c) => (string) $c)->all() ?: ['0']),
            ],
        ]);
    }

    public function edit(Vehicle $vehicle, TenantContext $context): View
    {
        return view('operator.vehicles.form', $this->formData($context, $vehicle));
    }

    public function update(Request $request, Vehicle $vehicle, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, $context, $vehicle);
        $old = $vehicle->only(array_keys($data));
        $vehicle->update($data);
        $this->saveImage($request, $vehicle);
        $audit->log('vehicle.updated', $vehicle, array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $old), $data);

        return redirect()->route('operator.vehicles.show', $vehicle)->with('success', __('Vehicle updated.'));
    }

    public function toggleAvailability(Vehicle $vehicle, AuditLogger $audit): RedirectResponse
    {
        $vehicle->forceFill(['is_available' => ! $vehicle->is_available])->save();
        $audit->log('vehicle.availability', $vehicle, [], ['is_available' => $vehicle->is_available]);

        return back()->with('success', $vehicle->is_available ? __('Marked available.') : __('Marked unavailable.'));
    }

    public function destroy(Vehicle $vehicle, AuditLogger $audit): RedirectResponse
    {
        $vehicle->delete();
        $audit->log('vehicle.deleted', $vehicle, ['registration' => $vehicle->registration_number]);

        return redirect()->route('operator.vehicles.index')->with('success', __('Vehicle removed. Its history is kept.'));
    }

    private function formData(TenantContext $context, Vehicle $vehicle): array
    {
        return [
            'vehicle' => $vehicle,
            'categories' => VehicleCategory::query()->availableTo($context->id())->get(),
            'branches' => Branch::query()->active()->get(['id', 'name']),
        ];
    }

    private function validated(Request $request, TenantContext $context, ?Vehicle $vehicle = null): array
    {
        $request->merge([
            'registration_number' => Vehicle::normalizeRegistration($request->input('registration_number')),
            'owner_phone' => $request->filled('owner_phone') ? Phone::normalize($request->input('owner_phone')) : null,
            'owner_pan' => $request->filled('owner_pan') ? strtoupper(trim((string) $request->input('owner_pan'))) : null,
        ]);
        $tenantId = $context->id();

        $data = $request->validate([
            'registration_number' => ['required', 'regex:/^([A-Z]{2}\d{1,2}[A-Z]{0,3}\d{1,4}|\d{2}BH\d{4}[A-Z]{1,2})$/',
                Rule::unique('vehicles', 'registration_number')->where('tenant_id', $tenantId)->whereNull('deleted_at')->ignore($vehicle)],
            'vehicle_category_id' => ['required', 'integer', Rule::exists('vehicle_categories', 'id')->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'make' => ['required', 'string', 'max:60'],
            'model' => ['required', 'string', 'max:60'],
            'year' => ['nullable', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
            'color' => ['nullable', 'string', 'max:30'],
            'fuel_type' => ['required', Rule::in(array_keys(FleetOptions::fuelTypes()))],
            'seats' => ['required', 'integer', 'min:1', 'max:60'],
            'is_ac' => ['nullable', 'boolean'],
            'ownership' => ['required', Rule::in(array_keys(FleetOptions::ownership()))],
            'status' => ['required', Rule::enum(VehicleStatus::class)],
            'is_available' => ['nullable', 'boolean'],
            'insurance_expiry' => ['nullable', 'date'],
            'permit_expiry' => ['nullable', 'date'],
            'fitness_expiry' => ['nullable', 'date'],
            'puc_expiry' => ['nullable', 'date'],
            'road_tax_expiry' => ['nullable', 'date'],
            'fastag_id' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-]+$/'],
            'vehicle_type' => ['nullable', Rule::in(array_keys(FleetOptions::vehicleTypes()))],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'depreciation_method' => ['nullable', Rule::in(array_keys(FleetOptions::depreciationMethods()))],
            'depreciation_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'owner_name' => ['nullable', 'required_if:ownership,attached,vendor', 'string', 'max:150'],
            'owner_phone' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'owner_pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'owner_bank' => ['nullable', 'string', 'max:300'],
            'owner_rate_type' => ['nullable', Rule::in(array_keys(FleetOptions::ownerRateTypes()))],
            'owner_rate' => ['nullable', 'required_with:owner_rate_type', 'numeric', 'min:0', 'max:9999999'],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], ['registration_number.regex' => __('Enter a valid Indian registration number, e.g. MH12AB1234 or 22BH1234AA.'), 'registration_number.unique' => __('This vehicle is already in your fleet.')]);

        $data['vehicle_type'] ??= 'car';
        $data['depreciation_method'] ??= 'wdv';
        if (! in_array($data['ownership'], ['attached', 'vendor'], true)) {
            foreach (['owner_name', 'owner_phone', 'owner_pan', 'owner_bank', 'owner_rate_type', 'owner_rate'] as $k) {
                $data[$k] = null;
            }
        }
        $data['fastag_id'] = isset($data['fastag_id']) ? strtoupper($data['fastag_id']) : null;
        $data['is_ac'] = (bool) ($data['is_ac'] ?? false);
        $data['is_available'] = (bool) ($data['is_available'] ?? false);
        unset($data['image']);

        return $data;
    }

    private function saveImage(Request $request, Vehicle $vehicle): void
    {
        if (! $request->hasFile('image')) {
            return;
        }
        $old = $vehicle->image_path;
        $vehicle->forceFill(['image_path' => $request->file('image')->storeAs('vehicles/'.$vehicle->tenant_id, Str::random(30).'.'.$request->file('image')->guessExtension(), 'public')])->save();
        if ($old) {
            Storage::disk('public')->delete($old);
        }
    }
}
