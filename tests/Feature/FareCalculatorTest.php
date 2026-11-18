<?php

namespace Tests\Feature;

use App\Enums\BookingType;
use App\Models\FareRule;
use App\Services\Booking\FareCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FareCalculatorTest extends TestCase
{
    protected bool $seed = false;

    private function rule(BookingType $type, array $o = []): FareRule
    {
        $r = new FareRule(array_merge([
            'vehicle_category_id' => 1, 'booking_type' => $type, 'base_fare' => 0, 'per_km' => 12, 'min_km' => 0, 'included_km' => 0, 'included_hours' => 0,
            'extra_km_rate' => 0, 'extra_hour_rate' => 0, 'driver_allowance' => 0, 'night_charge' => 0, 'night_start' => '22:00:00', 'night_end' => '06:00:00',
            'waiting_free_min' => 15, 'waiting_per_min' => 0, 'gst_percent' => 5, 'is_active' => true,
        ], $o));
        $r->id = 1;

        return $r;
    }

    private function calc(FareRule $rule, array $in): array
    {
        return (new FareCalculator)->calculate($rule, $in + ['pickup_at' => Carbon::parse('2026-12-01 09:00')]);
    }

    public function test_one_way_applies_minimum_km_allowance_and_gst(): void
    {
        $f = $this->calc($this->rule(BookingType::OutstationOneway, ['min_km' => 100, 'driver_allowance' => 300]), ['distance_km' => 80]);
        $this->assertSame(100.0, $f['billable_km']);
        $this->assertSame('1500.00', $f['fare']);           // 100×12 + 300
        $this->assertSame('75.00', $f['gst']);              // 5%
        $this->assertSame('1575.00', $f['total']);
    }

    public function test_round_trip_minimum_per_day_and_days(): void
    {
        $f = $this->calc($this->rule(BookingType::OutstationRound, ['per_km' => 11, 'min_km' => 250, 'driver_allowance' => 300]), [
            'distance_km' => 400, 'return_at' => Carbon::parse('2026-12-03 20:00'),
        ]);
        $this->assertSame(3, $f['days']);
        $this->assertSame(750.0, $f['billable_km']);       // 250 × 3 days > 400
        $this->assertSame('9150.00', $f['fare']);           // 750×11 + 3×300
        $this->assertSame('9608.00', $f['total']);          // 9150 + 457.50 GST → rounded

        $this->expectException(ValidationException::class);
        $this->calc($this->rule(BookingType::OutstationRound), ['distance_km' => 10]);
    }

    public function test_rental_package_extras_and_passthrough_not_taxed(): void
    {
        $f = $this->calc($this->rule(BookingType::Hourly, ['base_fare' => 1200, 'included_km' => 40, 'included_hours' => 4, 'extra_km_rate' => 14, 'extra_hour_rate' => 150]), [
            'distance_km' => 55, 'rental_hours' => 6, 'tolls' => 120, 'parking' => 50,
        ]);
        $this->assertSame('1710.00', $f['fare']);           // 1200 + 15×14 + 2×150
        $this->assertSame('85.50', $f['gst']);
        $this->assertSame('170.00', $f['passthrough_total']);
        $this->assertSame('1966.00', $f['total']);          // 1795.50 + 170 = 1965.50 → 1966
        $this->assertSame('0.50', $f['round_off']);
    }

    public function test_night_waiting_and_discount(): void
    {
        $rule = $this->rule(BookingType::Local, ['base_fare' => 100, 'min_km' => 5, 'night_charge' => 250, 'waiting_per_min' => 2]);
        $f = (new FareCalculator)->calculate($rule, ['pickup_at' => Carbon::parse('2026-12-01 23:30'), 'distance_km' => 3, 'waiting_min' => 25, 'discount' => 50]);
        $codes = array_column($f['lines'], 'code');
        $this->assertContains('night', $codes);
        $this->assertSame('430.00', $f['fare']);            // 100 + 5×12 + 250 + 10×2 (25−15 free)
        $this->assertSame('380.00', $f['taxable']);         // − 50 discount
        $this->assertSame('399.00', $f['total']);           // + 19.00 GST

        $day = (new FareCalculator)->calculate($rule, ['pickup_at' => Carbon::parse('2026-12-01 12:00'), 'distance_km' => 3]);
        $this->assertNotContains('night', array_column($day['lines'], 'code'));

        $this->expectException(ValidationException::class);
        (new FareCalculator)->calculate($rule, ['pickup_at' => now(), 'distance_km' => 3, 'discount' => 99999]);
    }
}
