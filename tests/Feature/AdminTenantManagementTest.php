<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantOnboardingService;
use Tests\TestCase;

class AdminTenantManagementTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_index_filters_and_search(): void
    {
        Tenant::factory()->create(['name' => 'Alpha Cabs']);
        Tenant::factory()->pending()->create(['name' => 'Beta Tours']);

        $this->actingAs($this->admin)->get(route('admin.tenants.index'))->assertOk()->assertSee('Alpha Cabs')->assertSee('Beta Tours');
        $this->actingAs($this->admin)->get(route('admin.tenants.index', ['status' => 'pending']))->assertSee('Beta Tours')->assertDontSee('Alpha Cabs');
        $this->actingAs($this->admin)->get(route('admin.tenants.index', ['q' => 'alpha']))->assertSee('Alpha Cabs')->assertDontSee('Beta Tours');
        $this->actingAs($this->admin)->get(route('admin.tenants.index', ['status' => 'bogus']))->assertSessionHasErrors('status');
    }

    public function test_show_page_with_subscription_and_users(): void
    {
        $owner = app(TenantOnboardingService::class)->register([
            'business_name' => 'Gamma Travels', 'city' => 'Surat', 'name' => 'G Owner', 'email' => 'g@example.com', 'phone' => '9876500099', 'password' => 'secret123',
        ]);

        $this->actingAs($this->admin)->get(route('admin.tenants.show', $owner->tenant))
            ->assertOk()->assertSee('Gamma Travels')->assertSee('G Owner')->assertSee('Starter')->assertSee('Trialing');
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Gamma Travels');
    }

    public function test_full_lifecycle_approve_suspend_reactivate(): void
    {
        $tenant = Tenant::factory()->pending()->create();
        $owner = $this->owner($tenant);

        $this->actingAs($this->admin)->post(route('admin.tenants.approve', $tenant))->assertSessionHas('success');
        $tenant->refresh();
        $this->assertSame('active', $tenant->status->value);
        $this->assertSame($this->admin->id, $tenant->approved_by);
        $this->actingAs($owner)->get(route('operator.dashboard'))->assertOk();

        $this->actingAs($this->admin)->post(route('admin.tenants.suspend', $tenant), ['reason' => 'Non payment'])->assertSessionHas('success');
        $this->assertSame('suspended', $tenant->fresh()->status->value);
        $this->actingAs($owner)->get(route('operator.dashboard'))->assertRedirect(route('operator.status'));

        $this->actingAs($this->admin)->post(route('admin.tenants.reactivate', $tenant))->assertSessionHas('success');
        $this->assertSame('active', $tenant->fresh()->status->value);
        $this->assertNull($tenant->fresh()->suspension_reason);

        $events = AuditLog::where('tenant_id', $tenant->id)->pluck('event')->all();
        $this->assertContains('tenant.approved', $events);
        $this->assertContains('tenant.suspended', $events);
        $this->assertContains('tenant.reactivated', $events);
        $this->assertSame($this->admin->id, AuditLog::where('event', 'tenant.suspended')->value('user_id'));
    }

    public function test_invalid_transitions_rejected(): void
    {
        $tenant = Tenant::factory()->create(); // active
        $this->actingAs($this->admin)->post(route('admin.tenants.approve', $tenant))->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->post(route('admin.tenants.reactivate', $tenant))->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->post(route('admin.tenants.suspend', $tenant), [])->assertSessionHasErrors('reason');
    }

    public function test_reject_pending(): void
    {
        $tenant = Tenant::factory()->pending()->create();
        $this->actingAs($this->admin)->post(route('admin.tenants.reject', $tenant), ['reason' => 'Fake documents'])->assertSessionHas('success');
        $this->assertSame('rejected', $tenant->fresh()->status->value);
    }

    public function test_kyc_update(): void
    {
        $tenant = Tenant::factory()->create();
        $this->actingAs($this->admin)->post(route('admin.tenants.kyc', $tenant), ['kyc_status' => 'verified', 'kyc_notes' => 'GST ok'])->assertSessionHas('success');
        $this->assertSame('verified', $tenant->fresh()->kyc_status->value);
        $this->actingAs($this->admin)->post(route('admin.tenants.kyc', $tenant), ['kyc_status' => 'nope'])->assertSessionHasErrors('kyc_status');
    }

    public function test_csrf_protection_enabled_on_state_changes(): void
    {
        $tenant = Tenant::factory()->pending()->create();
        $this->withoutMiddleware()->actingAs($this->admin); // sanity: route exists
        $this->assertTrue(app('router')->has('admin.tenants.approve'));
        $this->assertContains('web', app('router')->getRoutes()->getByName('admin.tenants.approve')->gatherMiddleware());
    }
}
