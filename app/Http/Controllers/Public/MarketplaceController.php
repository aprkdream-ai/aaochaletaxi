<?php

namespace App\Http\Controllers\Public;

use App\Enums\BookingType;
use App\Http\Controllers\Controller;
use App\Models\MarketplaceLead;
use App\Models\Tenant;
use App\Models\TenantServiceCity;
use App\Models\VehicleCategory;
use App\Services\Marketplace\MarketplaceService;
use App\Services\Messaging\OtpService;
use App\Support\MarketplaceReferral;
use App\Support\Phone;
use App\Support\VerifiedPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Public marketplace: compare operators by city with server-side fares, book directly or request quotes. */
class MarketplaceController extends Controller
{
    public const TYPES = [BookingType::OutstationOneway, BookingType::OutstationRound, BookingType::Local, BookingType::Airport, BookingType::Hourly, BookingType::FullDay];

    public function __construct(private MarketplaceService $marketplace, private OtpService $otp) {}

    public function search(Request $request): View
    {
        $q = $request->filled('from_city') ? $this->validateSearch($request) : null;

        return view('public.marketplace.search', $this->common($request) + [
            'q' => $q, 'found' => $q ? $this->marketplace->search($q) : null,
        ]);
    }

    public function city(Request $request, string $city): View
    {
        $name = TenantServiceCity::query()->where('city_key', TenantServiceCity::key($city))->value('city');
        abort_unless($name, 404);
        $operators = $this->marketplace->operatorsIn($name);
        abort_if($operators->isEmpty(), 404);

        return view('public.marketplace.city', $this->common($request) + ['city' => $name, 'operators' => $operators]);
    }

    /** Book directly with one operator: remember the referral (commission) and open their booking page prefilled. */
    public function go(Request $request, string $slug): RedirectResponse
    {
        $tenant = Tenant::query()->where('slug', $slug)->first();
        abort_unless($tenant && $tenant->isMarketplaceListable(), 404);
        MarketplaceReferral::remember($request, $tenant);
        $q = $request->only(['type', 'vehicle_category_id', 'pickup_at', 'return_at', 'rental_hours', 'passengers', 'from_city', 'to_city']);

        return redirect()->route('microsite.show', $tenant->slug)->withInput(array_filter([
            'type' => in_array($q['type'] ?? null, array_map(fn ($t) => $t->value, self::TYPES), true) ? $q['type'] : null,
            'vehicle_category_id' => isset($q['vehicle_category_id']) ? (int) $q['vehicle_category_id'] : null,
            'pickup_address' => isset($q['from_city']) ? mb_substr((string) $q['from_city'], 0, 100) : null,
            'drop_address' => isset($q['to_city']) ? mb_substr((string) $q['to_city'], 0, 100) : null,
            'pickup_at' => $this->dt($q['pickup_at'] ?? null), 'return_at' => $this->dt($q['return_at'] ?? null),
            'rental_hours' => isset($q['rental_hours']) ? (int) $q['rental_hours'] : null, 'passengers' => isset($q['passengers']) ? (int) $q['passengers'] : null,
        ]));
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);
        $data = $request->validate(['phone' => ['required', 'regex:/^[6-9]\d{9}$/']], ['phone.regex' => __('Enter a valid 10-digit Indian mobile number.')]);
        $channel = $this->otp->send($data['phone'], null, 'booking');

        return response()->json(['message' => match ($channel) {
            'sms' => __('Code sent by SMS to :p', ['p' => $data['phone']]),
            'whatsapp' => __('Code sent on WhatsApp to :p', ['p' => $data['phone']]),
            default => __('Code generated (test mode — see application log).'),
        }]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);
        $data = $request->validate(['phone' => ['required', 'regex:/^[6-9]\d{9}$/'], 'otp' => ['required', 'digits:6'], 'name' => ['nullable', 'string', 'max:150']]);
        $this->otp->verify($data['phone'], $data['otp']);
        VerifiedPhone::mark($request, $data['phone'], $data['name'] ?? null, null);

        return response()->json(['verified' => true]);
    }

    /** Request quotes: broadcast to the top operators; first to accept gets the booking. */
    public function quote(Request $request): RedirectResponse
    {
        $request->merge(['customer_phone' => Phone::normalize($request->input('customer_phone'))]);
        $data = $this->validateSearch($request, [
            'pickup_address' => ['nullable', 'string', 'max:255'], 'drop_address' => ['nullable', 'string', 'max:255'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:60'], 'notes' => ['nullable', 'string', 'max:500'],
            'customer_name' => ['required', 'string', 'max:150'], 'customer_phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'customer_email' => ['nullable', 'email', 'max:150'],
        ]);
        if (VerifiedPhone::get($request) !== $data['customer_phone']) {
            throw ValidationException::withMessages(['customer_phone' => __('Please verify your mobile number with the code we send you.')]);
        }
        $customer = $request->user()?->type?->value === 'customer' ? $request->user() : null;
        $lead = $this->marketplace->createLead($data, $customer, $request->ip());

        return redirect()->route('marketplace.lead', $lead)->with('success', __('Request sent to :n operators. You will get a call and SMS as soon as one accepts.', ['n' => $lead->offers()->count()]));
    }

    public function lead(MarketplaceLead $lead): View
    {
        $lead->load(['category:id,name', 'acceptedTenant:id,name,slug,phone', 'booking:id,public_token,booking_number']);

        return view('public.marketplace.lead', ['lead' => $lead, 'offered' => $lead->offers()->count(),
            'declined' => $lead->offers()->whereIn('status', ['declined'])->count()]);
    }

    private function validateSearch(Request $request, array $extra = []): array
    {
        $type = BookingType::tryFrom((string) $request->input('type'));

        return $request->validate([
            'from_city' => ['required', 'string', 'min:2', 'max:100'],
            'to_city' => [$type && ! $type->isRental() && $type !== BookingType::Local && $type !== BookingType::Airport ? 'required' : 'nullable', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_map(fn ($t) => $t->value, self::TYPES))],
            'vehicle_category_id' => ['required', 'integer', Rule::exists('vehicle_categories', 'id')->whereNull('tenant_id')->where('is_active', true)],
            'pickup_at' => ['required', 'date', 'after:+1 hour', 'before:+180 days'],
            'return_at' => [$type === BookingType::OutstationRound ? 'required' : 'nullable', 'date', 'after:pickup_at'],
            'rental_hours' => ['nullable', 'integer', 'min:1', 'max:24'],
        ] + $extra, [
            'pickup_at.after' => __('Please choose a pickup at least 1 hour from now.'),
            'customer_phone.regex' => __('Enter a valid 10-digit Indian mobile number.'),
        ]);
    }

    private function common(Request $request): array
    {
        return [
            'types' => collect(self::TYPES)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all(),
            'categories' => VehicleCategory::query()->availableTo(null)->get(),
            'cities' => $this->marketplace->listedCities(),
            'verifiedPhone' => VerifiedPhone::get($request),
        ];
    }

    private function dt(?string $v): ?string
    {
        try {
            return $v ? Carbon::parse($v)->format('Y-m-d\TH:i') : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
