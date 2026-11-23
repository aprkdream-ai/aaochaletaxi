<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\FleetTripRequest;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Services\FleetTripService;
use App\Services\TripScheduler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Fleet module: vehicle trips for the company's own use (no customer, no fare). */
class FleetTripController extends Controller
{
    public function __construct(private FleetTripService $trips) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:assigned,started,completed,released,open'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
            'vehicle' => ['nullable', 'integer'], 'driver' => ['nullable', 'integer'], 'department' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $trips = $this->fleet()->with(['vehicle:id,registration_number', 'driver:id,name', 'department:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $s === 'open' ? $q->whereIn('status', TripScheduler::ACTIVE) : $q->where('status', $s))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('start_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('start_at', '<=', Carbon::parse($d)->endOfDay()))
            ->when($filters['vehicle'] ?? null, fn ($q, $v) => $q->where('vehicle_id', $v))
            ->when($filters['driver'] ?? null, fn ($q, $v) => $q->where('driver_id', $v))
            ->when($filters['department'] ?? null, fn ($q, $v) => $q->where('department_id', $v))
            ->when($filters['q'] ?? null, function ($q, $t) {
                $like = '%'.addcslashes($t, '%_\\').'%';
                $q->where(fn ($w) => $w->where('trip_number', 'like', $like)->orWhere('purpose', 'like', $like)->orWhere('requested_by', 'like', $like));
            })
            ->orderByRaw("case when status in ('assigned','started') then 0 else 1 end")->orderByDesc('start_at')
            ->paginate(25)->withQueryString();

        return view('operator.fleet-trips.index', [
            'trips' => $trips, 'filters' => $filters,
            'vehicles' => Vehicle::query()->orderBy('registration_number')->get(['id', 'registration_number']),
            'drivers' => Driver::query()->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'counts' => [
                'today' => $this->fleet()->whereIn('status', TripScheduler::ACTIVE)->whereDate('start_at', today())->count(),
                'running' => $this->fleet()->where('status', 'started')->count(),
                'upcoming' => $this->fleet()->where('status', 'assigned')->where('start_at', '>', now())->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $start = now()->addHour()->startOfHour();

        return view('operator.fleet-trips.form', $this->formData(new Trip([
            'start_at' => $start, 'end_at' => $start->copy()->addHours(4),
            'vehicle_id' => $request->integer('vehicle') ?: null,
        ])));
    }

    public function store(FleetTripRequest $request): RedirectResponse
    {
        $trip = $this->trips->create($request->validated(), $request->user());

        return redirect()->route('operator.fleet-trips.show', $trip)->with('success', __('Trip :no created and sent to the driver.', ['no' => $trip->trip_number]));
    }

    public function show(Trip $trip): View
    {
        $this->ensureFleet($trip);
        $trip->load(['vehicle', 'driver', 'department']);

        return view('operator.fleet-trips.show', ['trip' => $trip]);
    }

    public function edit(Trip $trip): View|RedirectResponse
    {
        $this->ensureFleet($trip);
        if ($trip->status !== 'assigned') {
            return redirect()->route('operator.fleet-trips.show', $trip)->with('error', __('Only trips that have not started can be edited.'));
        }

        return view('operator.fleet-trips.form', $this->formData($trip));
    }

    public function update(FleetTripRequest $request, Trip $trip): RedirectResponse
    {
        $this->ensureFleet($trip);
        $this->trips->update($trip, $request->validated(), $request->user());

        return redirect()->route('operator.fleet-trips.show', $trip)->with('success', __('Trip updated.'));
    }

    public function start(Request $request, Trip $trip): RedirectResponse
    {
        $this->ensureFleet($trip);
        $data = $request->validate(['start_km' => ['required', 'integer', 'min:0', 'max:5000000']]);
        $this->trips->start($trip, (int) $data['start_km'], $request->user());

        return back()->with('success', __('Trip started.'));
    }

    public function complete(Request $request, Trip $trip): RedirectResponse
    {
        $this->ensureFleet($trip);
        $data = $request->validate(['end_km' => ['required', 'integer', 'min:0', 'max:5000000']]);
        $this->trips->complete($trip, (int) $data['end_km'], $request->user());

        return back()->with('success', __('Trip completed.'));
    }

    public function cancel(Request $request, Trip $trip): RedirectResponse
    {
        $this->ensureFleet($trip);
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);
        $this->trips->cancel($trip, $data['reason'], $request->user());

        return back()->with('success', __('Trip cancelled.'));
    }

    /** Busy vehicles/drivers for a time window (form hints; the server re-checks under locks on save). */
    public function availability(Request $request, TripScheduler $scheduler): JsonResponse
    {
        $data = $request->validate(['start_at' => ['required', 'date'], 'end_at' => ['required', 'date', 'after:start_at'], 'except' => ['nullable', 'integer']]);
        [$start, $end] = [Carbon::parse($data['start_at']), Carbon::parse($data['end_at'])];

        return response()->json([
            'vehicles' => $scheduler->busy('vehicle_id', $start, $end, $data['except'] ?? null),
            'drivers' => $scheduler->busy('driver_id', $start, $end, $data['except'] ?? null),
        ]);
    }

    private function formData(Trip $trip): array
    {
        return [
            'trip' => $trip,
            'vehicles' => Vehicle::query()->where('status', 'active')->orderBy('registration_number')->get()
                ->filter(fn (Vehicle $v) => $v->id === $trip->vehicle_id || $v->isAssignable())->values(),
            'drivers' => Driver::query()->where('status', 'active')->orderBy('name')->get()
                ->filter(fn (Driver $d) => $d->id === $trip->driver_id || $d->isAssignable())->values(),
            'departments' => Department::query()->active()->get(),
        ];
    }

    private function fleet()
    {
        return Trip::query()->whereNull('booking_id');
    }

    private function ensureFleet(Trip $trip): void
    {
        abort_unless($trip->isFleet(), 404);
    }
}
