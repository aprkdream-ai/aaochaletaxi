<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Driver;
use App\Models\FleetDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Notifications\FleetExpiryDigest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FleetTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->owner = $this->owner($this->tenant);
    }

    private function vehiclePayload(array $o = []): array
    {
        return array_merge([
            'registration_number' => 'gj 01 ab 1234', 'vehicle_category_id' => VehicleCategory::where('slug', 'sedan')->value('id'),
            'make' => 'Maruti Suzuki', 'model' => 'Dzire', 'year' => 2023, 'fuel_type' => 'cng', 'seats' => 4, 'is_ac' => 1,
            'ownership' => 'owned', 'status' => 'active', 'is_available' => 1, 'insurance_expiry' => now()->addDays(10)->toDateString(),
        ], $o);
    }

    private function driverPayload(array $o = []): array
    {
        return array_merge([
            'name' => 'Suresh Yadav', 'phone' => '9811122233', 'licence_number' => 'GJ01 20190012345', 'licence_expiry' => now()->addYears(3)->toDateString(),
            'pay_type' => 'monthly', 'pay_amount' => 18000, 'status' => 'active', 'is_available' => 1,
        ], $o);
    }

    private function as(User $u, \Closure $fn)
    {
        return app(TenantContext::class)->runAs(Tenant::find($u->tenant_id), $fn);
    }

    public function test_vehicle_crud_with_normalised_registration(): void
    {
        $this->actingAs($this->owner)->get(route('operator.vehicles.create'))->assertOk()->assertSee('Sedan');
        $this->actingAs($this->owner)->post(route('operator.vehicles.store'), $this->vehiclePayload())->assertSessionHasNoErrors()->assertRedirect();

        $v = Vehicle::withoutGlobalScopes()->sole();
        $this->assertSame('GJ01AB1234', $v->registration_number);
        $this->assertSame('GJ 01 AB 1234', $v->displayRegistration());
        $this->assertSame($this->tenant->id, $v->tenant_id);
        $this->assertSame('due', $v->complianceState());

        $this->actingAs($this->owner)->get(route('operator.vehicles.index'))->assertOk()->assertSee('GJ 01 AB 1234')->assertSee('Renewal due');
        $this->actingAs($this->owner)->get(route('operator.vehicles.show', $v))->assertOk()->assertSee('Dzire')->assertSee('In 10 days')->assertSee('Not set');

        // Duplicate in same fleet rejected; invalid number rejected.
        $this->actingAs($this->owner)->post(route('operator.vehicles.store'), $this->vehiclePayload(['registration_number' => 'GJ01AB1234']))->assertSessionHasErrors('registration_number');
        $this->actingAs($this->owner)->post(route('operator.vehicles.store'), $this->vehiclePayload(['registration_number' => 'HELLO']))->assertSessionHasErrors('registration_number');

        $this->actingAs($this->owner)->put(route('operator.vehicles.update', $v), $this->vehiclePayload(['status' => 'maintenance', 'is_available' => 0]))->assertSessionHasNoErrors();
        $this->assertSame('maintenance', $v->fresh()->status->value);

        $this->actingAs($this->owner)->delete(route('operator.vehicles.destroy', $v))->assertRedirect();
        $this->assertSoftDeleted('vehicles', ['id' => $v->id]);
    }

    public function test_same_registration_allowed_in_another_fleet_and_isolation(): void
    {
        $this->actingAs($this->owner)->post(route('operator.vehicles.store'), $this->vehiclePayload());
        $otherOwner = $this->owner(Tenant::factory()->create());
        $this->actingAs($otherOwner)->post(route('operator.vehicles.store'), $this->vehiclePayload())->assertSessionHasNoErrors();
        $this->assertSame(2, Vehicle::withoutGlobalScopes()->count());

        $mine = Vehicle::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sole();
        $this->actingAs($otherOwner)->get(route('operator.vehicles.show', $mine))->assertNotFound();
        $this->actingAs($otherOwner)->put(route('operator.vehicles.update', $mine), $this->vehiclePayload())->assertNotFound();
        $this->actingAs($otherOwner)->delete(route('operator.vehicles.destroy', $mine))->assertNotFound();
        $this->actingAs($otherOwner)->get(route('operator.vehicles.index'))->assertSee('GJ 01 AB 1234'); // their own copy
        $this->assertNull($mine->fresh()->deleted_at);
    }

    public function test_plan_limit_blocks_extra_vehicles(): void
    {
        // Factory tenants are on Starter (5 vehicles).
        foreach (range(1, 5) as $i) {
            $this->actingAs($this->owner)->post(route('operator.vehicles.store'), $this->vehiclePayload(['registration_number' => "GJ01AB100$i"]))->assertSessionHasNoErrors();
        }
        $this->actingAs($this->owner)->post(route('operator.vehicles.store'), $this->vehiclePayload(['registration_number' => 'GJ01AB1006']))
            ->assertSessionHasErrors('limit');
        $this->assertSame(5, Vehicle::withoutGlobalScopes()->count());
        $this->actingAs($this->owner)->from(route('operator.vehicles.create'))->post(route('operator.vehicles.store'), $this->vehiclePayload(['registration_number' => 'GJ01AB1007']))
            ->assertRedirect(route('operator.vehicles.create'));
        $this->actingAs($this->owner)->get(route('operator.vehicles.create'))->assertSee('Upgrade plan');
    }

    public function test_cannot_use_other_tenants_category_or_branch(): void
    {
        $other = Tenant::factory()->create();
        $foreignCat = new VehicleCategory(['name' => 'Secret', 'slug' => 'secret', 'seats' => 4]);
        $foreignCat->tenant_id = $other->id;
        $foreignCat->save();
        $foreignBranch = app(TenantContext::class)->runAs($other, fn () => Branch::create(['name' => 'X', 'code' => 'X', 'city' => 'Y']));

        $this->actingAs($this->owner)->post(route('operator.vehicles.store'), $this->vehiclePayload(['vehicle_category_id' => $foreignCat->id, 'branch_id' => $foreignBranch->id]))
            ->assertSessionHasErrors(['vehicle_category_id', 'branch_id']);
    }

    public function test_driver_with_app_access_can_log_in(): void
    {
        $this->actingAs($this->owner)->post(route('operator.drivers.store'), $this->driverPayload(['app_access' => 1, 'app_password' => 'drive1234', 'app_password_confirmation' => 'drive1234']))
            ->assertSessionHasNoErrors();
        $driver = Driver::withoutGlobalScopes()->sole();
        $this->assertSame('GJ0120190012345', $driver->licence_number);
        $this->assertNotNull($driver->user_id);
        $this->assertSame('driver', $driver->user->type->value);
        $this->assertSame($this->tenant->id, $driver->user->tenant_id);

        auth()->logout();
        $this->post(route('login.store'), ['login' => '9811122233', 'password' => 'drive1234'])->assertRedirect(route('driver.home'));
        $this->get(route('driver.home'))->assertOk()->assertSee('Suresh');

        // Disabling access blocks login.
        auth()->logout();
        $this->actingAs($this->owner)->post(route('operator.drivers.app-access', $driver), ['disable' => 1]);
        auth()->logout();
        $this->post(route('login.store'), ['login' => '9811122233', 'password' => 'drive1234'])->assertSessionHasErrors('login');
    }

    public function test_driver_validation_and_phone_conflicts(): void
    {
        User::factory()->customer()->create(['phone' => '9811122233']);
        $this->actingAs($this->owner)->post(route('operator.drivers.store'), $this->driverPayload(['app_access' => 1, 'app_password' => 'drive1234', 'app_password_confirmation' => 'drive1234']))
            ->assertSessionHasErrors('phone');
        $this->assertSame(0, Driver::withoutGlobalScopes()->count(), 'rolled back');

        $this->actingAs($this->owner)->post(route('operator.drivers.store'), $this->driverPayload(['licence_number' => '123', 'date_of_birth' => now()->subYears(16)->toDateString(), 'phone' => '555']))
            ->assertSessionHasErrors(['licence_number', 'date_of_birth', 'phone']);
    }

    public function test_documents_are_private_and_update_expiry(): void
    {
        Storage::fake('local');
        $this->actingAs($this->owner)->post(route('operator.vehicles.store'), $this->vehiclePayload());
        $v = Vehicle::withoutGlobalScopes()->sole();
        $date = now()->addYear()->toDateString();

        $this->actingAs($this->owner)->post(route('operator.vehicles.documents.store', $v), ['type' => 'insurance', 'number' => 'POL-1', 'expires_on' => $date, 'file' => UploadedFile::fake()->create('ins.pdf', 100, 'application/pdf')])
            ->assertSessionHasNoErrors();
        $doc = FleetDocument::withoutGlobalScopes()->sole();
        Storage::disk('local')->assertExists($doc->file_path);
        $this->assertSame($date, $v->fresh()->insurance_expiry->toDateString());

        $this->actingAs($this->owner)->get(route('operator.fleet-documents.show', $doc))->assertOk();
        $other = $this->owner(Tenant::factory()->create());
        $this->actingAs($other)->get(route('operator.fleet-documents.show', $doc))->assertNotFound();

        $accountant = User::factory()->operatorOf($this->tenant, 'accountant')->create();
        $this->actingAs($accountant)->get(route('operator.fleet-documents.show', $doc))->assertForbidden();

        $this->actingAs($this->owner)->post(route('operator.vehicles.documents.store', $v), ['type' => 'insurance', 'file' => UploadedFile::fake()->create('x.exe', 10, 'application/octet-stream')])->assertSessionHasErrors('file');
    }

    public function test_maintenance_and_expenses(): void
    {
        $this->actingAs($this->owner)->post(route('operator.vehicles.store'), $this->vehiclePayload());
        $v = Vehicle::withoutGlobalScopes()->sole();

        $this->actingAs($this->owner)->post(route('operator.vehicles.maintenance.store', $v), ['service_date' => now()->toDateString(), 'type' => 'service', 'description' => 'Oil change', 'cost' => 3200.50, 'odometer_km' => 45000])->assertSessionHasNoErrors();
        $this->assertSame(45000, $v->fresh()->odometer_km);

        $accountant = User::factory()->operatorOf($this->tenant, 'accountant')->create();
        $this->actingAs($accountant)->post(route('operator.vehicles.expenses.store', $v), ['expense_date' => now()->toDateString(), 'category' => 'fuel', 'amount' => 1500, 'litres' => 16, 'payment_method' => 'upi'])->assertSessionHasNoErrors();
        $this->actingAs($accountant)->post(route('operator.vehicles.maintenance.store', $v), ['service_date' => now()->toDateString(), 'type' => 'service', 'description' => 'x', 'cost' => 1])->assertForbidden();

        $this->actingAs($this->owner)->get(route('operator.vehicles.show', [$v, 'tab' => 'expenses']))->assertOk()->assertSee('₹1,500.00');
        $this->actingAs($this->owner)->get(route('operator.vehicles.show', [$v, 'tab' => 'maintenance']))->assertOk()->assertSee('Oil change')->assertSee('₹3,201');
        $this->actingAs($this->owner)->post(route('operator.vehicles.expenses.store', $v), ['expense_date' => now()->addDay()->toDateString(), 'category' => 'fuel', 'amount' => 10])->assertSessionHasErrors('expense_date');
    }

    public function test_expiry_digest_sent_once_per_day(): void
    {
        Notification::fake();
        $this->actingAs($this->owner)->post(route('operator.vehicles.store'), $this->vehiclePayload(['insurance_expiry' => now()->addDays(7)->toDateString()]));
        $this->actingAs($this->owner)->post(route('operator.drivers.store'), $this->driverPayload(['licence_expiry' => now()->addDays(30)->toDateString()]));

        $this->artisan('fleet:expiry-reminders')->assertSuccessful();
        $this->artisan('fleet:expiry-reminders')->assertSuccessful();
        Notification::assertSentToTimes($this->owner, FleetExpiryDigest::class, 1);
        Notification::assertSentTo($this->owner, FleetExpiryDigest::class, fn ($n) => count($n->items) === 2 && $n->items[0]['days'] === 7);

        $this->actingAs($this->owner)->get(route('operator.dashboard'))->assertOk()->assertSee('GJ 01 AB 1234')->assertSee('Driving licence');
    }

    public function test_permissions_for_fleet(): void
    {
        $bookingManager = User::factory()->operatorOf($this->tenant, 'booking-manager')->create();
        $this->actingAs($bookingManager)->get(route('operator.vehicles.index'))->assertOk();
        $this->actingAs($bookingManager)->get(route('operator.vehicles.create'))->assertForbidden();
        $this->actingAs($bookingManager)->get(route('operator.drivers.index'))->assertOk();
        $this->actingAs($bookingManager)->post(route('operator.drivers.store'), $this->driverPayload())->assertForbidden();
        $this->actingAs($bookingManager)->get(route('operator.staff.index'))->assertForbidden();
    }
}
