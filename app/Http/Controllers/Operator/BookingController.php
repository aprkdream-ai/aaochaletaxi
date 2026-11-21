<?php

namespace App\Http\Controllers\Operator;

use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\VehicleCategory;
use App\Services\Booking\BookingService;
use App\Services\Maps\GoogleMapsService;
use App\Support\Money;
use App\Support\Phone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(private BookingService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'view' => ['nullable', 'in:active,today,upcoming,completed,cancelled,all'],
            'q' => ['nullable', 'string', 'max:50'],
            'date' => ['nullable', 'date'],
        ]);
        $view = $filters['view'] ?? 'active';
        $open = [BookingStatus::Enquiry, BookingStatus::Quoted, BookingStatus::Confirmed, BookingStatus::Assigned, BookingStatus::Started];

        $bookings = Booking::query()->with(['category:id,name', 'trip.vehicle:id,registration_number', 'trip.driver:id,name'])
            ->when($view === 'active', fn ($q) => $q->whereIn('status', $open))
            ->when($view === 'today', fn ($q) => $q->whereDate('pickup_at', today())->where('status', '!=', 'cancelled'))
            ->when($view === 'upcoming', fn ($q) => $q->where('pickup_at', '>=', now())->whereIn('status', $open))
            ->when($view === 'completed', fn ($q) => $q->where('status', 'completed'))
            ->when($view === 'cancelled', fn ($q) => $q->where('status', 'cancelled'))
            ->when($filters['date'] ?? null, fn ($q, $d) => $q->whereDate('pickup_at', $d))
            ->when($filters['q'] ?? null, function ($q, $t) {
                $like = '%'.addcslashes($t, '%_\\').'%';
                $digits = preg_replace('/\D/', '', $t);
                $q->where(fn ($w) => $w->where('booking_number', 'like', $like)->orWhere('customer_name', 'like', $like)
                    ->when(strlen($digits) >= 4, fn ($x) => $x->orWhere('customer_phone', 'like', '%'.$digits.'%')));
            })
            ->orderBy($view === 'completed' || $view === 'cancelled' ? 'updated_at' : 'pickup_at', $view === 'completed' || $view === 'cancelled' ? 'desc' : 'asc')
            ->paginate(25)->withQueryString();

        $counts = Booking::query()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('operator.bookings.index', [
            'bookings' => $bookings, 'filters' => $filters, 'view' => $view, 'counts' => $counts,
            'today' => Booking::query()->whereDate('pickup_at', today())->where('status', '!=', 'cancelled')->count(),
        ]);
    }

    public function create(Request $request, TenantContext $context, GoogleMapsService $maps): View
    {
        return view('operator.bookings.create', [
            'categories' => VehicleCategory::query()->availableTo($context->id())->get(),
            'types' => BookingType::options(),
            'idempotencyKey' => (string) Str::uuid(),
            'mapsEnabled' => $maps->isEnabled(),
            'prefill' => $request->only(['customer_name', 'customer_phone', 'pickup_address', 'drop_address']),
        ]);
    }

    public function store(Request $request, TenantContext $context): RedirectResponse
    {
        [$data, $stops, $fare] = $this->validated($request, $context);
        $booking = $this->service->create($context->get(), $data, $stops, $fare, $request->user(), $request->input('idempotency_key'));

        return redirect()->route('operator.bookings.show', $booking)->with('success', $booking->status === BookingStatus::Quoted
            ? __('Booking :no created with a quote of :amt.', ['no' => $booking->booking_number, 'amt' => Money::format($booking->quoted_total)])
            : __('Enquiry :no saved. Add the distance to create a quote.', ['no' => $booking->booking_number]));
    }

    /** Live fare preview for the create form (JSON). */
    public function preview(Request $request, TenantContext $context): JsonResponse
    {
        [$data, , $fare] = $this->validated($request, $context, preview: true);
        $snapshot = $this->service->preview((int) $data['vehicle_category_id'], BookingType::from($data['type']), [
            'distance_km' => $data['distance_km'] ?? 0, 'pickup_at' => Carbon::parse($data['pickup_at']),
            'return_at' => isset($data['return_at']) ? Carbon::parse($data['return_at']) : null, 'rental_hours' => $data['rental_hours'] ?? null,
        ] + $fare);

        return response()->json($snapshot);
    }

    /** Road distance via Google Maps (if configured). */
    public function distance(Request $request, GoogleMapsService $maps): JsonResponse
    {
        $data = $request->validate(['from' => ['required', 'string', 'max:255'], 'to' => ['required', 'string', 'max:255'], 'round' => ['nullable', 'boolean']]);
        $d = $maps->distance($data['from'], $data['to']);
        if (! $d) {
            return response()->json(['message' => $maps->isEnabled() ? __('Could not find a route. Enter km manually.') : __('Maps are turned off. Enter km manually.')], 422);
        }
        $factor = ($data['round'] ?? false) ? 2 : 1;

        return response()->json(['km' => round($d['km'] * $factor, 1), 'minutes' => $d['minutes'] * $factor, 'from' => $d['origin'], 'to' => $d['destination']]);
    }

    public function show(Booking $booking): View
    {
        $booking->load(['category', 'stops', 'quotations', 'statusLogs.user:id,name', 'trip.vehicle', 'trip.driver', 'payments.recorder:id,name', 'creator:id,name']);
        $canAssign = request()->user()->hasPermission('bookings.assign') && $booking->status->canMoveTo(BookingStatus::Assigned);

        return view('operator.bookings.show', [
            'booking' => $booking,
            'availability' => $canAssign ? $this->service->availability($booking) : null,
            'window' => $this->service->window($booking),
        ]);
    }

    public function requote(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'rental_hours' => ['nullable', 'integer', 'min:1', 'max:24'],
            'tolls' => ['nullable', 'numeric', 'min:0', 'max:100000'], 'parking' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'state_tax' => ['nullable', 'numeric', 'min:0', 'max:100000'], 'discount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ]);
        $booking->forceFill(array_filter(['distance_km' => $data['distance_km'] ?? null, 'rental_hours' => $data['rental_hours'] ?? null], fn ($v) => $v !== null))->save();
        if (! $this->service->canPrice($booking)) {
            throw ValidationException::withMessages(['distance_km' => __('Enter the trip distance to create a quote.')]);
        }
        $this->service->quote($booking, $data, $request->user());

        return back()->with('success', __('Quotation updated.'));
    }

    public function confirm(Request $request, Booking $booking): RedirectResponse
    {
        $this->service->confirm($booking, $request->user());

        return back()->with('success', __('Booking confirmed.'));
    }

    public function payment(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:advance,balance,refund'],
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'method' => ['required', 'in:cash,upi,card,bank_transfer,other'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $this->service->recordPayment($booking, $data['kind'], $data['amount'], $data['method'], $data['reference'] ?? null, $request->user(), $data['notes'] ?? null);

        return back()->with('success', __('Payment recorded.'));
    }

    public function assign(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['vehicle_id' => ['required', 'integer'], 'driver_id' => ['required', 'integer']]);
        $result = $this->service->assign($booking, (int) $data['vehicle_id'], (int) $data['driver_id'], $request->user());

        return back()->with('success', __('Assigned. Customer trip OTP: :otp (also sent by SMS when configured).', ['otp' => $result['otp']]));
    }

    public function start(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['otp' => ['required', 'digits:4'], 'start_km' => ['nullable', 'integer', 'min:0', 'max:5000000']]);
        $this->service->start($booking, $data['otp'], $data['start_km'] ?? null, $request->user());

        return back()->with('success', __('Trip started.'));
    }

    public function complete(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'end_km' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'waiting_min' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'tolls' => ['nullable', 'numeric', 'min:0', 'max:100000'], 'parking' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'state_tax' => ['nullable', 'numeric', 'min:0', 'max:100000'], 'rental_hours' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);
        $this->service->complete($booking, isset($data['end_km']) ? (int) $data['end_km'] : null, $data, $request->user());

        return back()->with('success', __('Trip completed. Final fare: :amt', ['amt' => Money::format($booking->fresh()->final_total)]));
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->service->cancel($booking, $data['reason'], $request->user());

        return back()->with('success', __('Booking cancelled.'));
    }

    /** @return array{0: array, 1: list<string>, 2: array} */
    private function validated(Request $request, TenantContext $context, bool $preview = false): array
    {
        $request->merge(['customer_phone' => Phone::normalize($request->input('customer_phone'))]);
        $tenantId = $context->id();
        $type = BookingType::tryFrom((string) $request->input('type'));

        $data = $request->validate([
            'type' => ['required', Rule::enum(BookingType::class)],
            'vehicle_category_id' => ['required', 'integer', Rule::exists('vehicle_categories', 'id')->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenantId))],
            'customer_name' => [$preview ? 'nullable' : 'required', 'string', 'max:150'],
            'customer_phone' => [$preview ? 'nullable' : 'required', 'regex:/^[6-9]\d{9}$/'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'pickup_address' => [$preview ? 'nullable' : 'required', 'string', 'max:255'],
            'drop_address' => [$preview || ($type?->isRental()) ? 'nullable' : 'required', 'string', 'max:255'],
            'pickup_at' => array_values(array_filter(['required', 'date', $preview ? null : 'after:-1 hour'])),
            'return_at' => [$type === BookingType::OutstationRound ? 'required' : 'nullable', 'date', 'after:pickup_at'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:60'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'duration_min' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'rental_hours' => [$type?->isRental() ? 'required' : 'nullable', 'integer', 'min:1', 'max:24'],
            'source' => ['nullable', 'in:manual,phone,whatsapp,website,walk_in'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'stops' => ['nullable', 'array', 'max:8'],
            'stops.*' => ['nullable', 'string', 'max:255'],
            'tolls' => ['nullable', 'numeric', 'min:0', 'max:100000'], 'parking' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'state_tax' => ['nullable', 'numeric', 'min:0', 'max:100000'], 'discount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'idempotency_key' => [$preview ? 'nullable' : 'required', 'uuid'],
        ], [
            'customer_phone.regex' => __('Enter a valid 10-digit Indian mobile number.'),
            'pickup_at.after' => __('Pickup time cannot be in the past.'),
            'return_at.after' => __('Return must be after pickup.'),
        ]);

        $fare = array_intersect_key($data, array_flip(['tolls', 'parking', 'state_tax', 'discount']));
        $stops = $data['stops'] ?? [];
        $booking = array_intersect_key($data, array_flip(['type', 'vehicle_category_id', 'customer_name', 'customer_phone', 'customer_email', 'pickup_address', 'drop_address', 'pickup_at', 'return_at', 'passengers', 'distance_km', 'duration_min', 'rental_hours', 'source', 'notes']));
        $booking['passengers'] = $booking['passengers'] ?? 1;
        $booking['source'] = $booking['source'] ?? 'manual';

        return [$booking, $stops, $fare];
    }
}
