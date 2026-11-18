<?php

namespace App\Services\Booking;

use App\Enums\BookingType;
use App\Models\FareRule;
use App\Models\Tenant;
use App\Models\VehicleCategory;
use App\Support\PlatformSettings;
use App\Support\Tenancy\TenantContext;

class FareRuleService
{
    /** Suggested per-km rates (₹) by platform category slug — operators edit these. */
    private const PER_KM = ['hatchback' => 11, 'sedan' => 12, 'suv' => 16, 'muv' => 15, 'premium' => 22, 'tempo-traveller' => 24, 'bus' => 45];

    public function __construct(private TenantContext $context) {}

    public function find(int $categoryId, BookingType $type): ?FareRule
    {
        return FareRule::query()->where('vehicle_category_id', $categoryId)->where('booking_type', $type->value)->where('is_active', true)->first();
    }

    /** Creates any missing rules for every available category × booking type. Never overwrites. @return int created */
    public function seedDefaults(Tenant $tenant): int
    {
        return $this->context->runAs($tenant, function () use ($tenant) {
            $gst = PlatformSettings::decimal('default_gst_percent', '5');
            $existing = FareRule::query()->get(['vehicle_category_id', 'booking_type'])
                ->map(fn ($r) => $r->vehicle_category_id.':'.$r->booking_type->value)->flip();
            $created = 0;

            foreach (VehicleCategory::query()->availableTo($tenant->id)->get() as $cat) {
                $rate = self::PER_KM[$cat->slug] ?? max(10, (int) round($cat->seats * 2.5));
                foreach (BookingType::cases() as $type) {
                    if ($existing->has($cat->id.':'.$type->value)) {
                        continue;
                    }
                    FareRule::create(['vehicle_category_id' => $cat->id, 'booking_type' => $type, 'gst_percent' => $gst, 'is_active' => true] + $this->defaults($type, $rate));
                    $created++;
                }
            }

            return $created;
        });
    }

    private function defaults(BookingType $type, int $rate): array
    {
        $common = ['night_charge' => 250, 'night_start' => '22:00:00', 'night_end' => '06:00:00', 'waiting_free_min' => 15, 'waiting_per_min' => 2];

        return $common + match ($type) {
            BookingType::Local, BookingType::Corporate => ['base_fare' => 99, 'per_km' => $rate + 4, 'min_km' => 5],
            BookingType::Airport, BookingType::Railway => ['base_fare' => 199, 'per_km' => $rate + 3, 'min_km' => 10],
            BookingType::OutstationOneway => ['base_fare' => 0, 'per_km' => $rate + 1, 'min_km' => 100, 'driver_allowance' => 300],
            BookingType::OutstationRound => ['base_fare' => 0, 'per_km' => $rate, 'min_km' => 250, 'driver_allowance' => 300],
            BookingType::Hourly => ['base_fare' => $rate * 40 + 200, 'included_km' => 40, 'included_hours' => 4, 'extra_km_rate' => $rate + 2, 'extra_hour_rate' => 150],
            BookingType::FullDay => ['base_fare' => $rate * 80 + 400, 'included_km' => 80, 'included_hours' => 8, 'extra_km_rate' => $rate + 2, 'extra_hour_rate' => 150, 'driver_allowance' => 300],
        };
    }
}
