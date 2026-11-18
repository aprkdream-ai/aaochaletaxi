<?php

namespace App\Http\Controllers\Public;

use App\Enums\BookingType;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\BookingReview;
use App\Models\FareRule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Notifications\OperatorBookingAlert;
use App\Services\Booking\BookingService;
use App\Services\Maps\GoogleMapsService;
use App\Services\Messaging\OtpService;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MicrositeController extends Controller
{
    public function __construct(private BookingService $bookings, private GoogleMapsService $maps, private OtpService $otp) {}

    public function show(Request $request): View
    {
        $tenant = $this->tenant($request);
        $reviews = BookingReview::query()->where('is_published', true)->latest()->limit(6)->get();

        return view('public.microsite', [
            'tenant' => $tenant,
            'categories' => $this->categories($tenant),
            'types' => collect(BookingType::cases())->reject(fn ($t) => $t === BookingType::Corporate)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all(),
            'fleet' => Vehicle::query()->where('status', 'active')->selectRaw('vehicle_category_id, count(*) as n')->groupBy('vehicle_category_id')->pluck('n', 'vehicle_category_id'),
            'rating' => ['avg' => round((float) BookingReview::query()->where('is_published', true)->avg('rating'), 1), 'count' => BookingReview::query()->where('is_published', true)->count()],
            'reviews' => $reviews,
            'mapsEnabled' => $this->maps->isEnabled(),
            'verifiedPhone' => $this->verifiedPhone($request),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    /** Server-side fare estimate (distance from Google, never from the client). */
    public function preview(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $data = $this->validateTrip($request, $tenant, preview: true);
        $km = $this->distanceFor($data);

        if ($km === null && ! BookingType::from($data['type'])->isRental()) {
            return response()->json(['message' => __('We will confirm the fare by call shortly after you book.')], 422);
        }

        return response()->json($this->bookings->preview((int) $data['vehicle_category_id'], BookingType::from($data['type']), [
            'distance_km' => $km ?? 0, 'pickup_at' => Carbon::parse($data['pickup_at']), 'return_at' => isset($data['return_at']) ? Carbon::parse($data['return_at']) : null,
            'rental_hours' => $data['rental_hours'] ?? null,
        ]) + ['estimated_km' => $km]);
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $this->tenant($request);
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);
        $data = $request->validate(['phone' => ['required', 'regex:/^[6-9]\d{9}$/'], 'email' => ['nullable', 'email', 'max:150']],
            ['phone.regex' => __('Enter a valid 10-digit Indian mobile number.')]);
        $channel = $this->otp->send($data['phone'], $data['email'] ?? null, 'booking');

        return response()->json(['message' => match ($channel) {
            'sms' => __('Code sent by SMS to :p', ['p' => $data['phone']]),
            'whatsapp' => __('Code sent on WhatsApp to :p', ['p' => $data['phone']]),
            'email' => __('Code sent to your email'),
            default => __('Code generated (test mode — see application log).'),
        }]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $this->tenant($request);
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);
        $data = $request->validate(['phone' => ['required', 'regex:/^[6-9]\d{9}$/'], 'otp' => ['required', 'digits:6'], 'name' => ['nullable', 'string', 'max:150'], 'email' => ['nullable', 'email', 'max:150']]);
        $this->otp->verify($data['phone'], $data['otp']);
        $this->markVerified($request, $data['phone'], $data['name'] ?? null, $data['email'] ?? null);

        return response()->json(['verified' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $data = $this->validateTrip($request, $tenant);

        if ($this->verifiedPhone($request) !== $data['customer_phone']) {
            throw ValidationException::withMessages(['customer_phone' => __('Please verify your mobile number with the code we send you.')]);
        }

        $km = $this->distanceFor($data);
        $booking = $this->bookings->create($tenant, [
            'source' => 'website', 'type' => $data['type'], 'vehicle_category_id' => $data['vehicle_category_id'],
            'customer_name' => $data['customer_name'], 'customer_phone' => $data['customer_phone'], 'customer_email' => $data['customer_email'] ?? null,
            'pickup_address' => $data['pickup_address'], 'drop_address' => $data['drop_address'] ?? null,
            'pickup_at' => $data['pickup_at'], 'return_at' => $data['return_at'] ?? null, 'passengers' => $data['passengers'] ?? 1,
            'distance_km' => $km, 'rental_hours' => $data['rental_hours'] ?? null, 'notes' => $data['notes'] ?? null,
        ], $data['stops'] ?? [], [], $request->user()?->type === UserType::Customer ? $request->user() : null, $data['idempotency_key'], phoneVerified: true);

        $tenant->ownerUser()?->notify(new OperatorBookingAlert('new_online', $booking));

        return redirect()->route('booking.track', $booking->public_token)->with('success', __('Booking :no received! :op will confirm shortly.', ['no' => $booking->booking_number, 'op' => $tenant->name]));
    }

    private function validateTrip(Request $request, Tenant $tenant, bool $preview = false): array
    {
        $request->merge(['customer_phone' => Phone::normalize($request->input('customer_phone'))]);
        $type = BookingType::tryFrom((string) $request->input('type'));
        $req = $preview ? 'nullable' : 'required';

        return $request->validate([
            'type' => ['required', Rule::enum(BookingType::class)->except([BookingType::Corporate])],
            'vehicle_category_id' => ['required', 'integer', Rule::in($this->categories($tenant)->pluck('id')->all())],
            'pickup_address' => ['required', 'string', 'min:3', 'max:255'],
            'drop_address' => [$type?->isRental() ? 'nullable' : 'required', 'string', 'min:3', 'max:255'],
            'pickup_at' => ['required', 'date', 'after:+1 hour', 'before:+180 days'],
            'return_at' => [$type === BookingType::OutstationRound ? 'required' : 'nullable', 'date', 'after:pickup_at'],
            'rental_hours' => [$type?->isRental() ? 'required' : 'nullable', 'integer', 'min:1', 'max:24'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:60'],
            'stops' => ['nullable', 'array', 'max:5'], 'stops.*' => ['nullable', 'string', 'max:255'],
            'customer_name' => [$req, 'string', 'max:150'],
            'customer_phone' => [$req, 'regex:/^[6-9]\d{9}$/'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => [$req, 'uuid'],
        ], [
            'pickup_at.after' => __('Please book at least 1 hour before pickup. For urgent rides call the operator.'),
            'customer_phone.regex' => __('Enter a valid 10-digit Indian mobile number.'),
            'vehicle_category_id.in' => __('This vehicle type is not offered.'),
        ]);
    }

    private function distanceFor(array $data): ?float
    {
        if (BookingType::from($data['type'])->isRental() || empty($data['drop_address'])) {
            return null;
        }
        $d = $this->maps->distance($data['pickup_address'], $data['drop_address']);

        return $d ? round($d['km'] * ($data['type'] === BookingType::OutstationRound->value ? 2 : 1), 1) : null;
    }

    /** Categories that have at least one active fare rule for this operator. */
    private function categories(Tenant $tenant)
    {
        $ids = FareRule::query()->where('is_active', true)->distinct()->pluck('vehicle_category_id');

        return VehicleCategory::query()->availableTo($tenant->id)->whereIn('id', $ids)->get();
    }

    private function verifiedPhone(Request $request): ?string
    {
        $user = $request->user();
        if ($user && $user->type === UserType::Customer && $user->phone) {
            return $user->phone;
        }
        $v = $request->session()->get('verified_phone');

        return ($v && $v['until'] > now()->timestamp) ? $v['phone'] : null;
    }

    /** Remember the verified number; create/log in a customer account for it when appropriate. */
    private function markVerified(Request $request, string $phone, ?string $name, ?string $email): void
    {
        $request->session()->put('verified_phone', ['phone' => $phone, 'until' => now()->addMinutes(30)->timestamp]);

        $user = User::query()->where('phone', $phone)->first();
        if (! $user) {
            $user = new User(['name' => $name ?: __('Customer'), 'phone' => $phone, 'email' => $email && ! User::where('email', strtolower($email))->exists() ? strtolower($email) : null, 'password' => Str::random(32)]);
            $user->type = UserType::Customer;
            $user->status = UserStatus::Active;
            $user->phone_verified_at = now();
            $user->save();
        }
        if ($user->type === UserType::Customer && $user->isActive() && ! $request->user()) {
            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('verified_phone', ['phone' => $phone, 'until' => now()->addMinutes(30)->timestamp]);
        }
    }

    private function tenant(Request $request): Tenant
    {
        return $request->attributes->get('tenant');
    }
}
