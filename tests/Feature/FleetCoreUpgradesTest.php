<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleExpense;
use App\Models\VehicleInspection;
use App\Models\VehicleMaintenance;
use App\Notifications\FleetExpiryDigest;
use App\Notifications\VehicleInspectionIssue;
use App\Services\DriverService;
use App\Services\FleetExpiryService;
use App\Services\FuelEconomyService;
use App\Support\FleetOptions;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FleetCoreUpgradesTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->tenant = Tenant::factory()->create();
        $this->owner = $this->owner($this->tenant);
    }

    private function vehicle(array $o = []): Vehicle
    {
        return app(TenantContext::class)->runAs($this->tenant, fn () => Vehicle::create($o + [
            'registration_number' => 'GJ01AB1234', 'vehicle_category_id' => VehicleCategory::where('slug', 'sedan')->value('id'),
            'make' => 'Maruti', 'model' => 'Dzire', 'fuel_type' => 'diesel', 'seats' => 4, 'ownership' => 'owned', 'status' => 'active',
            'is_available' => true, 'insurance_expiry' => now()->addYear(), 'permit_expiry' => now()->addYear(), 'fitness_expiry' => now()->addYear(),
            'puc_expiry' => now()->addYear(), 'odometer_km' => 10000,
        ]));
    }

    /** @return array{0: Driver, 1: User} */
    private function driver(string $phone = '9811122233'): array
    {
        return app(TenantContext::class)->runAs($this->tenant, function () use ($phone) {
            $d = Driver::create(['name' => 'Suresh Kumar', 'phone' => $phone, 'licence_number' => 'GJ01201900'.substr($phone, -4), 'licence_expiry' => now()->addYears(2),
                'pay_type' => 'monthly', 'pay_amount' => 15000, 'status' => 'active', 'is_available' => true]);

            return [$d->fresh(), app(DriverService::class)->enableAppAccess($d, 'drive1234')];
        });
    }

    private function fuel(Vehicle $v, string $date, float $amount, float $litres, ?int $km, bool $full = true)
    {
        return $this->actingAs($this->owner)->post(route('operator.vehicles.expenses.store', $v), [
            'expense_date' => $date, 'category' => 'fuel', 'amount' => $amount, 'litres' => $litres, 'odometer_km' => $km, 'full_tank' => $full ? 1 : 0, 'payment_method' => 'upi',
        ]);
    }

    // ---------- Vehicle type, road tax, FASTag ----------

    public function test_vehicle_type_fastag_and_optional_road_tax(): void
    {
        $this->actingAs($this->owner)->post(route('operator.vehicles.store'), [
            'registration_number' => 'MH12AB1234', 'vehicle_category_id' => VehicleCategory::where('slug', 'bus')->value('id'), 'vehicle_type' => 'bus',
            'make' => 'Tata', 'model' => 'Starbus', 'fuel_type' => 'diesel', 'seats' => 32, 'ownership' => 'owned', 'status' => 'active',
            'fastag_id' => '34161fa8203286', 'road_tax_expiry' => now()->addDays(5)->toDateString(),
        ])->assertSessionHasNoErrors();
        $bus = Vehicle::withoutGlobalScopes()->where('registration_number', 'MH12AB1234')->sole();
        $this->assertSame('bus', $bus->vehicle_type);
        $this->assertSame('34161FA8203286', $bus->fastag_id);

        $this->actingAs($this->owner)->post(route('operator.vehicles.store'), [
            'registration_number' => 'MH12AB9999', 'vehicle_category_id' => VehicleCategory::where('slug', 'sedan')->value('id'), 'vehicle_type' => 'rocket',
            'make' => 'X', 'model' => 'Y', 'fuel_type' => 'diesel', 'seats' => 4, 'ownership' => 'owned', 'status' => 'active',
        ])->assertSessionHasErrors('vehicle_type');

        // Road tax only counts when set: a vehicle without it is not "missing" anything extra.
        $car = $this->vehicle();
        $this->assertArrayNotHasKey('road_tax_expiry', $car->expiries());
        $this->assertSame('ok', $car->complianceState());
        $this->assertArrayHasKey('road_tax_expiry', $bus->expiries());

        $this->actingAs($this->owner)->get(route('operator.vehicles.index', ['compliance' => 'attention']))->assertOk()->assertSee('MH 12 AB 1234')->assertDontSee('GJ 01 AB 1234');
        $this->actingAs($this->owner)->get(route('operator.vehicles.show', $bus))->assertOk()->assertSee('Road tax')->assertSee('34161FA8203286')->assertSee('Bus');
        $this->actingAs($this->owner)->get(route('operator.vehicles.create'))->assertOk()->assertSee('Vehicle type')->assertSee('FASTag ID');
    }

    public function test_road_tax_document_updates_expiry(): void
    {
        Storage::fake('local');
        $v = $this->vehicle();
        $this->actingAs($this->owner)->post(route('operator.vehicles.documents.store', $v), [
            'type' => 'road_tax', 'expires_on' => now()->addMonths(6)->toDateString(), 'file' => UploadedFile::fake()->create('tax.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertSame(now()->addMonths(6)->toDateString(), $v->fresh()->road_tax_expiry->toDateString());
    }

    // ---------- Fuel log & mileage ----------

    public function test_fuel_log_requires_litres_and_updates_odometer(): void
    {
        $v = $this->vehicle();
        $this->actingAs($this->owner)->post(route('operator.vehicles.expenses.store', $v), ['expense_date' => now()->toDateString(), 'category' => 'fuel', 'amount' => 2000])
            ->assertSessionHasErrors('litres');
        $this->fuel($v, now()->toDateString(), 2000, 21.5, 10500)->assertSessionHasNoErrors();
        $this->assertSame(10500, $v->fresh()->odometer_km);

        // Fuel-only fields are ignored for other categories.
        $this->actingAs($this->owner)->post(route('operator.vehicles.expenses.store', $v), ['expense_date' => now()->toDateString(), 'category' => 'toll', 'amount' => 150, 'litres' => 9, 'odometer_km' => 99999])
            ->assertSessionHasNoErrors();
        $toll = VehicleExpense::withoutGlobalScopes()->where('category', 'toll')->sole();
        $this->assertNull($toll->litres);
        $this->assertSame(10500, $v->fresh()->odometer_km);
    }

    public function test_mileage_full_to_full_with_partial_fill_and_low_flag(): void
    {
        $v = $this->vehicle();
        $d = now()->subDays(10);
        $this->fuel($v, $d->toDateString(), 4000, 40, 10000);                       // baseline full
        $this->fuel($v, $d->addDay()->toDateString(), 1000, 10, 10300, false);      // partial: litres carried forward
        $this->fuel($v, $d->addDay()->toDateString(), 3000, 30, 10800);             // 800 km / 40 L = 20.00
        $this->fuel($v, $d->addDay()->toDateString(), 4000, 40, 11600);             // 800 / 40 = 20.00
        $this->fuel($v, $d->addDay()->toDateString(), 4000, 40, 12400);             // 800 / 40 = 20.00
        $this->fuel($v, $d->addDay()->toDateString(), 5000, 50, 12900);             // 500 / 50 = 10.00 → low

        $r = app(TenantContext::class)->runAs($this->tenant, fn () => app(FuelEconomyService::class)->forVehicle($v));
        $kmpl = array_values(array_column($r['fills'], 'kmpl'));
        $this->assertSame(['20.00', '20.00', '20.00', '10.00'], $kmpl);
        $this->assertSame('17.05', $r['average']); // 2900 km / 170 L (baseline fill excluded), truncated
        $this->assertSame([false, false, false, true], array_values(array_column($r['fills'], 'low')));
        $this->assertSame('100.00', $r['rate']);

        $this->actingAs($this->owner)->get(route('operator.vehicles.show', [$v, 'tab' => 'expenses']))->assertOk()
            ->assertSee('20 km/l')->assertSee('10 km/l')->assertSee('Mileage (average)')->assertSee('partial');
    }

    public function test_mileage_needs_two_full_fills(): void
    {
        $r = app(FuelEconomyService::class)->compute(collect([
            new VehicleExpense(['amount' => 1000, 'litres' => 10, 'odometer_km' => 100, 'full_tank' => true]),
        ]));
        $this->assertNull($r['average']);
        $this->assertSame([], $r['fills']);
    }

    // ---------- Service reminders ----------

    public function test_service_reminders_by_date_and_km_and_superseded_by_newer_service(): void
    {
        $car = $this->vehicle(['odometer_km' => 19700]);
        $van = $this->vehicle(['registration_number' => 'GJ01AB5555']);
        $post = fn (Vehicle $v, array $o) => $this->actingAs($this->owner)->post(route('operator.vehicles.maintenance.store', $v),
            $o + ['service_date' => now()->subMonths(5)->toDateString(), 'type' => 'service', 'description' => 'Service', 'cost' => 2500])->assertSessionHasNoErrors();

        $post($car, ['next_service_km' => 20000]);                                        // 300 km left → due soon
        $post($van, ['next_service_date' => now()->addDays(3)->toDateString()]);          // 3 days

        $items = app(FleetExpiryService::class)->serviceItemsFor($this->tenant);
        $this->assertCount(2, $items);
        $this->assertSame('GJ 01 AB 5555', $items[0]['name']); // 3 days is more urgent than 300 km (=6 units)
        $this->assertSame(300, $items[1]['km_left']);

        $this->actingAs($this->owner)->get(route('operator.dashboard'))->assertOk()->assertSee('Service due')->assertSee('300 km left')->assertSee('3 days');

        // A newer service record without a next date replaces the reminder.
        $post($van, ['service_date' => now()->toDateString()]);
        $this->assertCount(1, app(FleetExpiryService::class)->serviceItemsFor($this->tenant));

        // Digest: date stage + km reminder once; second run (next day) does not repeat the km reminder.
        $this->artisan('fleet:expiry-reminders')->assertSuccessful();
        Notification::assertSentTo($this->owner, FleetExpiryDigest::class, fn ($n) => collect($n->items)->contains(fn ($i) => $i['kind'] === 'service' && $i['km_left'] === 300));
        $this->travel(1)->days();
        $this->assertSame([], app(FleetExpiryService::class)->serviceItemsFor($this->tenant, digest: true));
    }

    public function test_maintenance_form_accepts_next_service_km(): void
    {
        $v = $this->vehicle();
        $this->actingAs($this->owner)->post(route('operator.vehicles.maintenance.store', $v), [
            'service_date' => now()->toDateString(), 'type' => 'tyres', 'description' => 'Rotation', 'cost' => 400, 'next_service_km' => 15000,
        ])->assertSessionHasNoErrors();
        $this->assertSame(15000, VehicleMaintenance::withoutGlobalScopes()->sole()->next_service_km);
        $this->actingAs($this->owner)->get(route('operator.vehicles.show', [$v, 'tab' => 'maintenance']))->assertOk()->assertSee('15,000 km');
    }

    // ---------- Driver daily checklist ----------

    private function allOk(): array
    {
        return array_fill_keys(array_keys(FleetOptions::inspectionItems()), 'ok');
    }

    public function test_driver_submits_all_ok_check_once_per_day(): void
    {
        $v = $this->vehicle();
        [, $user] = $this->driver();

        $this->actingAs($user)->get(route('driver.check'))->assertOk()->assertSee('Daily vehicle check')->assertSee('GJ 01 AB 1234');
        $this->actingAs($user)->post(route('driver.check.store'), ['vehicle_id' => $v->id, 'odometer_km' => 10120, 'items' => $this->allOk()])
            ->assertRedirect(route('driver.check'))->assertSessionHas('success');

        $ins = VehicleInspection::withoutGlobalScopes()->sole();
        $this->assertSame(0, $ins->issues_count);
        $this->assertSame($this->tenant->id, $ins->tenant_id);
        $this->assertSame(10120, $v->fresh()->odometer_km);
        Notification::assertNothingSentTo($this->owner, VehicleInspectionIssue::class);

        $this->actingAs($user)->post(route('driver.check.store'), ['vehicle_id' => $v->id, 'items' => $this->allOk()])->assertSessionHasErrors('vehicle_id');
        $this->actingAs($user)->post(route('driver.check.store'), ['vehicle_id' => $v->id, 'items' => ['tyres' => 'ok']])->assertSessionHasErrors('items.brakes');
    }

    public function test_problem_alerts_owner_and_can_be_resolved(): void
    {
        Storage::fake('local');
        $v = $this->vehicle();
        [, $user] = $this->driver();
        $items = ['brakes' => 'issue', 'lights' => 'issue'] + $this->allOk();

        $this->actingAs($user)->post(route('driver.check.store'), ['vehicle_id' => $v->id, 'items' => $items])->assertSessionHasErrors('notes');
        $this->actingAs($user)->post(route('driver.check.store'), ['vehicle_id' => $v->id, 'items' => $items, 'notes' => 'Brake noise', 'photo' => UploadedFile::fake()->image('b.jpg')])
            ->assertSessionHasNoErrors();

        $ins = VehicleInspection::withoutGlobalScopes()->sole();
        $this->assertSame(2, $ins->issues_count);
        Storage::disk('local')->assertExists($ins->photo_path);
        Notification::assertSentTo($this->owner, VehicleInspectionIssue::class, fn ($n) => $n->issues === ['Brakes', 'Lights & indicators']);

        $this->actingAs($this->owner)->get(route('operator.dashboard'))->assertOk()->assertSee('Daily check problems')->assertSee('Brakes');
        $this->actingAs($this->owner)->get(route('operator.vehicles.show', [$v, 'tab' => 'inspections']))->assertOk()->assertSee('Brake noise')->assertSee('Mark resolved');
        $this->actingAs($this->owner)->get(route('operator.inspections.photo', $ins))->assertOk();

        $this->actingAs($this->owner)->post(route('operator.inspections.resolve', $ins))->assertSessionHas('success');
        $this->assertNotNull($ins->fresh()->resolved_at);
        $this->assertTrue(AuditLog::where('event', 'vehicle.inspection_resolved')->exists());
        $this->actingAs($this->owner)->get(route('operator.dashboard'))->assertOk()->assertDontSee('Daily check problems');
    }

    public function test_inspections_are_tenant_isolated(): void
    {
        [, $user] = $this->driver();
        $other = Tenant::factory()->create();
        $foreign = app(TenantContext::class)->runAs($other, fn () => Vehicle::create([
            'registration_number' => 'DL01AB0001', 'vehicle_category_id' => VehicleCategory::where('slug', 'sedan')->value('id'),
            'make' => 'X', 'model' => 'Y', 'fuel_type' => 'diesel', 'seats' => 4, 'ownership' => 'owned', 'status' => 'active', 'is_available' => true,
        ]));

        $this->actingAs($user)->post(route('driver.check.store'), ['vehicle_id' => $foreign->id, 'items' => $this->allOk()])->assertSessionHasErrors('vehicle_id');
        $this->actingAs($user)->get(route('driver.check'))->assertOk()->assertDontSee('DL 01 AB 0001');

        $v = $this->vehicle();
        $this->actingAs($user)->post(route('driver.check.store'), ['vehicle_id' => $v->id, 'items' => ['tyres' => 'issue'] + $this->allOk(), 'notes' => 'Flat']);
        $ins = VehicleInspection::withoutGlobalScopes()->sole();
        $otherOwner = $this->owner($other);
        $this->actingAs($otherOwner)->post(route('operator.inspections.resolve', $ins))->assertNotFound();
        $this->actingAs($otherOwner)->get(route('operator.inspections.photo', $ins))->assertNotFound();
    }
}
