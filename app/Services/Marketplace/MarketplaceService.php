<?php

namespace App\Services\Marketplace;

use App\Enums\BookingType;
use App\Enums\TenantStatus;
use App\Models\Booking;
use App\Models\BookingReview;
use App\Models\MarketplaceLead;
use App\Models\MarketplaceLeadOffer;
use App\Models\Tenant;
use App\Models\TenantServiceCity;
use App\Models\User;
use App\Notifications\MarketplaceLeadOffered;
use App\Services\AuditLogger;
use App\Services\Booking\BookingService;
use App\Services\Booking\FareRuleService;
use App\Services\Maps\GoogleMapsService;
use App\Support\PlatformSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Public marketplace (Taxi module only): search operators serving a city with server-side fare estimates,
 * and quote requests broadcast to the top operators where the first to accept gets the booking (row lock on the lead).
 */
class MarketplaceService
{
    public function __construct(
        private TenantContext $context,
        private FareRuleService $rules,
        private BookingService $bookings,
        private GoogleMapsService $maps,
        private AuditLogger $audit,
    ) {}

    /** Operators listed in a city, ranked (verified → rating → completed trips). @return Collection<int, Tenant> */
    public function operatorsIn(string $city): Collection
    {
        $key = TenantServiceCity::key($city);
        if ($key === '') {
            return collect();
        }
        $tenants = Tenant::query()->where('status', TenantStatus::Active)->where('marketplace_enabled', true)
            ->whereIn('business_type', ['taxi', 'both'])
            ->whereIn('id', TenantServiceCity::query()->where('city_key', $key)->select('tenant_id'))
            ->get()->filter->isMarketplaceListable()->values();
        $stats = $this->stats($tenants->pluck('id')->all());

        return $tenants->each(fn (Tenant $t) => $t->setAttribute('mp_stats', $stats[$t->id] ?? ['rating' => 0.0, 'reviews' => 0, 'completed' => 0]))
            ->sortByDesc(fn (Tenant $t) => [$t->kyc_status->value === 'verified' ? 1 : 0, $t->mp_stats['rating'], $t->mp_stats['completed']])->values();
    }

    /**
     * Search results: listed operators with an active fare rule for the trip and a server-side estimate.
     *
     * @param  array{from_city:string, to_city?:?string, type:string, vehicle_category_id:int, pickup_at:mixed, return_at?:mixed, rental_hours?:?int}  $q
     * @return array{km: ?float, results: Collection<int, object>}
     */
    public function search(array $q): array
    {
        $type = BookingType::from($q['type']);
        $km = $this->distance($q['from_city'], $q['to_city'] ?? null, $type);
        $input = ['distance_km' => $km ?? 0, 'pickup_at' => Carbon::parse($q['pickup_at']), 'return_at' => isset($q['return_at']) ? Carbon::parse($q['return_at']) : null,
            'rental_hours' => $type->isRental() ? (int) ($q['rental_hours'] ?? ($type === BookingType::FullDay ? 8 : 4)) : null];
        $canPrice = $km !== null || $type->isRental();

        $results = $this->operatorsIn($q['from_city'])->map(function (Tenant $t) use ($q, $type, $input, $canPrice) {
            return $this->context->runAs($t, function () use ($t, $q, $type, $input, $canPrice) {
                $rule = $this->rules->find((int) $q['vehicle_category_id'], $type);
                if (! $rule) {
                    return null;
                }
                $estimate = null;
                if ($canPrice) {
                    try {
                        $estimate = $this->bookings->preview((int) $q['vehicle_category_id'], $type, $input)['total'] ?? null;
                    } catch (ValidationException) {
                        $estimate = null;
                    }
                }

                return (object) ['tenant' => $t, 'estimate' => $estimate, 'per_km' => (string) $rule->per_km, 'stats' => $t->mp_stats];
            });
        })->filter()->values();

        return ['km' => $km, 'results' => $results];
    }

    /** Create a quote request and offer it to the top operators. */
    public function createLead(array $data, ?User $customer, ?string $ip): MarketplaceLead
    {
        $found = $this->search($data);
        $top = $found['results']->take(max(1, PlatformSettings::int('marketplace_broadcast_count', 3)));
        if ($top->isEmpty()) {
            throw ValidationException::withMessages(['from_city' => __('No listed operator serves this trip yet. Try another vehicle type or nearby city.')]);
        }
        $type = BookingType::from($data['type']);
        $pickupAt = Carbon::parse($data['pickup_at']);
        $ttl = now()->addMinutes(PlatformSettings::int('marketplace_lead_ttl_minutes', 120));
        $expires = $ttl->min($pickupAt->copy()->subMinutes(30));

        $lead = DB::transaction(function () use ($data, $customer, $ip, $top, $found, $type, $expires) {
            $lead = new MarketplaceLead([
                'customer_name' => $data['customer_name'], 'customer_phone' => $data['customer_phone'], 'customer_email' => $data['customer_email'] ?? null,
                'type' => $type, 'vehicle_category_id' => $data['vehicle_category_id'], 'from_city' => $data['from_city'], 'to_city' => $data['to_city'] ?? null,
                'pickup_address' => ($data['pickup_address'] ?? null) ?: $data['from_city'], 'drop_address' => $type->isRental() ? null : (($data['drop_address'] ?? null) ?: ($data['to_city'] ?? null)),
                'pickup_at' => $data['pickup_at'], 'return_at' => $data['return_at'] ?? null, 'rental_hours' => $data['rental_hours'] ?? null,
                'passengers' => $data['passengers'] ?? 1, 'distance_km' => $found['km'], 'notes' => $data['notes'] ?? null,
            ]);
            $lead->forceFill(['token' => Str::random(40), 'city_key' => TenantServiceCity::key($data['from_city']), 'status' => 'open',
                'expires_at' => $expires, 'customer_user_id' => $customer?->id, 'ip' => $ip])->save();

            foreach ($top as $r) {
                $this->context->runAs($r->tenant, fn () => MarketplaceLeadOffer::create(['marketplace_lead_id' => $lead->id, 'status' => 'pending', 'estimate' => $r->estimate]));
            }

            return $lead;
        });

        foreach ($top as $r) {
            $r->tenant->ownerUser()?->notify(new MarketplaceLeadOffered($lead->from_city, $lead->to_city, $lead->pickup_at->format('d M, h:i A'), $type->label(), $lead->expires_at->format('h:i A')));
        }
        $this->audit->log('marketplace.lead_created', $lead, [], ['offers' => $top->count(), 'city' => $lead->from_city]);

        return $lead;
    }

    /** First operator to accept gets the booking; everyone else's offer becomes "lost". Runs in the operator's tenant context. */
    public function accept(MarketplaceLeadOffer $offer, User $user): Booking
    {
        $tenant = $this->context->get();

        return DB::transaction(function () use ($offer, $user, $tenant) {
            $lead = MarketplaceLead::query()->whereKey($offer->marketplace_lead_id)->lockForUpdate()->firstOrFail();
            $mine = MarketplaceLeadOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            if (! $lead->isOpen() || $mine->status !== 'pending') {
                throw ValidationException::withMessages(['lead' => $lead->status === 'accepted' ? __('Another operator accepted this request first.') : __('This request is no longer open.')]);
            }

            $booking = $this->bookings->create($tenant, [
                'source' => 'marketplace', 'type' => $lead->type->value, 'vehicle_category_id' => $lead->vehicle_category_id,
                'customer_name' => $lead->customer_name, 'customer_phone' => $lead->customer_phone, 'customer_email' => $lead->customer_email,
                'pickup_address' => $lead->pickup_address, 'drop_address' => $lead->drop_address, 'pickup_at' => $lead->pickup_at, 'return_at' => $lead->return_at,
                'passengers' => $lead->passengers, 'distance_km' => $lead->distance_km, 'rental_hours' => $lead->rental_hours, 'notes' => $lead->notes,
            ], [], [], $user, 'lead-'.$lead->id, phoneVerified: true);
            $booking->forceFill(['marketplace_lead_id' => $lead->id, 'commission_percent' => self::commissionPercent()])->save();

            $lead->forceFill(['status' => 'accepted', 'accepted_tenant_id' => $tenant->id, 'booking_id' => $booking->id])->save();
            $mine->forceFill(['status' => 'accepted', 'responded_at' => now()])->save();
            MarketplaceLeadOffer::withoutGlobalScopes()->where('marketplace_lead_id', $lead->id)->whereKeyNot($mine->id)->where('status', 'pending')
                ->update(['status' => 'lost', 'updated_at' => now()]);
            $this->audit->log('marketplace.lead_accepted', $lead, [], ['booking' => $booking->booking_number]);

            return $booking;
        });
    }

    public function decline(MarketplaceLeadOffer $offer): void
    {
        DB::transaction(function () use ($offer) {
            $mine = MarketplaceLeadOffer::query()->whereKey($offer->id)->lockForUpdate()->firstOrFail();
            if ($mine->status !== 'pending') {
                throw ValidationException::withMessages(['lead' => __('This request is no longer open.')]);
            }
            $mine->forceFill(['status' => 'declined', 'responded_at' => now()])->save();
            // Everyone declined → close the request early.
            if (! MarketplaceLeadOffer::withoutGlobalScopes()->where('marketplace_lead_id', $mine->marketplace_lead_id)->where('status', 'pending')->exists()) {
                MarketplaceLead::query()->whereKey($mine->marketplace_lead_id)->where('status', 'open')->update(['status' => 'expired', 'updated_at' => now()]);
            }
        });
    }

    /** @return int leads expired */
    public function expireLeads(): int
    {
        $ids = MarketplaceLead::query()->where('status', 'open')->where('expires_at', '<=', now())->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }
        MarketplaceLead::query()->whereIn('id', $ids)->where('status', 'open')->update(['status' => 'expired', 'updated_at' => now()]);
        MarketplaceLeadOffer::withoutGlobalScopes()->whereIn('marketplace_lead_id', $ids)->where('status', 'pending')->update(['status' => 'expired', 'updated_at' => now()]);

        return $ids->count();
    }

    public static function commissionPercent(): string
    {
        return PlatformSettings::decimal('marketplace_commission_percent', '10');
    }

    /** Distinct cities with at least one listed operator (for SEO pages / sitemap). @return list<string> */
    public function listedCities(): array
    {
        $tenantIds = Tenant::query()->where('status', TenantStatus::Active)->where('marketplace_enabled', true)->whereIn('business_type', ['taxi', 'both'])->pluck('id');

        return TenantServiceCity::query()->whereIn('tenant_id', $tenantIds)->orderBy('city')->get(['city', 'city_key'])
            ->unique('city_key')->pluck('city')->values()->all();
    }

    private function distance(string $from, ?string $to, BookingType $type): ?float
    {
        if ($type->isRental() || blank($to) || ! $this->maps->isEnabled()) {
            return null;
        }
        $d = $this->maps->distance($from, (string) $to);

        return $d ? round($d['km'] * ($type === BookingType::OutstationRound ? 2 : 1), 1) : null;
    }

    /** @param list<int> $tenantIds @return array<int, array{rating: float, reviews: int, completed: int}> */
    private function stats(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }
        $reviews = BookingReview::withoutGlobalScopes()->whereIn('tenant_id', $tenantIds)->where('is_published', true)
            ->selectRaw('tenant_id, avg(rating) as r, count(*) as n')->groupBy('tenant_id')->get()->keyBy('tenant_id');
        $completed = Booking::withoutGlobalScopes()->whereIn('tenant_id', $tenantIds)->where('status', 'completed')
            ->selectRaw('tenant_id, count(*) as n')->groupBy('tenant_id')->pluck('n', 'tenant_id');

        return collect($tenantIds)->mapWithKeys(fn ($id) => [$id => [
            'rating' => round((float) ($reviews[$id]->r ?? 0), 1), 'reviews' => (int) ($reviews[$id]->n ?? 0), 'completed' => (int) ($completed[$id] ?? 0),
        ]])->all();
    }
}
