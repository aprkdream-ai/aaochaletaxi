<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\PlatformSetting;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPlatformTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_all_admin_pages_render(): void
    {
        $tenant = Tenant::factory()->create();
        foreach ([
            route('admin.dashboard'), route('admin.tenants.index'), route('admin.tenants.show', $tenant), route('admin.subscriptions.index'),
            route('admin.plans.index'), route('admin.plans.create'), route('admin.plans.edit', SubscriptionPlan::first()),
            route('admin.enquiries.index'), route('admin.support.index'), route('admin.staff.index'), route('admin.staff.create'),
            route('admin.settings.edit'), route('admin.settings.edit', ['group' => 'company']), route('admin.settings.edit', ['group' => 'payments']),
            route('admin.audit.index'),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
        $this->actingAs($this->admin)->get(route('admin.settings.edit', ['group' => 'nope']))->assertNotFound();
    }

    public function test_enquiry_inbox(): void
    {
        $m = ContactMessage::create(['name' => 'Ravi', 'phone' => '9876543210', 'message' => 'Need Innova to Shirdi', 'type' => 'enquiry']);
        $this->actingAs($this->admin)->get(route('admin.enquiries.index'))->assertSee('Need Innova to Shirdi');
        $this->actingAs($this->admin)->patch(route('admin.enquiries.update', $m), ['handled' => 1])->assertSessionHas('success');
        $this->assertNotNull($m->fresh()->handled_at);
        $this->assertSame($this->admin->id, $m->fresh()->handled_by);
        $this->actingAs($this->admin)->get(route('admin.enquiries.index'))->assertDontSee('Need Innova to Shirdi');
        $this->actingAs($this->admin)->get(route('admin.enquiries.index', ['state' => 'handled']))->assertSee('Need Innova to Shirdi');
    }

    public function test_plan_crud_with_immutable_code(): void
    {
        $payload = ['code' => 'Enterprise', 'name' => 'Enterprise', 'price_monthly' => 5999, 'price_yearly' => 59990, 'max_vehicles' => 500, 'max_drivers' => 600, 'max_branches' => 50, 'max_users' => 200, 'trial_days' => 0, 'features_text' => "API access\n\nDedicated manager", 'is_active' => 1];
        $this->actingAs($this->admin)->post(route('admin.plans.store'), $payload)->assertRedirect(route('admin.plans.index'));
        $plan = SubscriptionPlan::where('code', 'enterprise')->sole();
        $this->assertSame(['API access', 'Dedicated manager'], $plan->features);

        $this->actingAs($this->admin)->put(route('admin.plans.update', $plan), ['code' => 'hacked'] + $payload + ['name' => 'Enterprise+'])->assertRedirect();
        $this->assertSame('enterprise', $plan->fresh()->code);

        $this->actingAs($this->admin)->post(route('admin.plans.store'), ['code' => 'starter'] + $payload)->assertSessionHasErrors('code');
        $this->get(route('partner'))->assertSee('Enterprise');
    }

    public function test_settings_secrets_are_encrypted_and_not_audited(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['group' => 'payments', 'razorpay_mode' => 'test', 'razorpay_key_id' => 'rzp_test_1', 'razorpay_key_secret' => 'supersecret'])->assertRedirect()->assertSessionHasNoErrors();
        $raw = DB::table('platform_settings')->where('key', 'razorpay_key_secret')->value('value');
        $this->assertNotSame('supersecret', $raw);
        $this->assertSame('supersecret', PlatformSetting::getValue('razorpay_key_secret'));

        // Blank keeps the existing secret; page never echoes it.
        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['group' => 'payments', 'razorpay_mode' => 'test', 'razorpay_key_id' => 'rzp_test_1', 'razorpay_key_secret' => '']);
        $this->assertSame('supersecret', PlatformSetting::getValue('razorpay_key_secret'));
        $this->actingAs($this->admin)->get(route('admin.settings.edit', ['group' => 'payments']))->assertDontSee('supersecret')->assertSee('Configured');

        $this->assertStringNotContainsString('supersecret', AuditLog::where('event', 'platform.settings_updated')->get()->toJson());

        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['group' => 'payments', 'razorpay_mode' => 'disabled', 'clear_razorpay_key_secret' => 1]);
        $this->assertNull(PlatformSetting::getValue('razorpay_key_secret'));
    }

    public function test_billing_settings_validation_and_effect(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['group' => 'billing', 'trial_plan_code' => 'nope', 'trial_days' => 500, 'grace_days' => 3, 'subscription_gst_percent' => 18, 'default_gst_percent' => 5, 'marketplace_commission_percent' => 10, 'online_collection_fee_percent' => 2])
            ->assertSessionHasErrors(['trial_plan_code', 'trial_days']);
        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['group' => 'billing', 'trial_plan_code' => 'growth', 'trial_days' => 30, 'grace_days' => 3, 'subscription_gst_percent' => 18, 'default_gst_percent' => 5, 'marketplace_commission_percent' => 10, 'online_collection_fee_percent' => 2])->assertSessionHasNoErrors();

        $this->post('/logout');
        $this->post(route('register.operator.store'), ['business_name' => 'New Co', 'city' => 'Pune', 'name' => 'N', 'email' => 'n@example.com', 'phone' => '9876555555', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => 1]);
        $sub = Tenant::where('name', 'New Co')->sole()->usableSubscription();
        $this->assertSame('growth', $sub->plan->code);
        $this->assertSame(now()->addDays(30)->toDateString(), $sub->ends_at->toDateString());
    }

    public function test_staff_management(): void
    {
        $roleId = Role::forTenant(null)->where('slug', 'platform-support')->value('id');
        $tenantRoleId = Role::forTenant(Tenant::factory()->create()->id)->where('slug', 'owner')->value('id');

        $this->actingAs($this->admin)->post(route('admin.staff.store'), ['name' => 'Sita', 'email' => 'sita@aaochaletaxi.test', 'password' => 'support12345', 'password_confirmation' => 'support12345', 'status' => 'active', 'roles' => [$tenantRoleId]])
            ->assertSessionHasErrors('roles.0');
        $this->actingAs($this->admin)->post(route('admin.staff.store'), ['name' => 'Sita', 'email' => 'sita@aaochaletaxi.test', 'password' => 'support12345', 'password_confirmation' => 'support12345', 'status' => 'active', 'roles' => [$roleId]])
            ->assertRedirect(route('admin.staff.index'));

        $sita = User::where('email', 'sita@aaochaletaxi.test')->sole();
        $this->assertSame('platform_staff', $sita->type->value);
        $this->assertTrue($sita->hasPermission('admin.support.manage'));
        $this->assertFalse($sita->hasPermission('admin.settings.manage'));

        $this->actingAs($sita)->get(route('admin.staff.index'))->assertForbidden();
        $this->actingAs($sita)->get(route('admin.settings.edit'))->assertForbidden();

        $this->actingAs($this->admin)->put(route('admin.staff.update', $this->admin), ['name' => 'Boss', 'email' => $this->admin->email, 'status' => 'blocked'])->assertSessionHasErrors('status');
        $operator = $this->owner();
        $this->actingAs($this->admin)->get(route('admin.staff.edit', $operator))->assertNotFound();
    }

    public function test_audit_log_filters(): void
    {
        $tenant = Tenant::factory()->pending()->create();
        $this->actingAs($this->admin)->post(route('admin.tenants.approve', $tenant));
        $this->actingAs($this->admin)->get(route('admin.audit.index', ['event' => 'tenant.approved']))->assertOk()->assertSee('tenant.approved');
        $this->actingAs($this->admin)->get(route('admin.audit.index', ['from' => '2026-01-02', 'to' => '2026-01-01']))->assertSessionHasErrors('to');
    }
}
