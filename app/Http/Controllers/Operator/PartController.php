<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\FleetPart;
use App\Models\Vehicle;
use App\Services\FleetPartService;
use App\Support\FleetOptions;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Tyres, batteries and other parts: stock, fitted on vehicles, scrapped. */
class PartController extends Controller
{
    public function __construct(private FleetPartService $parts) {}

    public function index(Request $request): View
    {
        $f = $request->validate(['type' => ['nullable', Rule::in(array_keys(FleetOptions::partTypes()))], 'status' => ['nullable', 'in:in_stock,fitted,scrapped'], 'q' => ['nullable', 'string', 'max:60']]);
        $parts = FleetPart::query()->with('vehicle:id,registration_number,odometer_km')
            ->when($f['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', 'scrapped'))
            ->when($f['q'] ?? null, function ($q, $t) {
                $like = '%'.addcslashes($t, '%_\\').'%';
                $q->where(fn ($w) => $w->where('brand', 'like', $like)->orWhere('model', 'like', $like)->orWhere('serial_number', 'like', $like));
            })
            ->orderByRaw("case status when 'fitted' then 0 when 'in_stock' then 1 else 2 end")->orderBy('type')->orderByDesc('id')->paginate(30)->withQueryString();

        return view('operator.parts.index', [
            'parts' => $parts, 'filters' => $f,
            'vehicles' => Vehicle::query()->where('status', '!=', 'inactive')->orderBy('registration_number')->get(['id', 'registration_number', 'odometer_km']),
            'counts' => FleetPart::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(FleetOptions::partTypes()))],
            'brand' => ['required', 'string', 'max:60'], 'model' => ['nullable', 'string', 'max:80'],
            'serial_number' => ['nullable', 'string', 'max:60'], 'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999'], 'vendor' => ['nullable', 'string', 'max:120'],
            'warranty_until' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:500'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);
        $qty = (int) ($data['quantity'] ?? 1);
        unset($data['quantity']);
        $data['cost'] ??= 0;
        if ($qty > 1 && ! empty($data['serial_number'])) {
            return back()->withInput()->withErrors(['quantity' => __('Add parts with serial numbers one at a time.')]);
        }
        for ($i = 0; $i < $qty; $i++) {
            $this->parts->create($data, $request->user());
        }

        return back()->with('success', trans_choice('{1} Part added to stock.|[2,*] :n parts added to stock.', $qty, ['n' => $qty]));
    }

    public function fit(Request $request, FleetPart $part, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where('tenant_id', $context->id())->whereNull('deleted_at')],
            'position' => ['nullable', Rule::in(array_keys(FleetOptions::partPositions()))],
            'odometer_km' => ['nullable', 'integer', 'min:0', 'max:5000000'], 'moved_on' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $this->parts->fit($part, Vehicle::query()->findOrFail($data['vehicle_id']), $data['position'] ?? null, isset($data['odometer_km']) ? (int) $data['odometer_km'] : null, $data['moved_on'], $request->user());

        return back()->with('success', __('Part fitted.'));
    }

    public function remove(Request $request, FleetPart $part): RedirectResponse
    {
        $data = $request->validate([
            'to' => ['required', 'in:in_stock,scrapped'], 'odometer_km' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'moved_on' => ['required', 'date', 'before_or_equal:today'], 'notes' => ['nullable', 'string', 'max:300'],
        ]);
        $this->parts->remove($part, isset($data['odometer_km']) ? (int) $data['odometer_km'] : null, $data['moved_on'], $data['to'], $request->user(), $data['notes'] ?? null);

        return back()->with('success', $data['to'] === 'scrapped' ? __('Part scrapped.') : __('Part removed and back in stock.'));
    }
}
