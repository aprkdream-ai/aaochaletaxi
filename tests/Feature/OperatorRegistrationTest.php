<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class OperatorRegistrationTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Shree Ram Travels', 'city' => 'Jaipur', 'state' => 'Rajasthan',
            'name' => 'Mahesh Sharma', 'email' => 'Mahesh@Example.com', 'phone' => '9829012345',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => '1',
        ], $overrides);
    }

    public function test_registration_creates_pending_tenant_owner_roles_and_trial(): void
    {
        $this->post(route('register.operator.store'), $this->payload())->assertRedirect(route('operator.status'));

        $tenant = Tenant::sole();
        $this->assertSame('pending', $tenant->status->value);
        $this->assertSame('shree-ram-travels', $tenant->slug);

        $owner = User::where('email', 'mahesh@example.com')->sole();
        $this->assertSame($tenant->id, $owner->tenant_id);
        $this->assertSame('operator', $owner->type->value);
        $this->assertTrue($owner->hasRole('owner'));
        $this->assertTrue($owner->hasPermission('vehicles.manage'));
        $this->assertFalse($owner->hasPermission('admin.tenants.manage'));

        $this->assertSame(4, Role::where('tenant_id', $tenant->id)->count());

        $sub = Subscription::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame('trialing', $sub->status->value);
        $this->assertSame('starter', $sub->plan->code);
        $this->assertSame(now()->addDays(14)->toDateString(), $sub->ends_at->toDateString());

        $this->assertTrue(AuditLog::where('event', 'tenant.registered')->where('tenant_id', $tenant->id)->exists());
        $this->assertAuthenticatedAs($owner);
    }

    public function test_pending_operator_sees_status_page_and_cannot_use_panel(): void
    {
        $this->post(route('register.operator.store'), $this->payload());

        $this->get(route('operator.status'))->assertOk()->assertSee('verifying your business');
        $this->get(route('operator.dashboard'))->assertRedirect(route('operator.status'));
    }

    public function test_duplicate_email_or_phone_rejected(): void
    {
        $this->post(route('register.operator.store'), $this->payload());
        auth()->logout();

        $this->post(route('register.operator.store'), $this->payload(['business_name' => 'Other']))->assertSessionHasErrors(['email', 'phone']);
        $this->assertSame(1, Tenant::count());
    }

    public function test_validation(): void
    {
        $this->post(route('register.operator.store'), $this->payload(['phone' => '12345', 'password' => 'short', 'terms' => null]))
            ->assertSessionHasErrors(['phone', 'password', 'terms']);
        $this->assertSame(0, Tenant::count());
    }

    public function test_slug_is_unique_and_avoids_reserved_words(): void
    {
        Tenant::factory()->create(['slug' => 'shree-ram-travels']);
        $this->post(route('register.operator.store'), $this->payload());
        $this->assertTrue(Tenant::where('slug', 'shree-ram-travels-2')->exists());

        $this->assertSame('admin-2', Tenant::uniqueSlug('Admin'));
    }

    public function test_logged_in_users_cannot_see_registration(): void
    {
        $this->actingAs(User::factory()->customer()->create())->get(route('register.operator'))->assertRedirect(route('account.dashboard'));
    }
}
