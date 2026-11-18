<?php

namespace App\Http\Controllers\Operator;

use App\Enums\DriverStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Driver;
use App\Services\DriverService;
use App\Services\PlanLimitService;
use App\Support\FleetOptions;
use App\Support\Phone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class DriverController extends Controller
{
    public function __construct(private DriverService $service) {}

    public function index(Request $request, TenantContext $context): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::enum(DriverStatus::class)],
            'branch' => ['nullable', 'integer'],
            'compliance' => ['nullable', 'in:attention'],
        ]);
        $soon = now()->addDays(30)->toDateString();

        return view('operator.drivers.index', [
            'drivers' => Driver::query()->with(['branch:id,name', 'user:id,status,last_login_at'])
                ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($t, '%_\\').'%')
                    ->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $t).'%')->orWhere('licence_number', 'like', '%'.addcslashes($t, '%_\\').'%')))
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->when($filters['branch'] ?? null, fn ($q, $b) => $q->where('branch_id', $b))
                ->when($filters['compliance'] ?? null, fn ($q) => $q->where('licence_expiry', '<=', $soon))
                ->orderBy('name')->paginate(24)->withQueryString(),
            'filters' => $filters,
            'branches' => Branch::query()->active()->get(['id', 'name']),
            'counts' => [
                'total' => Driver::query()->count(),
                'available' => Driver::query()->where('status', 'active')->where('is_available', true)->count(),
                'app' => Driver::query()->whereNotNull('user_id')->count(),
                'attention' => Driver::query()->where('licence_expiry', '<=', $soon)->count(),
            ],
            'usage' => app(PlanLimitService::class)->usage($context->get())['drivers'],
            'limit' => $context->get()->usableSubscription()?->plan->max_drivers,
        ]);
    }

    public function create(): View
    {
        return view('operator.drivers.form', ['driver' => new Driver(['status' => DriverStatus::Active, 'is_available' => true, 'pay_type' => 'monthly', 'joined_on' => now()]), 'branches' => Branch::query()->active()->get(['id', 'name'])]);
    }

    public function store(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $this->validated($request, $context);
        $password = $request->boolean('app_access') ? $request->validate(['app_password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]])['app_password'] : null;
        $driver = $this->service->create($context->get(), $data, $password);
        $this->savePhoto($request, $driver);

        return redirect()->route('operator.drivers.show', $driver)->with('success', __('Driver :name added.', ['name' => $driver->name]));
    }

    public function show(Driver $driver): View
    {
        return view('operator.drivers.show', ['driver' => $driver->load(['branch', 'documents', 'user'])]);
    }

    public function edit(Driver $driver): View
    {
        return view('operator.drivers.form', ['driver' => $driver, 'branches' => Branch::query()->active()->get(['id', 'name'])]);
    }

    public function update(Request $request, Driver $driver, TenantContext $context): RedirectResponse
    {
        $this->service->update($driver, $this->validated($request, $context, $driver));
        $this->savePhoto($request, $driver);

        return redirect()->route('operator.drivers.show', $driver)->with('success', __('Driver updated.'));
    }

    public function appAccess(Request $request, Driver $driver): RedirectResponse
    {
        if ($request->boolean('disable')) {
            $this->service->disableAppAccess($driver);

            return back()->with('success', __('Driver app access disabled.'));
        }
        $data = $request->validate(['app_password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()]]);
        $this->service->enableAppAccess($driver, $data['app_password']);

        return back()->with('success', __('Driver can now log in with :phone and the password you set.', ['phone' => $driver->phone]));
    }

    public function toggleAvailability(Driver $driver): RedirectResponse
    {
        $driver->forceFill(['is_available' => ! $driver->is_available])->save();

        return back()->with('success', $driver->is_available ? __('Marked available.') : __('Marked unavailable.'));
    }

    public function destroy(Driver $driver): RedirectResponse
    {
        $this->service->delete($driver);

        return redirect()->route('operator.drivers.index')->with('success', __('Driver removed. Trip history is kept.'));
    }

    private function validated(Request $request, TenantContext $context, ?Driver $driver = null): array
    {
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')),
            'alt_phone' => Phone::normalize($request->input('alt_phone')),
            'licence_number' => strtoupper(preg_replace('/[\s-]/', '', (string) $request->input('licence_number'))),
        ]);
        $tenantId = $context->id();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/', Rule::unique('drivers', 'phone')->where('tenant_id', $tenantId)->whereNull('deleted_at')->ignore($driver)],
            'alt_phone' => ['nullable', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'licence_number' => ['required', 'regex:/^[A-Z]{2}\d{2}[A-Z0-9]{7,13}$/', Rule::unique('drivers', 'licence_number')->where('tenant_id', $tenantId)->whereNull('deleted_at')->ignore($driver)],
            'licence_type' => ['nullable', Rule::in(array_keys(FleetOptions::licenceTypes()))],
            'licence_expiry' => ['required', 'date'],
            'aadhaar_last4' => ['nullable', 'digits:4'],
            'date_of_birth' => ['nullable', 'date', 'before:-18 years'],
            'joined_on' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'pay_type' => ['required', Rule::in(array_keys(FleetOptions::payTypes()))],
            'pay_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'status' => ['required', Rule::enum(DriverStatus::class)],
            'is_available' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ], [
            'phone.regex' => __('Enter a valid 10-digit Indian mobile number.'),
            'phone.unique' => __('A driver with this mobile number already exists.'),
            'licence_number.regex' => __('Enter a valid driving licence number, e.g. MH1220190012345.'),
            'licence_number.unique' => __('A driver with this licence number already exists.'),
            'date_of_birth.before' => __('Driver must be at least 18 years old.'),
        ]);
        $data['is_available'] = (bool) ($data['is_available'] ?? false);
        unset($data['photo']);

        return $data;
    }

    private function savePhoto(Request $request, Driver $driver): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }
        $old = $driver->photo_path;
        $driver->forceFill(['photo_path' => $request->file('photo')->storeAs('drivers/'.$driver->tenant_id, Str::random(30).'.'.$request->file('photo')->guessExtension(), 'public')])->save();
        if ($old) {
            Storage::disk('public')->delete($old);
        }
    }
}
