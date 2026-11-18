<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\Booking\FareRuleService;
use App\Services\DriverService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\TestCase;

class DriverAppTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    private int $sedan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->owner = $this->owner($this->tenant);
        app(FareRuleService::class)->seedDefaults($this->tenant);
        $this->sedan = VehicleCategory::where('slug', 'sedan')->value('id');
    }

    /** @return array{0: Vehicle, 1: Driver, 2: User} */
    private function fleet(string $reg = 'GJ01AB1234', string $phone = '9811122233'): array
    {
        return app(TenantContext::class)->runAs($this->tenant, function () use ($reg, $phone) {
            $v = Vehicle::create(['registration_number' => $reg, 'vehicle_category_id' => $this->sedan, 'make' => 'Maruti', 'model' => 'Dzire', 'fuel_type' => 'cng', 'seats' => 4, 'ownership' => 'owned', 'status' => 'active', 'is_available' => true, 'insurance_expiry' => now()->addYear()]);
            $d = Driver::create(['name' => 'Suresh Kumar', 'phone' => $phone, 'licence_number' => 'GJ0120190012'.substr($phone, -3), 'licence_expiry' => now()->addYears(2), 'pay_type' => 'per_trip', 'pay_amount' => 500, 'status' => 'active', 'is_available' => true]);
            $u = app(DriverService::class)->enableAppAccess($d, 'drive1234');

            return [$v, $d->fresh(), $u];
        });
    }

    /** Creates a confirmed booking and assigns it; returns [trip, otp]. */
    private function assigned(Vehicle $v, Driver $d, ?string $pickup = null): array
    {
        $this->actingAs($this->owner)->post(route('operator.bookings.store'), [
            'type' => 'outstation_oneway', 'vehicle_category_id' => $this->sedan, 'customer_name' => 'Priya Shah', 'customer_phone' => '9825012345',
            'pickup_address' => 'Ahmedabad', 'drop_address' => 'Udaipur', 'pickup_at' => $pickup ?? now()->addHour()->format('Y-m-d H:i'),
            'distance_km' => 260, 'passengers' => 3, 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $b = Booking::withoutGlobalScopes()->latest('id')->first();
        $this->actingAs($this->owner)->post(route('operator.bookings.confirm', $b));
        $this->actingAs($this->owner)->post(route('operator.bookings.assign', $b), ['vehicle_id' => $v->id, 'driver_id' => $d->id])->assertSessionHas('success');
        preg_match('/OTP: (\d{4})/', session('success'), $m);

        return [Trip::withoutGlobalScopes()->where('booking_id', $b->id)->where('status', 'assigned')->sole(), $m[1]];
    }

    public function test_assignment_notifies_driver_and_poll_reports_it(): void
    {
        [$v, $d, $u] = $this->fleet();
        [$trip] = $this->assigned($v, $d);

        $this->assertSame(1, $u->unreadNotifications()->count());
        $this->assertSame('trip_assigned', $u->unreadNotifications()->first()->data['kind']);

        $this->actingAs($u)->getJson(route('driver.poll'))->assertOk()->assertJson(['new' => 1, 'on_duty' => true, 'active_trip' => null]);
        $this->actingAs($u)->get(route('driver.home'))->assertOk()->assertSee($trip->booking()->withoutGlobalScopes()->value('booking_number'))->assertSee('Ahmedabad');
        // Opening the app marks assignment alerts as seen.
        $this->actingAs($u)->getJson(route('driver.poll'))->assertJson(['new' => 0]);
        $this->assertNotNull($d->fresh()->app_seen_at);
    }

    public function test_driver_starts_with_otp_and_completes_trip(): void
    {
        [$v, $d, $u] = $this->fleet();
        [$trip, $otp] = $this->assigned($v, $d);
        $wrong = $otp === '0000' ? '1111' : '0000';

        $this->actingAs($u)->get(route('driver.trips.show', $trip))->assertOk()->assertSee('Priya Shah')->assertSee('google.com/maps/dir', false)->assertSee('wa.me/919825012345', false);
        $this->actingAs($u)->post(route('driver.trips.start', $trip), ['otp' => $wrong, 'start_km' => 10000])->assertSessionHasErrors('otp');
        $this->actingAs($u)->post(route('driver.trips.start', $trip), ['otp' => $otp, 'start_km' => 10000])->assertRedirect(route('driver.trips.show', $trip));
        $this->assertSame('started', $trip->fresh()->status);

        $this->actingAs($u)->post(route('driver.availability'))->assertSessionHas('error');
        $this->assertTrue($d->fresh()->is_available);

        $this->actingAs($u)->postJson(route('driver.trips.location', $trip), ['lat' => 23.0225, 'lng' => 72.5714])->assertOk();
        $this->assertEqualsWithDelta(23.0225, $trip->fresh()->last_lat, 0.0001);
        $this->actingAs($u)->postJson(route('driver.trips.location', $trip), ['lat' => 51.5, 'lng' => -0.12])->assertUnprocessable();
        $this->actingAs($this->owner)->get(route('operator.bookings.show', $trip->booking()->withoutGlobalScopes()->first()))->assertSee('Last location');

        $this->actingAs($u)->post(route('driver.trips.complete', $trip), ['end_km' => 10270, 'tolls' => 250])->assertSessionHas('success');
        $t = $trip->fresh();
        $this->assertSame('completed', $t->status);
        $this->assertSame('completed', $t->booking()->withoutGlobalScopes()->first()->status->value);
        $this->actingAs($u)->postJson(route('driver.trips.location', $trip), ['lat' => 23, 'lng' => 72.5])->assertStatus(409);

        $this->actingAs($u)->get(route('driver.history'))->assertOk()->assertSee('Ahmedabad');
        $this->actingAs($u)->get(route('driver.earnings'))->assertOk()->assertSee('₹500');
        $this->actingAs($u)->get(route('driver.profile'))->assertOk()->assertSee('GJ0120190012233');
    }

    public function test_trip_cannot_start_too_early(): void
    {
        [$v, $d, $u] = $this->fleet();
        [$trip, $otp] = $this->assigned($v, $d, now()->addDays(2)->format('Y-m-d H:i'));
        $this->actingAs($u)->post(route('driver.trips.start', $trip), ['otp' => $otp, 'start_km' => 100])->assertSessionHasErrors('otp');
        $this->assertSame('assigned', $trip->fresh()->status);
        $this->actingAs($u)->get(route('driver.home'))->assertOk()->assertSee('Upcoming');
    }

    public function test_driver_cannot_see_or_act_on_other_drivers_trips(): void
    {
        [$v, $d] = $this->fleet();
        [, $d2, $u2] = $this->fleet('GJ01AB9999', '9811100000');
        [$trip, $otp] = $this->assigned($v, $d);

        $this->actingAs($u2)->get(route('driver.trips.show', $trip))->assertNotFound();
        $this->actingAs($u2)->post(route('driver.trips.start', $trip), ['otp' => $otp, 'start_km' => 1])->assertNotFound();
        $this->actingAs($u2)->postJson(route('driver.trips.location', $trip), ['lat' => 23, 'lng' => 72.5])->assertNotFound();
        $this->assertSame('assigned', $trip->fresh()->status);

        // Other operator's driver can't even resolve the trip (tenant scope).
        $other = Tenant::factory()->create();
        $ou = app(TenantContext::class)->runAs($other, fn () => app(DriverService::class)->enableAppAccess(
            Driver::create(['name' => 'X', 'phone' => '9822200000', 'licence_number' => 'MH1220190012345', 'licence_expiry' => now()->addYear(), 'pay_type' => 'monthly', 'status' => 'active', 'is_available' => true]), 'pass12345'));
        $this->actingAs($ou)->get(route('driver.trips.show', $trip))->assertNotFound();

        // Staff/customers can't use driver endpoints.
        $this->actingAs($this->owner)->getJson(route('driver.poll'))->assertForbidden();
    }

    public function test_availability_toggle_and_push_token(): void
    {
        [, $d, $u] = $this->fleet();
        $this->actingAs($u)->post(route('driver.availability'))->assertSessionHas('success');
        $this->assertFalse($d->fresh()->is_available);
        $this->actingAs($u)->postJson(route('driver.push-token'), ['token' => str_repeat('a', 150)])->assertOk();
        $this->assertSame(str_repeat('a', 150), $d->fresh()->fcm_token);
        $this->assertArrayNotHasKey('fcm_token', $d->fresh()->toArray());
    }

    public function test_pwa_manifest_service_worker_and_offline_page(): void
    {
        $this->get(route('driver.manifest'))->assertOk()->assertHeader('Content-Type', 'application/manifest+json')->assertJsonPath('scope', '/driver/');
        $sw = $this->get(route('driver.sw'))->assertOk()->assertHeader('Service-Worker-Allowed', '/driver/');
        $this->assertStringStartsWith('application/javascript', $sw->headers->get('Content-Type'));
        $this->assertStringContainsString("addEventListener('push'", $sw->getContent());
        $this->get(route('driver.offline'))->assertOk()->assertSee('You are offline');
        [, , $u] = $this->fleet();
        $this->actingAs($u)->get(route('driver.home'))->assertSee(route('driver.manifest'), false);
    }
}
