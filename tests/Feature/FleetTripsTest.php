<?php

namespace Tests\Feature;

use App\Enums\BusinessType;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\Booking\FareRuleService;
use App\Services\DriverService;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlatformSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class FleetTripsTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->businessType(BusinessType::Both)->create();
        $this->owner = $this->owner($this->tenant);
    }

    private function ctx(\Closure $fn, ?Tenant $t = null)
    {
        return app(TenantContext::class)->runAs($t ?? $this->tenant, $fn);
    }

    private function vehicle(string $reg = 'GJ01AB1234', ?Tenant $t = null): Vehicle
    {
        return $this->ctx(fn () => Vehicle::create([
            'registration_number' => $reg, 'vehicle_category_id' => VehicleCategory::where('slug', 'sedan')->value('id'), 'make' => 'Maruti', 'model' => 'Dzire',
            'fuel_type' => 'diesel', 'seats' => 4, 'ownership' => 'owned', 'status' => 'active', 'is_available' => true, 'odometer_km' => 20000,
            'insurance_expiry' => now()->addYear(), 'permit_expiry' => now()->addYear(), 'fitness_expiry' => now()->addYear(), 'puc_expiry' => now()->addYear(),
        ]), $t);
    }

    /** @return array{0: Driver, 1: User} */
    private function driver(string $phone = '9811122233', ?Tenant $t = null): array
    {
        return $this->ctx(function () use ($phone) {
            $d = Driver::create(['name' => 'Driver '.substr($phone, -3), 'phone' => $phone, 'licence_number' => 'GJ0120190'.substr($phone, -5), 'licence_expiry' => now()->addYears(2),
                'pay_type' => 'monthly', 'pay_amount' => 15000, 'status' => 'active', 'is_available' => true]);

            return [$d->fresh(), app(DriverService::class)->enableAppAccess($d, 'drive1234')];
        }, $t);
    }

    private function department(string $name = 'Sales', ?Tenant $t = null): Department
    {
        return $this->ctx(fn () => Department::create(['name' => $name, 'code' => 'CC-'.strtoupper(substr($name, 0, 3))]), $t);
    }

    private function payload(Vehicle $v, Driver $d, array $o = []): array
    {
        return $o + [
            'vehicle_id' => $v->id, 'driver_id' => $d->id, 'start_at' => now()->addHours(2)->format('Y-m-d H:i'), 'end_at' => now()->addHours(6)->format('Y-m-d H:i'),
            'purpose' => 'Client visit', 'pickup_point' => 'Head office', 'drop_point' => 'GIDC Sanand', 'requested_by' => 'Mehul', 'requested_phone' => '98250 12345', 'passengers' => 2,
        ];
    }

    private function createTrip(Vehicle $v, Driver $d, array $o = []): Trip
    {
        $this->actingAs($this->owner)->post(route('operator.fleet-trips.store'), $this->payload($v, $d, $o))->assertSessionHasNoErrors()->assertRedirect();

        return Trip::withoutGlobalScopes()->whereNull('booking_id')->latest('id')->first();
    }

    // ---------- Module gating & permissions ----------

    public function test_fleet_trips_are_fleet_module_only(): void
    {
        $taxiOwner = $this->owner(Tenant::factory()->businessType(BusinessType::Taxi)->create());
        $this->actingAs($taxiOwner)->get(route('operator.fleet-trips.index'))->assertForbidden();
        $this->actingAs($taxiOwner)->get(route('operator.departments.index'))->assertForbidden();
        $this->actingAs($taxiOwner)->get(route('operator.dashboard'))->assertOk()->assertDontSee('Fleet trips today');

        $fleetOwner = $this->owner(Tenant::factory()->businessType(BusinessType::Fleet)->create());
        $this->actingAs($fleetOwner)->get(route('operator.fleet-trips.index'))->assertOk();
        $this->actingAs($fleetOwner)->get(route('operator.dashboard'))->assertOk()->assertSee('Fleet trips today')->assertSee(route('operator.fleet-trips.create'));
    }

    public function test_seeder_gives_existing_owner_roles_new_permissions(): void
    {
        $role = Role::forTenant($this->tenant->id)->where('slug', 'owner')->sole();
        $role->permissions()->detach(Permission::where('key', 'fleet_trips.manage')->value('id'));
        $this->assertFalse($this->owner->fresh()->hasPermission('fleet_trips.manage'));

        $this->seed(PlatformSeeder::class);
        $this->assertTrue($this->owner->fresh()->hasPermission('fleet_trips.manage'));
    }

    // ---------- Create, notify, number ----------

    public function test_create_trip_notifies_driver_and_numbers_it(): void
    {
        $v = $this->vehicle();
        [$d, $u] = $this->driver();
        $dept = $this->department();

        $this->actingAs($this->owner)->get(route('operator.fleet-trips.create'))->assertOk()->assertSee('GJ 01 AB 1234')->assertSee('Sales (CC-SAL)');
        $trip = $this->createTrip($v, $d, ['department_id' => $dept->id]);

        $this->assertMatchesRegularExpression('/^FT\d{4}-0001$/', $trip->trip_number);
        $this->assertSame('assigned', $trip->status);
        $this->assertSame('9825012345', $trip->requested_phone);
        $this->assertSame($this->tenant->id, $trip->tenant_id);
        $this->assertSame($dept->id, $trip->department_id);
        $this->assertSame('trip_assigned', $u->unreadNotifications()->sole()->data['kind']);
        $this->assertSame($trip->trip_number, $u->unreadNotifications()->sole()->data['booking']);
        $this->assertTrue(AuditLog::where('event', 'fleet_trip.created')->exists());

        $this->actingAs($this->owner)->get(route('operator.fleet-trips.index'))->assertOk()->assertSee($trip->trip_number)->assertSee('Client visit');
        $this->actingAs($this->owner)->get(route('operator.fleet-trips.show', $trip))->assertOk()->assertSee('GIDC Sanand')->assertSee('Mehul');
        $this->assertSame('0002', substr($this->createTrip($this->vehicle('GJ01AB2222'), $this->driver('9811100002')[0])->trip_number, -4));
    }

    public function test_validation_and_cross_tenant_ids_rejected(): void
    {
        $v = $this->vehicle();
        [$d] = $this->driver();
        $other = Tenant::factory()->businessType(BusinessType::Fleet)->create();
        $foreignDept = $this->department('Other', $other);
        $foreignVehicle = $this->vehicle('DL01AB0001', $other);

        $post = fn (array $o) => $this->actingAs($this->owner)->post(route('operator.fleet-trips.store'), $this->payload($v, $d, $o));
        $post(['department_id' => $foreignDept->id])->assertSessionHasErrors('department_id');
        $post(['vehicle_id' => $foreignVehicle->id])->assertSessionHasErrors('vehicle_id');
        $post(['end_at' => now()->addHour()->format('Y-m-d H:i')])->assertSessionHasErrors('end_at');
        $post(['purpose' => ''])->assertSessionHasErrors('purpose');
        $post(['passengers' => 9])->assertSessionHasErrors('vehicle_id'); // 4-seater
        $this->assertSame(0, Trip::withoutGlobalScopes()->count());
    }

    // ---------- Overlap rules shared with bookings ----------

    public function test_overlapping_fleet_trips_are_blocked_for_vehicle_and_driver(): void
    {
        $v = $this->vehicle();
        [$d] = $this->driver();
        [$d2] = $this->driver('9811100002');
        $first = $this->createTrip($v, $d);

        $this->actingAs($this->owner)->post(route('operator.fleet-trips.store'), $this->payload($v, $d2, ['start_at' => now()->addHours(5)->format('Y-m-d H:i'), 'end_at' => now()->addHours(8)->format('Y-m-d H:i')]))
            ->assertSessionHasErrors(['vehicle_id' => __('Vehicle is already booked :from–:to (:no).', ['from' => $first->start_at->format('d M H:i'), 'to' => $first->end_at->format('d M H:i'), 'no' => $first->trip_number])]);
        $this->actingAs($this->owner)->post(route('operator.fleet-trips.store'), $this->payload($this->vehicle('GJ01AB9999'), $d, ['start_at' => now()->addHours(3)->format('Y-m-d H:i')]))
            ->assertSessionHasErrors('driver_id');

        // Back-to-back is fine; availability endpoint marks busy resources.
        $this->createTrip($v, $d2, ['start_at' => now()->addHours(6)->format('Y-m-d H:i'), 'end_at' => now()->addHours(9)->format('Y-m-d H:i')]);
        $this->actingAs($this->owner)->getJson(route('operator.fleet-trips.availability', ['start_at' => now()->addHours(3)->format('Y-m-d H:i'), 'end_at' => now()->addHours(4)->format('Y-m-d H:i')]))
            ->assertOk()->assertJson(['vehicles' => [$v->id], 'drivers' => [$d->id]]);
    }

    public function test_customer_booking_cannot_take_a_vehicle_on_a_fleet_trip(): void
    {
        app(FareRuleService::class)->seedDefaults($this->tenant);
        $v = $this->vehicle();
        [$d] = $this->driver();
        [$d2] = $this->driver('9811100002');
        $this->createTrip($v, $d, ['start_at' => now()->addHour()->format('Y-m-d H:i'), 'end_at' => now()->addHours(10)->format('Y-m-d H:i')]);

        $this->actingAs($this->owner)->post(route('operator.bookings.store'), [
            'type' => 'local', 'vehicle_category_id' => $v->vehicle_category_id, 'customer_name' => 'Priya', 'customer_phone' => '9825012345',
            'pickup_address' => 'Ahmedabad', 'drop_address' => 'Gandhinagar', 'pickup_at' => now()->addHours(3)->format('Y-m-d H:i'),
            'distance_km' => 30, 'passengers' => 2, 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $b = Booking::withoutGlobalScopes()->latest('id')->first();
        $this->actingAs($this->owner)->post(route('operator.bookings.confirm', $b));
        $this->actingAs($this->owner)->post(route('operator.bookings.assign', $b), ['vehicle_id' => $v->id, 'driver_id' => $d2->id])->assertSessionHasErrors('vehicle_id');
    }

    // ---------- Driver app ----------

    public function test_driver_starts_without_otp_and_completes(): void
    {
        $v = $this->vehicle();
        [$d, $u] = $this->driver();
        $trip = $this->createTrip($v, $d, ['start_at' => now()->addMinutes(30)->format('Y-m-d H:i')]);

        $this->actingAs($u)->get(route('driver.home'))->assertOk()->assertSee($trip->trip_number)->assertSee('Client visit')->assertSee('Head office');
        $this->actingAs($u)->get(route('driver.trips.show', $trip))->assertOk()->assertSee('Odometer reading (km)')->assertDontSee('Customer OTP');

        $this->actingAs($u)->post(route('driver.trips.start', $trip), ['start_km' => 19000])->assertSessionHasErrors('start_km'); // below odometer
        $this->actingAs($u)->post(route('driver.trips.start', $trip), ['start_km' => 20010])->assertSessionHasNoErrors();
        $this->assertSame('started', $trip->fresh()->status);

        $this->actingAs($u)->post(route('driver.trips.complete', $trip), ['end_km' => 20000])->assertSessionHasErrors('end_km');
        $this->actingAs($u)->post(route('driver.trips.complete', $trip), ['end_km' => 20085])->assertSessionHasNoErrors();
        $trip->refresh();
        $this->assertSame('completed', $trip->status);
        $this->assertSame(75, $trip->km());
        $this->assertSame(20085, $v->fresh()->odometer_km);

        $this->actingAs($u)->get(route('driver.history'))->assertOk()->assertSee($trip->trip_number)->assertSee('75 km');
        $this->actingAs($u)->get(route('driver.earnings'))->assertOk();
    }

    public function test_driver_cannot_start_far_future_or_others_trip(): void
    {
        $v = $this->vehicle();
        [$d, $u] = $this->driver();
        [, $u2] = $this->driver('9811100002');
        $trip = $this->createTrip($v, $d, ['start_at' => now()->addDays(2)->format('Y-m-d H:i'), 'end_at' => now()->addDays(2)->addHours(3)->format('Y-m-d H:i')]);

        $this->actingAs($u2)->get(route('driver.trips.show', $trip))->assertNotFound();
        $this->actingAs($u2)->post(route('driver.trips.start', $trip), ['start_km' => 20000])->assertNotFound();
        $this->actingAs($u)->post(route('driver.trips.start', $trip), ['start_km' => 20000])->assertSessionHasErrors('start_km');
    }

    // ---------- Operator edit / start / complete / cancel ----------

    public function test_operator_edits_reassigns_and_cancels(): void
    {
        Notification::fake();
        $v = $this->vehicle();
        [$d] = $this->driver();
        [$d2] = $this->driver('9811100002');
        $trip = $this->createTrip($v, $d);

        // Editing its own time window must not clash with itself; reassigning notifies the new driver.
        $this->actingAs($this->owner)->get(route('operator.fleet-trips.edit', $trip))->assertOk();
        $this->actingAs($this->owner)->put(route('operator.fleet-trips.update', $trip), $this->payload($v, $d2, ['end_at' => now()->addHours(7)->format('Y-m-d H:i'), 'purpose' => 'Bank visit']))
            ->assertSessionHasNoErrors();
        $trip->refresh();
        $this->assertSame($d2->id, $trip->driver_id);
        $this->assertSame('Bank visit', $trip->purpose);

        $this->actingAs($this->owner)->post(route('operator.fleet-trips.cancel', $trip), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->owner)->post(route('operator.fleet-trips.cancel', $trip), ['reason' => 'Meeting postponed'])->assertSessionHas('success');
        $this->assertSame('released', $trip->fresh()->status);
        $this->actingAs($this->owner)->get(route('operator.fleet-trips.edit', $trip))->assertRedirect(route('operator.fleet-trips.show', $trip));
        $this->actingAs($this->owner)->post(route('operator.fleet-trips.start', $trip), ['start_km' => 20000])->assertSessionHasErrors('status');

        // The released window is free again.
        $this->createTrip($v, $d);
    }

    public function test_operator_can_run_trip_without_driver_app_and_booking_trips_are_not_fleet(): void
    {
        $v = $this->vehicle();
        [$d] = $this->driver();
        $trip = $this->createTrip($v, $d);
        $this->actingAs($this->owner)->post(route('operator.fleet-trips.start', $trip), ['start_km' => 20000])->assertSessionHas('success');
        $this->actingAs($this->owner)->post(route('operator.fleet-trips.complete', $trip), ['end_km' => 20120])->assertSessionHas('success');
        $this->assertSame(120, $trip->fresh()->km());
        $this->actingAs($this->owner)->post(route('operator.fleet-trips.complete', $trip), ['end_km' => 20200])->assertSessionHasErrors('status');
    }

    public function test_trips_are_tenant_isolated(): void
    {
        $trip = $this->createTrip($this->vehicle(), $this->driver()[0]);
        $other = $this->owner(Tenant::factory()->businessType(BusinessType::Fleet)->create());

        $this->actingAs($other)->get(route('operator.fleet-trips.show', $trip))->assertNotFound();
        $this->actingAs($other)->post(route('operator.fleet-trips.cancel', $trip), ['reason' => 'x'])->assertNotFound();
        $this->actingAs($other)->get(route('operator.fleet-trips.index'))->assertOk()->assertDontSee($trip->trip_number);
    }

    // ---------- Departments ----------

    public function test_departments_crud_and_unique_per_tenant(): void
    {
        $this->actingAs($this->owner)->post(route('operator.departments.store'), ['name' => 'Admin', 'code' => 'CC10'])->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('operator.departments.store'), ['name' => 'Admin'])->assertSessionHasErrors('name');
        $dept = Department::withoutGlobalScopes()->sole();
        $this->assertTrue($dept->is_active);

        $this->actingAs($this->owner)->put(route('operator.departments.update', $dept), ['name' => 'Administration', 'code' => 'CC10', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($dept->fresh()->is_active);
        $this->actingAs($this->owner)->get(route('operator.departments.index'))->assertOk()->assertSee('Administration')->assertSee('Inactive');

        // Inactive departments cannot be picked; another tenant can reuse the name.
        $this->actingAs($this->owner)->post(route('operator.fleet-trips.store'), $this->payload($this->vehicle(), $this->driver()[0], ['department_id' => $dept->id]))->assertSessionHasErrors('department_id');
        $otherOwner = $this->owner(Tenant::factory()->businessType(BusinessType::Fleet)->create());
        $this->actingAs($otherOwner)->post(route('operator.departments.store'), ['name' => 'Administration'])->assertSessionHasNoErrors();
        $this->actingAs($otherOwner)->put(route('operator.departments.update', $dept), ['name' => 'Hijack'])->assertNotFound();
    }

    // ---------- Fleet cost report ----------

    public function test_cost_report_by_vehicle_and_department_with_csv(): void
    {
        $v = $this->vehicle();
        [$d] = $this->driver();
        $sales = $this->department('Sales');
        $ops = $this->department('Ops');

        foreach ([[$sales, 20000, 20100], [$ops, 20100, 20400]] as $i => [$dept, $s, $e]) {
            $t = $this->createTrip($v, $d, ['department_id' => $dept->id, 'start_at' => now()->addHours(1 + $i * 5)->format('Y-m-d H:i'), 'end_at' => now()->addHours(4 + $i * 5)->format('Y-m-d H:i')]);
            $this->actingAs($this->owner)->post(route('operator.fleet-trips.start', $t), ['start_km' => $s]);
            $this->actingAs($this->owner)->post(route('operator.fleet-trips.complete', $t), ['end_km' => $e]);
        }
        $exp = fn (array $o) => $this->actingAs($this->owner)->post(route('operator.vehicles.expenses.store', $v), $o + ['expense_date' => now()->toDateString(), 'payment_method' => 'upi'])->assertSessionHasNoErrors();
        $exp(['category' => 'fuel', 'amount' => 3000, 'litres' => 30, 'odometer_km' => 20400]);
        $exp(['category' => 'toll', 'amount' => 600]);
        $this->actingAs($this->owner)->post(route('operator.vehicles.maintenance.store', $v), ['service_date' => now()->toDateString(), 'type' => 'service', 'description' => 'Oil', 'cost' => 400])->assertSessionHasNoErrors();

        // 400 km, cost 4000 → ₹10/km; Sales 100 km = ₹1,000, Ops 300 km = ₹3,000.
        $this->actingAs($this->owner)->get(route('operator.fleet-report.index'))->assertOk()
            ->assertSee('GJ 01 AB 1234')->assertSee('₹10.00')->assertSee('₹4,000')->assertSee('Sales')->assertSee('₹1,000')->assertSee('₹3,000');

        $csv = $this->actingAs($this->owner)->get(route('operator.fleet-report.export', ['tab' => 'departments']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Ops,1,300', $csv);
        $csv = $this->actingAs($this->owner)->get(route('operator.fleet-report.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('GJ01AB1234,car,2,2,400', $csv);
        $this->assertStringContainsString('4000.00,10.00', $csv);

        // Taxi-only operators get the vehicle report without the department section.
        $taxiOwner = $this->owner(Tenant::factory()->businessType(BusinessType::Taxi)->create());
        $this->actingAs($taxiOwner)->get(route('operator.fleet-report.index'))->assertOk()->assertDontSee('By department');
    }
}
