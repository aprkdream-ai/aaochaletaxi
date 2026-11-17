<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_with_email_redirects_by_user_type(): void
    {
        $admin = User::factory()->superAdmin()->create(['email' => 'boss@example.com']);

        $this->post(route('login.store'), ['login' => 'BOSS@example.com', 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_login_with_phone(): void
    {
        $customer = User::factory()->customer()->create(['phone' => '9876543210']);

        $this->post(route('login.store'), ['login' => '+91 98765 43210', 'password' => 'password'])
            ->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs($customer);
    }

    public function test_operator_lands_on_operator_dashboard(): void
    {
        $owner = $this->owner();
        $this->post(route('login.store'), ['login' => $owner->email, 'password' => 'password'])->assertRedirect(route('operator.dashboard'));
        $this->get(route('operator.dashboard'))->assertOk()->assertSee($owner->tenant->name);
    }

    public function test_wrong_password_fails(): void
    {
        $user = User::factory()->create();
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_blocked_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create();
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_user_blocked_after_login_is_logged_out(): void
    {
        $user = User::factory()->customer()->create();
        $this->actingAs($user);
        $user->forceFill(['status' => 'blocked'])->save();

        $this->get(route('account.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['login' => $user->email, 'password' => 'bad']);
        }
        $this->post(route('login.store'), ['login' => $user->email, 'password' => 'password'])->assertStatus(429);
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->create())->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['admin.dashboard', 'operator.dashboard', 'account.dashboard', 'driver.home'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_customer_registration(): void
    {
        $this->post(route('register.customer.store'), [
            'name' => 'Priya', 'phone' => '98765 11111', 'email' => '', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => '1',
        ])->assertRedirect(route('account.dashboard'));

        $user = User::where('phone', '9876511111')->sole();
        $this->assertSame('customer', $user->type->value);
        $this->assertNull($user->tenant_id);
        $this->assertNull($user->email);
        $this->assertAuthenticatedAs($user);
    }

    public function test_customer_registration_cannot_escalate_type_or_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $this->post(route('register.customer.store'), [
            'name' => 'Hacker', 'phone' => '9876522222', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => '1',
            'type' => 'super_admin', 'tenant_id' => $tenant->id, 'status' => 'active',
        ]);

        $user = User::where('phone', '9876522222')->sole();
        $this->assertSame('customer', $user->type->value);
        $this->assertNull($user->tenant_id);
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->post(route('password.email'), ['email' => 'reset@example.com'])->assertSessionHas('success');
        // Unknown email gives the same response (no enumeration).
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->get(route('password.reset', ['token' => $notification->token, 'email' => $user->email]))->assertOk();
            $this->post(route('password.update'), [
                'token' => $notification->token, 'email' => $user->email, 'password' => 'newpass123', 'password_confirmation' => 'newpass123',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->post(route('login.store'), ['login' => 'reset@example.com', 'password' => 'newpass123'])->assertRedirect();
        $this->assertAuthenticated();
    }
}
