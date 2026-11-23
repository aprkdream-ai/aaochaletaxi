<?php

namespace Tests\Feature;

use App\Enums\BusinessType;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\Modules;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BusinessModulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function tenantOf(BusinessType $type): Tenant
    {
        return Tenant::factory()->businessType($type)->create();
    }

    public function test_permission_module_map(): void
    {
        $this->assertSame(Modules::TAXI, Modules::forPermission('bookings.assign'));
        $this->assertSame(Modules::TAXI, Modules::forPermission('fares.manage'));
        $this->assertSame(Modules::TAXI, Modules::forPermission('invoices.view'));
        $this->assertNull(Modules::forPermission('vehicles.manage'));
        $this->assertNull(Modules::forPermission('expenses.manage'));
        $this->assertSame(['vehicles.view'], Modules::filterPermissions(['vehicles.view', 'bookings.view'], [Modules::FLEET]));
    }

    public function test_existing_and_default_tenants_are_taxi(): void
    {
        $tenant = Tenant::factory()->create();
        $this->assertSame(BusinessType::Taxi, $tenant->fresh()->business_type);
        $this->assertTrue($tenant->hasModule(Modules::TAXI));
        $this->assertFalse($tenant->hasModule(Modules::FLEET));
    }

    public function test_fleet_only_operator_cannot_use_taxi_features(): void
    {
        $owner = $this->owner($this->tenantOf(BusinessType::Fleet));

        $this->assertFalse($owner->hasPermission('bookings.view'));
        $this->assertTrue($owner->hasPermission('vehicles.manage'));

        $this->actingAs($owner)->get(route('operator.bookings.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('operator.bookings.create'))->assertForbidden();
        $this->actingAs($owner)->get(route('operator.fares.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('operator.invoices.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('operator.vehicles.index'))->assertOk();

        $this->actingAs($owner)->get(route('operator.dashboard'))->assertOk()
            ->assertDontSee(route('operator.bookings.index'))
            ->assertDontSee('Your booking page')
            ->assertSee('In maintenance');
    }

    public function test_taxi_and_both_operators_keep_booking_features(): void
    {
        foreach ([BusinessType::Taxi, BusinessType::Both] as $type) {
            $owner = $this->owner($this->tenantOf($type));
            $this->actingAs($owner)->get(route('operator.bookings.index'))->assertOk();
            $this->actingAs($owner)->get(route('operator.dashboard'))->assertOk()->assertSee('Your booking page');
        }
    }

    public function test_microsite_is_only_for_taxi_module(): void
    {
        $fleet = $this->tenantOf(BusinessType::Fleet);
        $taxi = $this->tenantOf(BusinessType::Taxi);

        $this->get(route('microsite.show', $fleet->slug))->assertNotFound();
        $this->get(route('microsite.show', $taxi->slug))->assertOk();
    }

    public function test_registration_stores_business_type_and_defaults_to_taxi(): void
    {
        $payload = fn (string $suffix, array $extra = []) => [
            'business_name' => 'Acme '.$suffix, 'city' => 'Pune', 'name' => 'Owner '.$suffix, 'email' => "o{$suffix}@example.com",
            'phone' => '98765'.str_pad((string) crc32($suffix) % 100000, 5, '0', STR_PAD_LEFT), 'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => '1',
        ] + $extra;

        $this->get(route('register.operator'))->assertOk()->assertSee('Fleet management')->assertSee('Taxi + Fleet');

        $this->post(route('register.operator.store'), $payload('fleet', ['business_type' => 'fleet']))->assertRedirect(route('operator.status'));
        $tenant = Tenant::where('name', 'Acme fleet')->sole();
        $this->assertSame(BusinessType::Fleet, $tenant->business_type);
        $this->assertSame(BusinessType::Fleet, $tenant->subscriptions()->first()->business_type);
        auth()->logout();

        $this->post(route('register.operator.store'), $payload('plain'))->assertRedirect(route('operator.status'));
        $this->assertSame(BusinessType::Taxi, Tenant::where('name', 'Acme plain')->sole()->business_type);
        auth()->logout();

        $this->post(route('register.operator.store'), $payload('bad', ['business_type' => 'trucks']))->assertSessionHasErrors('business_type');
    }

    public function test_only_super_admin_side_can_change_business_type(): void
    {
        $tenant = $this->tenantOf(BusinessType::Taxi);
        $owner = $this->owner($tenant);

        // No operator route exists for this; the admin route rejects operators.
        $this->actingAs($owner)->post(route('admin.tenants.business-type', $tenant), ['business_type' => 'both'])->assertForbidden();
        $this->assertSame(BusinessType::Taxi, $tenant->fresh()->business_type);

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->post(route('admin.tenants.business-type', $tenant), ['business_type' => 'fleet', 'reason' => 'Corporate fleet'])
            ->assertSessionHas('success');
        $this->assertSame(BusinessType::Fleet, $tenant->fresh()->business_type);
        $this->assertTrue(AuditLog::where('event', 'tenant.business_type_changed')->where('auditable_id', $tenant->id)->exists());

        // Same type again → validation error; invalid value rejected.
        $this->actingAs($admin)->post(route('admin.tenants.business-type', $tenant), ['business_type' => 'fleet'])->assertSessionHasErrors('business_type');
        $this->actingAs($admin)->post(route('admin.tenants.business-type', $tenant), ['business_type' => 'x'])->assertSessionHasErrors('business_type');

        // Module access follows immediately.
        $this->actingAs($owner->fresh())->get(route('operator.bookings.index'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.tenants.show', $tenant))->assertOk()->assertSee('Fleet management');
        $this->actingAs($admin)->get(route('admin.tenants.index', ['type' => 'fleet']))->assertOk()->assertSee($tenant->name);
        $this->actingAs($admin)->get(route('admin.tenants.index', ['type' => 'taxi']))->assertOk()->assertDontSee($tenant->name);
    }

    public function test_addon_price_is_charged_only_for_both(): void
    {
        $plan = SubscriptionPlan::where('code', 'growth')->sole();
        $plan->update(['addon_price_monthly' => 700, 'addon_price_yearly' => 7000]);

        $this->assertSame('1499.00', $plan->priceFor('monthly', BusinessType::Taxi));
        $this->assertSame('1499.00', $plan->priceFor('monthly', BusinessType::Fleet));
        $this->assertSame('2199.00', $plan->priceFor('monthly', BusinessType::Both));
        $this->assertSame('21990.00', $plan->priceFor('yearly', BusinessType::Both));

        $admin = User::factory()->superAdmin()->create();
        $tenant = $this->tenantOf(BusinessType::Both);
        $sub = app(SubscriptionService::class)->activate($tenant, $plan, 'monthly', 'now', $admin);
        $this->assertSame('2199.00', $sub->price);
        $this->assertSame('700.00', $sub->addon_price);
        $this->assertSame(BusinessType::Both, $sub->business_type);

        $single = app(SubscriptionService::class)->activate($this->tenantOf(BusinessType::Fleet), $plan, 'monthly', 'now', $admin);
        $this->assertSame('1499.00', $single->price);
        $this->assertSame('0.00', $single->addon_price);
    }

    public function test_online_checkout_includes_addon(): void
    {
        PlatformSetting::setValue('razorpay_mode', 'test');
        PlatformSetting::setValue('razorpay_key_id', 'rzp_test_ABC123');
        PlatformSetting::setValue('razorpay_key_secret', 'test_key_secret', true);
        $plan = SubscriptionPlan::where('code', 'growth')->sole();
        $plan->update(['addon_price_monthly' => 700]);
        $owner = $this->owner($this->tenantOf(BusinessType::Both));
        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_ADDON', 'amount' => 259482, 'currency' => 'INR', 'status' => 'created'])]);

        $this->actingAs($owner)->get(route('operator.subscription'))->assertOk()->assertSee('incl. Fleet add-on')->assertSee('Taxi + Fleet');
        $this->actingAs($owner)->postJson(route('operator.checkout.store'), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly'])->assertOk();

        Http::assertSent(fn ($r) => $r->url() === 'https://api.razorpay.com/v1/orders' && $r['amount'] === 259482); // (1499 + 700) + 18% GST
    }

    public function test_admin_plan_form_saves_addon_prices(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $plan = SubscriptionPlan::where('code', 'starter')->sole();

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), [
            'name' => 'Starter', 'price_monthly' => 799, 'price_yearly' => 7990, 'addon_price_monthly' => 450, 'addon_price_yearly' => 4500,
            'max_vehicles' => 5, 'max_drivers' => 5, 'max_branches' => 1, 'max_users' => 3, 'trial_days' => 14, 'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame('450.00', $plan->fresh()->addon_price_monthly);
        $this->assertSame('4500.00', $plan->fresh()->addon_price_yearly);
    }

    public function test_role_editor_hides_and_preserves_switched_off_module_permissions(): void
    {
        $tenant = $this->tenantOf(BusinessType::Taxi);
        $owner = $this->owner($tenant);
        $role = Role::forTenant($tenant->id)->where('slug', 'booking-manager')->sole();
        $bookingsView = Permission::where('key', 'bookings.view')->value('id');
        $vehiclesView = Permission::where('key', 'vehicles.view')->value('id');

        $tenant->forceFill(['business_type' => BusinessType::Fleet])->save();

        $this->actingAs($owner)->get(route('operator.roles.edit', $role))->assertOk()->assertDontSee('Assign vehicles &amp; drivers', false);

        // Taxi permission cannot be granted while the module is off …
        $this->actingAs($owner)->put(route('operator.roles.update', $role), ['name' => 'Booking Manager', 'permissions' => [$bookingsView]])
            ->assertSessionHasErrors('permissions.0');

        // … and editing keeps the existing (hidden) taxi permissions for when the module returns.
        $this->actingAs($owner)->put(route('operator.roles.update', $role), ['name' => 'Booking Manager', 'permissions' => [$vehiclesView]])
            ->assertRedirect(route('operator.roles.index'));
        $this->assertTrue($role->permissions()->whereKey($bookingsView)->exists());
    }

    public function test_addon_price_seeder_fills_only_unset_plans(): void
    {
        SubscriptionPlan::query()->update(['addon_price_monthly' => 0, 'addon_price_yearly' => 0]);
        SubscriptionPlan::where('code', 'growth')->update(['addon_price_monthly' => 555]);

        $this->seed(\Database\Seeders\ModuleAddonPriceSeeder::class);
        $this->seed(\Database\Seeders\ModuleAddonPriceSeeder::class); // idempotent

        $this->assertSame('399.00', SubscriptionPlan::where('code', 'starter')->value('addon_price_monthly'));
        $this->assertSame('3990.00', SubscriptionPlan::where('code', 'starter')->value('addon_price_yearly'));
        $this->assertSame('555.00', SubscriptionPlan::where('code', 'growth')->value('addon_price_monthly')); // admin edit kept
    }
}
